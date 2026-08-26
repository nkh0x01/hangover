<?php
/**
 * service_estimate.php?case_id=X — ხარჯთაღრიცხვის შედგენა/გაგზავნა (TASK 2).
 * ახალი ვერსია აუქმებს (supersede) ძველ ღიას; კლიენტს ეგზავნება დასადასტურებელი ლინკი SMS-ით.
 */
require_once 'includes/config.php';
require_once 'includes/service_v2.php';
requireLogin();
blockBranchUser();

$caseId = (int)($_GET['case_id'] ?? $_POST['case_id'] ?? 0);
if (!$caseId) { header('Location: service_cases.php'); exit; }
$q = $pdo->prepare("SELECT * FROM gw_service_cases WHERE id=?");
$q->execute([$caseId]);
$case = $q->fetch(PDO::FETCH_ASSOC);
if (!$case) { http_response_code(404); die('ქეისი ვერ მოიძებნა'); }

$err = null; $okMsg = null;
$partsCost = svPartsCost($pdo, $caseId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) { csrf_verify(); }
    $labor = round((float)str_replace(',', '.', $_POST['labor'] ?? '0'), 2);
    $parts = round((float)str_replace(',', '.', $_POST['parts_amount'] ?? '0'), 2);
    $other = round((float)str_replace(',', '.', $_POST['other'] ?? '0'), 2);
    $notes = trim($_POST['notes'] ?? '');
    $days  = max(1, min(30, (int)($_POST['expires_days'] ?? 7)));
    $total = round($labor + $parts + $other, 2);
    if ($total <= 0) {
        $err = 'ჯამი 0-ზე მეტი უნდა იყოს';
    } elseif (empty($case['customer_phone'])) {
        $err = 'ქეისს ტელეფონი არ აქვს — SMS ვერ გაიგზავნება';
    } else {
        try {
            $pdo->prepare("UPDATE gw_service_estimates SET status='superseded'
                WHERE service_case_id=? AND status IN ('draft','awaiting_customer')")->execute([$caseId]);
            $ver = (int)$pdo->query("SELECT COALESCE(MAX(version),0)+1 FROM gw_service_estimates WHERE service_case_id=" . $caseId)->fetchColumn();
            $token = bin2hex(random_bytes(24));
            $exp = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            $pdo->prepare("INSERT INTO gw_service_estimates
                (service_case_id, version, status, labor, parts_amount, other, total, notes, approval_token, expires_at, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$caseId, $ver, 'awaiting_customer', $labor, $parts, $other, $total, $notes ?: null, $token, $exp, currentUserId()]);
            $estId = (int)$pdo->lastInsertId();

            [$ok, $terr] = svSetStatus($pdo, $caseId, 'waiting_customer_approval', currentUserId(),
                'ხარჯთაღრიცხვა v' . $ver . ' გაეგზავნა კლიენტს (' . number_format($total, 2) . '₾)');
            if (!$ok) { $err = $terr; }
            else {
                $link = SITE_URL . '/estimate_approve.php?t=' . $token;
                queueSmsNow('estimate', $case['customer_phone'],
                    "გაჯეტი: შეკეთების ხარჯთაღრიცხვა #{$case['case_number']}: " . number_format($total, 2) . "₾. დაადასტურეთ: {$link}",
                    'service:' . $caseId . ':estimate:' . $estId, 'service_case', $caseId);
                svAddActivity($pdo, $caseId, 'diagnosis', 'ხარჯთაღრიცხვა v' . $ver . ': ' . number_format($total, 2) . '₾',
                    ['estimate_id' => $estId, 'labor' => $labor, 'parts' => $parts, 'other' => $other], currentUserId());
                try { auditLog($pdo, 'service_case', $caseId, 'estimate_sent', 'estimate', null, 'v' . $ver . ' / ' . $total); } catch (Throwable $e) {}
                header('Location: service_case.php?id=' . $caseId . '&ok=1'); exit;
            }
        } catch (Throwable $e) { $err = $e->getMessage(); }
    }
}

$history = [];
try {
    $h = $pdo->prepare("SELECT e.*, u.full_name FROM gw_service_estimates e LEFT JOIN gw_users u ON u.id=e.created_by
        WHERE e.service_case_id=? ORDER BY e.version DESC");
    $h->execute([$caseId]);
    $history = $h->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$csrf = function_exists('csrf_field') ? csrf_field() : '';
$estLabels = ['draft' => 'დრაფტი', 'awaiting_customer' => 'ელოდება კლიენტს', 'approved' => '✅ დადასტურებული',
              'rejected' => '❌ უარყოფილი', 'superseded' => 'ჩანაცვლებული'];
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>💰 ხარჯთაღრიცხვა — <?= htmlspecialchars($case['case_number'], ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.est-wrap{max-width:760px;margin:24px auto;padding:0 16px}
.est-card{background:#fff;border:1px solid #E7EBF1;border-radius:12px;padding:22px;margin-bottom:18px}
.est-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.est-total{font-size:22px;font-weight:800;margin:12px 0}
.est-err{background:#FEF2F2;color:#991B1B;padding:12px;border-radius:9px;margin-bottom:14px}
.est-tbl{width:100%;border-collapse:collapse;font-size:13px}
.est-tbl th,.est-tbl td{padding:8px 10px;border-bottom:1px solid #EEF1F6;text-align:left}
.btn-est{background:#4F46E5;color:#fff;border:0;border-radius:9px;padding:11px 20px;font-weight:600;cursor:pointer;font-size:14px}
</style></head><body>
<?php include 'includes/navbar.php'; ?>
<div class="est-wrap">
  <p><a href="service_case.php?id=<?= $caseId ?>">← ქეისზე დაბრუნება</a></p>
  <h1>💰 ხარჯთაღრიცხვა — #<?= htmlspecialchars($case['case_number'], ENT_QUOTES, 'UTF-8') ?></h1>
  <p style="color:#64748B"><?= htmlspecialchars($case['product_name'] ?? '', ENT_QUOTES, 'UTF-8') ?> ·
     <?= htmlspecialchars($case['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?> ·
     სტატუსი: <b><?= svLabel($case['status']) ?></b></p>

  <?php if ($err): ?><div class="est-err">❌ <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <div class="est-card">
    <h3 style="margin-bottom:14px">ახალი ვერსიის გაგზავნა</h3>
    <form method="POST">
      <?= $csrf ?>
      <input type="hidden" name="case_id" value="<?= $caseId ?>">
      <div class="est-row">
        <div><label>სამუშაო (₾)</label><input class="form-control" name="labor" id="labor" value="0" inputmode="decimal"></div>
        <div><label>ნაწილები (₾)</label><input class="form-control" name="parts_amount" id="parts" value="<?= number_format($partsCost, 2, '.', '') ?>" inputmode="decimal"></div>
        <div><label>სხვა (₾)</label><input class="form-control" name="other" id="other" value="0" inputmode="decimal"></div>
      </div>
      <div class="est-total">ჯამი: <span id="tot"><?= number_format($partsCost, 2) ?></span> ₾</div>
      <div style="margin-bottom:12px"><label>დიაგნოზი / შენიშვნა (კლიენტი ნახავს)</label>
        <textarea class="form-control" name="notes" rows="3"></textarea></div>
      <div style="margin-bottom:16px"><label>ლინკის ვადა (დღე)</label>
        <input class="form-control" name="expires_days" value="7" style="max-width:110px"></div>
      <button class="btn-est">📤 გაგზავნა კლიენტთან (SMS)</button>
    </form>
  </div>

  <div class="est-card">
    <h3 style="margin-bottom:10px">ვერსიების ისტორია</h3>
    <table class="est-tbl">
      <tr><th>v</th><th>ჯამი</th><th>სტატუსი</th><th>ავტორი</th><th>თარიღი</th><th>გადაწყდა</th></tr>
      <?php foreach ($history as $h): ?>
      <tr>
        <td>v<?= (int)$h['version'] ?></td>
        <td><?= number_format((float)$h['total'], 2) ?>₾</td>
        <td><?= $estLabels[$h['status']] ?? htmlspecialchars($h['status'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($h['full_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(substr((string)$h['created_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($h['decided_at'] ? substr($h['decided_at'], 0, 16) : '—', ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
      <?php endforeach; if (!$history): ?><tr><td colspan="6" style="color:#94A3B8">ჯერ არ არის</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<script>
(function(){
  function num(id){ var v = parseFloat(document.getElementById(id).value.replace(',', '.')); return isNaN(v) ? 0 : v; }
  function upd(){ document.getElementById('tot').textContent = (num('labor') + num('parts') + num('other')).toFixed(2); }
  ['labor','parts','other'].forEach(function(id){ document.getElementById(id).addEventListener('input', upd); });
  upd();
})();
</script>
<?php include 'includes/footer.php'; ?>
</body></html>
