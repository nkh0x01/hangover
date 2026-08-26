<?php
/**
 * estimate_approve.php?t=TOKEN — PUBLIC: კლიენტი ადასტურებს/უარყოფს ხარჯთაღრიცხვას (TASK 2).
 * Auth = მაღალ-ენტროპიული token (48 hex). login არ სჭირდება.
 */
require_once 'includes/config.php';
require_once 'includes/service_v2.php';

$token = preg_replace('/[^a-f0-9]/i', '', $_GET['t'] ?? $_POST['t'] ?? '');
$est = null; $case = null; $state = 'notfound'; $msg = null;

if (strlen($token) >= 32) {
    $q = $pdo->prepare("SELECT e.*, c.case_number, c.product_name, c.problem_description, c.customer_name, c.status AS case_status
        FROM gw_service_estimates e JOIN gw_service_cases c ON c.id = e.service_case_id
        WHERE e.approval_token = ? LIMIT 1");
    $q->execute([$token]);
    $est = $q->fetch(PDO::FETCH_ASSOC);
    if ($est) {
        if ($est['status'] === 'approved') $state = 'already_approved';
        elseif ($est['status'] === 'rejected') $state = 'already_rejected';
        elseif ($est['status'] === 'superseded') $state = 'superseded';
        elseif ($est['expires_at'] && strtotime($est['expires_at']) < time()) $state = 'expired';
        elseif ($est['status'] === 'awaiting_customer') $state = 'open';
        else $state = 'closed';
    }
}

if ($state === 'open' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $caseId = (int)$est['service_case_id'];
    $act = $_POST['decision'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($act === 'approve') {
        $u = $pdo->prepare("UPDATE gw_service_estimates SET status='approved', decided_at=NOW() WHERE id=? AND status='awaiting_customer'");
        $u->execute([(int)$est['id']]);
        if ($u->rowCount() > 0) {
            svSetStatus($pdo, $caseId, 'in_repair', null, 'კლიენტმა დაადასტურა ხარჯთაღრიცხვა v' . $est['version'] . ' (' . number_format((float)$est['total'], 2) . '₾)');
            try { $pdo->prepare("UPDATE gw_service_cases SET repair_started_at=COALESCE(repair_started_at,NOW()), payment_status=COALESCE(payment_status,'pending') WHERE id=?")->execute([$caseId]); } catch (Throwable $e) {}
            svAddActivity($pdo, $caseId, 'worklog', 'ხარჯთაღრიცხვა დადასტურდა კლიენტის მიერ', ['estimate_id' => (int)$est['id'], 'ip' => $ip], null);
            try { auditLog($pdo, 'service_case', $caseId, 'estimate_approved', 'estimate', 'v' . $est['version'], $ip); } catch (Throwable $e) {}
        }
        $state = 'just_approved';
    } elseif ($act === 'reject') {
        $u = $pdo->prepare("UPDATE gw_service_estimates SET status='rejected', decided_at=NOW() WHERE id=? AND status='awaiting_customer'");
        $u->execute([(int)$est['id']]);
        if ($u->rowCount() > 0) {
            svSetStatus($pdo, $caseId, 'in_diagnostic', null, 'კლიენტმა უარყო ხარჯთაღრიცხვა v' . $est['version']);
            svAddActivity($pdo, $caseId, 'worklog', 'ხარჯთაღრიცხვა უარყოფილია კლიენტის მიერ', ['estimate_id' => (int)$est['id'], 'ip' => $ip], null);
            try { auditLog($pdo, 'service_case', $caseId, 'estimate_rejected', 'estimate', 'v' . $est['version'], $ip); } catch (Throwable $e) {}
        }
        $state = 'just_rejected';
    }
}

function ee($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ხარჯთაღრიცხვის დადასტურება — გაჯეტი</title>
<style>
  body{font-family:-apple-system,system-ui,"Noto Sans Georgian",sans-serif;background:#F6F8FC;color:#0F172A;margin:0;padding:20px;font-size:15px}
  .card{max-width:480px;margin:24px auto;background:#fff;border:1px solid #E7EBF1;border-radius:16px;padding:26px;box-shadow:0 10px 30px rgba(15,23,42,.06)}
  h1{font-size:19px;margin:0 0 4px}
  .sub{color:#64748B;font-size:13px;margin-bottom:18px}
  .row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px dashed #E7EBF1;font-size:14px}
  .row span:first-child{color:#64748B}
  .row.total{font-size:18px;font-weight:800;border-bottom:0;padding-top:14px}
  .note{background:#F8FAFC;border:1px solid #EEF1F6;border-radius:10px;padding:12px;font-size:13.5px;margin:14px 0;color:#374151}
  .btns{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:18px}
  button{border:0;border-radius:11px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}
  .ok{background:#059669;color:#fff}
  .no{background:#fff;color:#DC2626;border:2px solid #FECACA}
  .state{text-align:center;padding:26px 6px}
  .state .big{font-size:44px;margin-bottom:10px}
  .brand{text-align:center;color:#94A3B8;font-size:12px;margin-top:16px}
</style></head><body>
<div class="card">
<?php if ($state === 'open'): ?>
  <h1>შეკეთების ხარჯთაღრიცხვა</h1>
  <div class="sub">საქმე #<?= ee($est['case_number']) ?> · <?= ee($est['customer_name']) ?></div>
  <div class="row"><span>ნივთი</span><b><?= ee($est['product_name']) ?></b></div>
  <?php if (!empty($est['problem_description'])): ?>
  <div class="row"><span>პრობლემა</span><span style="max-width:60%;text-align:right"><?= ee(mb_substr($est['problem_description'], 0, 120)) ?></span></div>
  <?php endif; ?>
  <div class="row"><span>სამუშაო</span><span><?= number_format((float)$est['labor'], 2) ?> ₾</span></div>
  <div class="row"><span>ნაწილები</span><span><?= number_format((float)$est['parts_amount'], 2) ?> ₾</span></div>
  <?php if ((float)$est['other'] > 0): ?><div class="row"><span>სხვა</span><span><?= number_format((float)$est['other'], 2) ?> ₾</span></div><?php endif; ?>
  <div class="row total"><span>ჯამი</span><span><?= number_format((float)$est['total'], 2) ?> ₾</span></div>
  <?php if (!empty($est['notes'])): ?><div class="note">🔧 <?= nl2br(ee($est['notes'])) ?></div><?php endif; ?>
  <?php if ($est['expires_at']): ?><div class="sub" style="margin:8px 0 0">მოქმედია: <?= ee(substr($est['expires_at'], 0, 16)) ?>-მდე</div><?php endif; ?>
  <form method="POST" class="btns">
    <input type="hidden" name="t" value="<?= ee($token) ?>">
    <button class="ok" name="decision" value="approve" onclick="return confirm('დაადასტურებთ შეკეთებას <?= number_format((float)$est['total'], 2) ?> ₾-ად?')">✓ ვეთანხმები</button>
    <button class="no" name="decision" value="reject" onclick="return confirm('ნამდვილად უარყოფთ?')">✗ არ ვეთანხმები</button>
  </form>
<?php elseif ($state === 'just_approved' || $state === 'already_approved'): ?>
  <div class="state"><div class="big">✅</div><h1>დადასტურებულია</h1>
  <p style="color:#64748B">მადლობა! შეკეთებას ვიწყებთ — დასრულებისას SMS-ით შეგატყობინებთ.</p></div>
<?php elseif ($state === 'just_rejected' || $state === 'already_rejected'): ?>
  <div class="state"><div class="big">📋</div><h1>უარყოფილია</h1>
  <p style="color:#64748B">გავითვალისწინეთ. სერვის ცენტრი დაგიკავშირდებათ შემდეგი ნაბიჯებისთვის.</p></div>
<?php elseif ($state === 'expired'): ?>
  <div class="state"><div class="big">⌛</div><h1>ლინკს ვადა გაუვიდა</h1>
  <p style="color:#64748B">გთხოვთ დაუკავშირდეთ სერვის ცენტრს — ახალ ლინკს გამოგიგზავნით.</p></div>
<?php elseif ($state === 'superseded'): ?>
  <div class="state"><div class="big">🔄</div><h1>ეს ვერსია მოძველდა</h1>
  <p style="color:#64748B">გამოგზავნილია განახლებული ხარჯთაღრიცხვა — შეამოწმეთ ბოლო SMS.</p></div>
<?php else: ?>
  <div class="state"><div class="big">❓</div><h1>ლინკი ვერ მოიძებნა</h1>
  <p style="color:#64748B">გადაამოწმეთ SMS-ის ბმული ან დაუკავშირდით სერვის ცენტრს.</p></div>
<?php endif; ?>
  <div class="brand">გაჯეტი · სერვის ცენტრი</div>
</div>
</body></html>
