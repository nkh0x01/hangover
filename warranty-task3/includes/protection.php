<?php
/**
 * includes/protection.php — Protection / Claims (TASK 3). Additive.
 * eligibility · BOG activation (idempotent + amount-checked) · claim guard · recorded-cost sync
 */

/** ინციდენტების ტიპები (coverage_json-ის მნიშვნელობები) */
function protIncidentTypes()
{
    return [
        'screen_damage' => 'ეკრანის დაზიანება',
        'liquid_damage' => 'სითხის დაზიანება',
        'mechanical'    => 'მექანიკური დაზიანება',
        'theft'         => 'ქურდობა',
        'battery'       => 'ბატარეის გაუარესება',
        'other'         => 'სხვა',
    ];
}

function protClaimStatusLabel($s)
{
    static $L = ['submitted' => 'შემოსული', 'approved' => 'დამტკიცებული', 'rejected' => 'უარყოფილი',
                 'in_service' => 'სერვისში', 'resolved' => 'დასრულებული'];
    return $L[$s] ?? $s;
}

function protStatusLabel($s)
{
    static $L = ['active' => 'აქტიური', 'expired' => 'ვადაგასული', 'cancelled' => 'გაუქმებული', 'used' => 'გამოყენებული'];
    return $L[$s] ?? $s;
}

function protJson($raw)
{
    if ($raw === null || $raw === '') return [];
    $d = json_decode((string)$raw, true);
    return is_array($d) ? $d : [];
}

/** გეგმის ფასი კონკრეტული ნივთისთვის (fixed ან percent პროდუქტის ფასიდან) */
function protPlanPrice(array $plan, $productPrice)
{
    $pp = (float)$productPrice;
    if (($plan['price_type'] ?? 'fixed') === 'percent') {
        $v = $pp * ((float)$plan['price_value'] / 100);
        return $v > 0 ? round(ceil($v / 10) * 10, 2) : 0.0;   // 10-მდე დამრგვალება, extension-ის მსგავსად
    }
    return round((float)$plan['price_value'], 2);
}

/** ამ რეგისტრაციისთვის ხელმისაწვდომი (eligible) გეგმები */
function protEligiblePlans(PDO $pdo, array $reg)
{
    $out = [];
    try {
        $plans = $pdo->query("SELECT * FROM gw_protection_plans WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }

    $price = (float)($reg['product_price'] ?? 0);
    $activeTypes = [];
    try {
        $q = $pdo->prepare("SELECT plan_type_snapshot FROM gw_customer_protections
            WHERE registration_id = ? AND status = 'active' AND (ends_at IS NULL OR ends_at >= CURDATE())");
        $q->execute([(int)$reg['id']]);
        $activeTypes = array_map('strval', $q->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {}

    foreach ($plans as $p) {
        if ($p['min_price'] !== null && $price > 0 && $price < (float)$p['min_price']) continue;
        if ($p['max_price'] !== null && $price > 0 && $price > (float)$p['max_price']) continue;
        if (in_array((string)$p['plan_type'], $activeTypes, true)) continue;   // იგივე ტიპი უკვე აქტიურია
        $p['calc_price'] = protPlanPrice($p, $price);
        if ($p['calc_price'] <= 0) continue;
        $out[] = $p;
    }
    return $out;
}

/**
 * BOG callback-ის ჰენდლერი „PROT-" order-ებისთვის.
 * იდემპოტენტური: აქტივაცია ხდება მხოლოდ ერთხელ (atomic UPDATE ... WHERE status='pending').
 * თანხის შემოწმება: BOG-ის დაბრუნებული თანხა უნდა ემთხვეოდეს ჩვენს ჩანაწერს.
 */
function protectionHandleBogCallback(PDO $pdo, $bogOrderId, $externalOrderId, $callbackStatus, $rawBody = '')
{
    $q = $pdo->prepare("SELECT * FROM gw_payments WHERE external_order_id = ? LIMIT 1");
    $q->execute([$externalOrderId]);
    $pay = $q->fetch(PDO::FETCH_ASSOC);
    if (!$pay) { error_log('PROT callback: payment not found ' . $externalOrderId); return; }
    if ($pay['status'] !== 'pending') { error_log('PROT callback: already processed #' . $pay['id']); return; }

    /* სტატუსის გადამოწმება BOG-თან (callback-ს მარტო არ ვენდობით) */
    $verified = $callbackStatus;
    $bogAmount = null;
    try {
        $od = BogPay::getOrderStatus($bogOrderId ?: $externalOrderId);
        $verified = $od['order_status'] ?? $od['status'] ?? $callbackStatus;
        if (is_array($verified)) $verified = $verified['key'] ?? $verified['value'] ?? $callbackStatus;
        foreach ([$od['purchase_units']['request_amount'] ?? null,
                  $od['purchase_units'][0]['amount']['value'] ?? null,
                  $od['purchase_units']['transfer_amount'] ?? null,
                  $od['amount'] ?? null] as $cand) {
            if ($cand !== null && $cand !== '') { $bogAmount = (float)$cand; break; }
        }
    } catch (Throwable $e) {
        error_log('PROT callback getOrderStatus failed: ' . $e->getMessage());
    }
    $verified = strtoupper((string)$verified);

    if (in_array($verified, ['REJECTED', 'FAILED', 'EXPIRED', 'CANCELLED'], true)) {
        $pdo->prepare("UPDATE gw_payments SET status='failed', callback_data=?, completed_at=NOW() WHERE id=? AND status='pending'")
            ->execute([$rawBody, $pay['id']]);
        error_log('PROT callback: payment failed #' . $pay['id']);
        return;
    }
    if (!in_array($verified, ['COMPLETED', 'SUCCESS', 'APPROVED'], true)) {
        error_log('PROT callback: still pending, status=' . $verified);
        return;
    }

    /* თანხის შემოწმება — შეუსაბამობისას არ ვააქტიურებთ */
    if ($bogAmount !== null && abs($bogAmount - (float)$pay['amount']) > 0.01) {
        error_log('PROT callback: AMOUNT MISMATCH payment #' . $pay['id'] . ' ours=' . $pay['amount'] . ' bog=' . $bogAmount);
        try { auditLog($pdo, 'payment', (int)$pay['id'], 'protection_amount_mismatch', 'amount', (string)$pay['amount'], (string)$bogAmount); } catch (Throwable $e) {}
        return;
    }

    /* გეგმის ამოღება notes-იდან (plan_id შენახულია ყიდვისას) */
    $meta = protJson($pay['notes']);
    $planId = (int)($meta['plan_id'] ?? 0);
    $plan = null;
    if ($planId) {
        $pq = $pdo->prepare("SELECT * FROM gw_protection_plans WHERE id = ?");
        $pq->execute([$planId]);
        $plan = $pq->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$plan) { error_log('PROT callback: plan not found for payment #' . $pay['id']); return; }

    $rq = $pdo->prepare("SELECT id, phone FROM gw_registrations WHERE id = ?");
    $rq->execute([(int)$pay['registration_id']]);
    $reg = $rq->fetch(PDO::FETCH_ASSOC);

    /* ── ატომური აქტივაცია: pending → completed. ერთხელ გაივლის. ── */
    $lock = $pdo->prepare("UPDATE gw_payments SET status='completed', bog_order_id=?, completed_at=NOW(), callback_data=? WHERE id=? AND status='pending'");
    $lock->execute([$bogOrderId, $rawBody, $pay['id']]);
    if ($lock->rowCount() < 1) { error_log('PROT callback: race, already completed #' . $pay['id']); return; }

    $months = max(1, (int)($plan['duration_months'] ?: 12));
    $starts = date('Y-m-d');
    $ends   = date('Y-m-d', strtotime($starts . " +{$months} months"));
    $token  = bin2hex(random_bytes(24));

    try {
        $pdo->prepare("INSERT INTO gw_customer_protections
            (registration_id, customer_phone, plan_id, plan_name_snapshot, plan_type_snapshot, coverage_snapshot,
             terms_snapshot, terms_version_snapshot, price_paid, starts_at, ends_at, status, payment_id, public_token)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'active', ?, ?)")
            ->execute([(int)$pay['registration_id'], $reg['phone'] ?? null, (int)$plan['id'],
                       $plan['name'], $plan['plan_type'], $plan['coverage_json'],
                       $plan['terms'], (int)$plan['terms_version'], (float)$pay['amount'],
                       $starts, $ends, (int)$pay['id'], $token]);
        $protId = (int)$pdo->lastInsertId();
        try { auditLog($pdo, 'protection', $protId, 'activated', 'status', null, 'active'); } catch (Throwable $e) {}

        if (!empty($reg['phone']) && function_exists('queueSmsNow')) {
            $link = SITE_URL . '/my_protection.php?t=' . $token;
            $smsText = 'გაჯეტი: დაცვის პაკეტი "' . $plan['name'] . '" გააქტიურდა '
                     . date('d.m.Y', strtotime($ends)) . '-მდე. დეტალები: ' . $link;
            queueSmsNow('protection', $reg['phone'], $smsText,
                'protection:' . $protId . ':activated', 'protection', $protId);
        }
        error_log('PROT: protection #' . $protId . ' activated for reg #' . $pay['registration_id']);
    } catch (Throwable $e) {
        error_log('PROT callback: activation insert failed: ' . $e->getMessage());
    }
}

/** დაცვის ჩატვირთვა public token-ით (+ ნივთის მონაცემები) */
function protLoadByToken(PDO $pdo, $token)
{
    $t = preg_replace('/[^a-f0-9]/i', '', (string)$token);
    if (strlen($t) < 32) return null;
    try {
        $q = $pdo->prepare("SELECT p.*, r.product_name, r.serial_number, r.short_code, r.first_name, r.last_name,
                r.phone AS reg_phone, r.warranty_end_date
            FROM gw_customer_protections p
            LEFT JOIN gw_registrations r ON r.id = p.registration_id
            WHERE p.public_token = ? LIMIT 1");
        $q->execute([$t]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/** არის თუ არა დაცვა ამ მომენტში მოქმედი */
function protIsLive(array $prot)
{
    if (($prot['status'] ?? '') !== 'active') return false;
    $today = date('Y-m-d');
    if (!empty($prot['starts_at']) && $prot['starts_at'] > $today) return false;
    if (!empty($prot['ends_at']) && $prot['ends_at'] < $today) return false;
    return true;
}

/**
 * claim-ის დაშვების მკაცრი შემოწმება.
 * აბრუნებს [ok(bool), errMsg(?string)]
 */
function protClaimGuard(PDO $pdo, array $prot, $incidentType, $incidentAt)
{
    if (!protIsLive($prot)) return [false, 'დაცვის პაკეტი აქტიური არ არის (სტატუსი ან ვადა).'];

    $cov = protJson($prot['coverage_snapshot']);
    if ($cov && !in_array((string)$incidentType, array_map('strval', $cov), true)) {
        return [false, 'ეს შემთხვევა თქვენი პაკეტით არ იფარება.'];
    }
    if (!isset(protIncidentTypes()[$incidentType])) return [false, 'აირჩიეთ შემთხვევის ტიპი.'];

    if ($incidentAt) {
        $d = date('Y-m-d', strtotime($incidentAt));
        if ($d > date('Y-m-d')) return [false, 'თარიღი მომავალში ვერ იქნება.'];
        if (!empty($prot['starts_at']) && $d < $prot['starts_at']) return [false, 'შემთხვევა დაცვის დაწყებამდეა.'];
        if (!empty($prot['ends_at']) && $d > $prot['ends_at']) return [false, 'შემთხვევა დაცვის ვადის შემდეგაა.'];
    }

    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM gw_protection_claims WHERE protection_id = ? AND status IN ('submitted','approved','in_service')");
        $q->execute([(int)$prot['id']]);
        if ((int)$q->fetchColumn() > 0) return [false, 'ამ პაკეტზე უკვე გაქვთ მიმდინარე განაცხადი.'];
    } catch (Throwable $e) {}

    return [true, null];
}

/** ავტომატური flag-ები ადმინის სამსჯავროსთვის (არ ბლოკავს, მხოლოდ აღნიშნავს) */
function protReviewFlags(array $prot, $incidentAt)
{
    $f = [];
    if ($incidentAt && !empty($prot['starts_at'])) {
        $days = (int)floor((strtotime($incidentAt) - strtotime($prot['starts_at'])) / 86400);
        if ($days <= 7) $f[] = 'ინციდენტი დაცვის დაწყებიდან ' . max(0, $days) . ' დღეში';
    }
    if ((float)($prot['price_paid'] ?? 0) <= 0) $f[] = 'გადახდის თანხა 0';
    return $f;
}

/** ახალი სერვის-ქეისის ნომერი — არსებულ ნუმერაციას მიჰყვება */
function protNextCaseNumber(PDO $pdo)
{
    try {
        $last = $pdo->query("SELECT case_number FROM gw_service_cases WHERE case_number IS NOT NULL AND case_number <> '' ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($last && preg_match('/^(.*?)(\d+)$/u', (string)$last, $m)) {
            return $m[1] . str_pad((string)((int)$m[2] + 1), strlen($m[2]), '0', STR_PAD_LEFT);
        }
    } catch (Throwable $e) {}
    return 'SC' . date('ymd') . random_int(100, 999);
}

/** სერვის-ქეისის ღირებულების სინქრონიზაცია claim-ზე */
function protSyncRecordedCost(PDO $pdo, $serviceCaseId, $cost = null)
{
    try {
        if ($cost === null && function_exists('svPartsCost')) $cost = svPartsCost($pdo, (int)$serviceCaseId);
        if ($cost === null) return;
        $pdo->prepare("UPDATE gw_protection_claims SET recorded_cost = ? WHERE service_case_id = ?")
            ->execute([round((float)$cost, 2), (int)$serviceCaseId]);
    } catch (Throwable $e) { error_log('protSyncRecordedCost: ' . $e->getMessage()); }
}
