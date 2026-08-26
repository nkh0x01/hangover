<?php
/**
 * includes/service_v2.php — Service v2 (TASK 2): სტატუს-მანქანა + helpers. Additive.
 * flow: received → in_diagnostic → waiting_customer_approval → waiting_part → in_repair → quality_check → ready → returned
 */

function svStatuses()
{
    return ['draft','intake_pending_signature','received','in_diagnostic','waiting_customer_approval',
            'waiting_part','in_repair','quality_check','ready','returned','cancelled'];
}

function svLabel($s)
{
    static $L = [
        'draft' => 'დრაფტი', 'intake_pending_signature' => 'ხელმოწერას ელოდება', 'received' => 'მიღებულია',
        'in_diagnostic' => 'დიაგნოსტიკა', 'waiting_customer_approval' => 'ელოდება მომხმარებლის თანხმობას',
        'waiting_part' => 'ელოდება ნაწილს', 'in_repair' => 'შეკეთებაში', 'quality_check' => 'ხარისხის კონტროლი (QA)',
        'ready' => 'მზადაა', 'returned' => 'გაცემულია', 'cancelled' => 'გაუქმებული',
    ];
    return $L[$s] ?? $s;
}

/* დაშვებული გადასვლები — დაუშვებელ jump-ს svSetStatus ბლოკავს */
function svAllowed()
{
    return [
        'received'                  => ['in_diagnostic', 'in_repair', 'waiting_customer_approval', 'cancelled'],
        'in_diagnostic'             => ['waiting_customer_approval', 'waiting_part', 'in_repair', 'cancelled'],
        'waiting_customer_approval' => ['in_repair', 'in_diagnostic', 'cancelled'],
        'waiting_part'              => ['in_repair', 'cancelled'],
        'in_repair'                 => ['quality_check', 'waiting_part', 'cancelled'],
        'quality_check'             => ['ready', 'in_repair'],
        'ready'                     => ['returned'],
    ];
}

function svCan($from, $to)
{
    $m = svAllowed();
    return isset($m[$from]) && in_array($to, $m[$from], true);
}

function svAddActivity(PDO $pdo, $caseId, $type, $note = null, $meta = null, $userId = null)
{
    try {
        $pdo->prepare("INSERT INTO gw_service_activities (service_case_id, type, user_id, note, meta_json) VALUES (?,?,?,?,?)")
            ->execute([(int)$caseId, substr((string)$type, 0, 16),
                       $userId !== null ? (int)$userId : null, $note,
                       $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
    } catch (Throwable $e) { error_log('svAddActivity: ' . $e->getMessage()); }
}

/** guard-იანი სტატუსის ცვლილება. აბრუნებს [ok(bool), errMsg(?string)] */
function svSetStatus(PDO $pdo, $caseId, $to, $userId = null, $note = null)
{
    $to = (string)$to;
    if (!in_array($to, svStatuses(), true)) return [false, 'უცნობი სტატუსი: ' . $to];
    $q = $pdo->prepare("SELECT status FROM gw_service_cases WHERE id=?");
    $q->execute([(int)$caseId]);
    $from = $q->fetchColumn();
    if ($from === false) return [false, 'ქეისი ვერ მოიძებნა'];
    if ($from === $to) return [true, null];
    if (!svCan($from, $to)) return [false, 'დაუშვებელი გადასვლა: ' . svLabel($from) . ' → ' . svLabel($to)];
    $pdo->prepare("UPDATE gw_service_cases SET status=? WHERE id=?")->execute([$to, (int)$caseId]);
    try { auditLog($pdo, 'service_case', (int)$caseId, 'status_changed', 'status', $from, $to); } catch (Throwable $e) {}
    svAddActivity($pdo, $caseId, 'worklog', $note ?: ('სტატუსი: ' . svLabel($from) . ' → ' . svLabel($to)),
                  ['from' => $from, 'to' => $to], $userId);
    return [true, null];
}

/** ნაწილების ჯამური ღირებულება (actual თუ არის, თორემ est) */
function svPartsCost(PDO $pdo, $caseId)
{
    try {
        return (float)$pdo->query("SELECT COALESCE(SUM(qty * COALESCE(actual_unit_cost, est_unit_cost, 0)),0)
            FROM gw_service_parts WHERE service_case_id=" . (int)$caseId . " AND status NOT IN ('cancelled','unavailable')")->fetchColumn();
    } catch (Throwable $e) { return 0.0; }
}

/** ღია კრიტიკული ნაწილები (requested/ordered) */
function svOpenCriticalParts(PDO $pdo, $caseId)
{
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM gw_service_parts
            WHERE service_case_id=" . (int)$caseId . " AND is_critical=1 AND status IN ('requested','ordered')")->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function svLatestEstimate(PDO $pdo, $caseId)
{
    try {
        $q = $pdo->prepare("SELECT * FROM gw_service_estimates WHERE service_case_id=? ORDER BY version DESC, id DESC LIMIT 1");
        $q->execute([(int)$caseId]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    } catch (Throwable $e) { return null; }
}

/** "მზადაა" SMS — იდემპოტენტური key-ით (ერთხელ იგზავნება, ვინც პირველი გამოიძახებს) */
function svSendReadySms(PDO $pdo, $caseId)
{
    try {
        $q = $pdo->prepare("SELECT case_number, customer_phone, public_slug FROM gw_service_cases WHERE id=?");
        $q->execute([(int)$caseId]);
        $c = $q->fetch(PDO::FETCH_ASSOC);
        if (!$c || empty($c['customer_phone'])) return;
        $link = (defined('SITE_URL') ? SITE_URL : '') . '/g.php?c=' . $c['public_slug'];
        if (function_exists('queueSmsNow')) {
            queueSmsNow('service_ready', $c['customer_phone'],
                "გაჯეტი: თქვენი ნივთი (#{$c['case_number']}) მზადაა გასატანად! სტატუსი: {$link}",
                'service:' . (int)$caseId . ':ready', 'service_case', (int)$caseId);
        }
    } catch (Throwable $e) { error_log('svSendReadySms: ' . $e->getMessage()); }
}
