<?php
/**
 * service_v2_action.php — v2 პანელის POST მოქმედებები (TASK 2): guard-იანი სტატუს-გადასვლა, ხელოსნის მინიჭება.
 */
require_once 'includes/config.php';
require_once 'includes/service_v2.php';
requireLogin();
blockBranchUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: service_cases.php'); exit; }
if (function_exists('csrf_verify')) { csrf_verify(); }

$caseId = (int)($_POST['case_id'] ?? 0);
if (!$caseId) { header('Location: service_cases.php'); exit; }
$back = 'service_case.php?id=' . $caseId;
$act = $_POST['sv_action'] ?? '';

if ($act === 'status') {
    $to = (string)($_POST['to'] ?? '');
    [$ok, $err] = svSetStatus($pdo, $caseId, $to, currentUserId());
    if (!$ok) { header('Location: ' . $back . '&sv_err=' . urlencode($err)); exit; }
    try {
        if ($to === 'in_repair')     $pdo->prepare("UPDATE gw_service_cases SET repair_started_at=COALESCE(repair_started_at,NOW()) WHERE id=?")->execute([$caseId]);
        if ($to === 'quality_check') $pdo->prepare("UPDATE gw_service_cases SET repair_finished_at=COALESCE(repair_finished_at,NOW()) WHERE id=?")->execute([$caseId]);
        if ($to === 'in_diagnostic') $pdo->prepare("UPDATE gw_service_cases SET diagnostic_started_at=COALESCE(diagnostic_started_at,NOW()) WHERE id=?")->execute([$caseId]);
        if ($to === 'ready') {
            $pdo->prepare("UPDATE gw_service_cases SET ready_at=COALESCE(ready_at,NOW()) WHERE id=?")->execute([$caseId]);
            svSendReadySms($pdo, $caseId);
        }
    } catch (Throwable $e) { error_log('sv_action side-effect: ' . $e->getMessage()); }
    header('Location: ' . $back . '&ok=1'); exit;
}

if ($act === 'technician') {
    $tid = (int)($_POST['technician_id'] ?? 0);
    try {
        $pdo->prepare("UPDATE gw_service_cases SET technician_id=? WHERE id=?")->execute([$tid ?: null, $caseId]);
        $name = '—';
        if ($tid) {
            $u = $pdo->prepare("SELECT full_name FROM gw_users WHERE id=?");
            $u->execute([$tid]);
            $name = $u->fetchColumn() ?: ('#' . $tid);
        }
        svAddActivity($pdo, $caseId, 'assign', 'ხელოსანი: ' . $name, ['technician_id' => $tid], currentUserId());
        try { auditLog($pdo, 'service_case', $caseId, 'technician_assigned', 'technician_id', null, (string)$tid); } catch (Throwable $e) {}
    } catch (Throwable $e) {
        header('Location: ' . $back . '&sv_err=' . urlencode($e->getMessage())); exit;
    }
    header('Location: ' . $back . '&ok=1'); exit;
}

header('Location: ' . $back);
