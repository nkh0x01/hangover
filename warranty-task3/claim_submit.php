<?php
/** claim_submit.php?t=TOKEN — PUBLIC: შემთხვევის განაცხადი (TASK 3). ownership = public_token. */
require_once 'includes/config.php';
require_once 'includes/protection.php';

$token = preg_replace('/[^a-f0-9]/i', '', $_GET['t'] ?? $_POST['t'] ?? '');
$prot  = protLoadByToken($pdo, $token);
$TYPES = protIncidentTypes();
$err = null; $done = false;
$cov = $prot ? protJson($prot['coverage_snapshot']) : [];
$allowed = $cov ? array_intersect_key($TYPES, array_flip(array_map('strval', $cov))) : $TYPES;

if ($prot && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = (string)($_POST['incident_type'] ?? '');
    $at   = trim($_POST['incident_at'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    [$ok, $gerr] = protClaimGuard($pdo, $prot, $type, $at ?: null);
    if (!$ok) { $err = $gerr; }
    elseif (mb_strlen($desc) < 10) { $err = 'აღწერეთ შემთხვევა უფრო დეტალურად (მინ. 10 სიმბოლო).'; }
    else {
        try {
            $flags = protReviewFlags($prot, $at ?: null);
            $pdo->prepare("INSERT INTO gw_protection_claims
                (protection_id, incident_type, incident_at, description, status, review_flags_json)
                VALUES (?,?,?,?, 'submitted', ?)")
                ->execute([(int)$prot['id'], $type, $at ?: null, $desc,
                           $flags ? json_encode($flags, JSON_UNESCAPED_UNICODE) : null]);
            $claimId = (int)$pdo->lastInsertId();
            try { auditLog($pdo, 'protection_claim', $claimId, 'submitted', null, null, $type); } catch (Throwable $e) {}

            if (!empty($prot['reg_phone']) && function_exists('queueSmsNow')) {
                queueSmsNow('claim', $prot['reg_phone'],
                    'გაჯეტი: თქვენი განაცხადი მიღებულია (#' . $claimId . '). განხილვის შედეგს შეგატყობინებთ.',
                    'claim:' . $claimId . ':submitted', 'protection_claim', $claimId);
            }
            $done = true;
        } catch (Throwable $e) { $err = 'შენახვა ვერ მოხერხდა. სცადეთ მოგვიანებით.'; error_log('claim submit: ' . $e->getMessage()); }
    }
}
$csrf = function_exists('csrf_field') ? csrf_field() : '';
function cs_e($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>შემთხვევის განაცხადი — გაჯეტი</title>
<style>
  body{font-family:-apple-system,system-ui,"Noto Sans Georgian",sans-serif;background:#F6F8FC;color:#0F172A;margin:0;padding:18px;font-size:15px}
  .wrap{max-width:520px;margin:20px auto}
  .card{background:#fff;border:1px solid #E7EBF1;border-radius:16px;padding:22px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
  h1{font-size:20px;margin:0 0 4px}
  .sub{color:#64748B;font-size:13px;margin-bottom:16px}
  label{display:block;font-size:13px;font-weight:600;margin:14px 0 5px}
  select,input,textarea{width:100%;padding:12px;border:1px solid #E7EBF1;border-radius:10px;font-size:15px;font-family:inherit;box-sizing:border-box}
  button{width:100%;border:0;border-radius:12px;padding:15px;font-size:15px;font-weight:700;cursor:pointer;background:#4F46E5;color:#fff;font-family:inherit;margin-top:18px}
  .err{background:#FEF2F2;color:#991B1B;padding:13px;border-radius:11px;margin-bottom:14px}
  .state{text-align:center;padding:26px 6px}.state .big{font-size:44px;margin-bottom:10px}
  .brand{text-align:center;color:#94A3B8;font-size:12px;margin-top:18px}
  a{color:#4F46E5}
</style></head><body><div class="wrap"><div class="card">
<?php if (!$prot): ?>
  <div class="state"><div class="big">❓</div><h1>ვერ მოიძებნა</h1><p class="sub">გადაამოწმეთ ბმული.</p></div>
<?php elseif ($done): ?>
  <div class="state"><div class="big">✅</div><h1>განაცხადი მიღებულია</h1>
    <p class="sub">განხილვის შედეგს SMS-ით შეგატყობინებთ.</p>
    <p><a href="my_protection.php?t=<?= cs_e($token) ?>">← ჩემი დაცვა</a></p></div>
<?php else: ?>
  <h1>📋 შემთხვევის განაცხადი</h1>
  <div class="sub"><?= cs_e($prot['plan_name_snapshot']) ?> · <?= cs_e($prot['product_name'] ?: '') ?></div>
  <?php if ($err): ?><div class="err">❌ <?= cs_e($err) ?></div><?php endif; ?>
  <?php if (!protIsLive($prot)): ?>
    <div class="err">ℹ️ დაცვის პაკეტი აქტიური არ არის — განაცხადის გაკეთება ვერ მოხერხდება.</div>
    <p><a href="my_protection.php?t=<?= cs_e($token) ?>">← უკან</a></p>
  <?php else: ?>
  <form method="POST">
    <?= $csrf ?>
    <input type="hidden" name="t" value="<?= cs_e($token) ?>">
    <label>რა მოხდა? *</label>
    <select name="incident_type" required>
      <option value="">— აირჩიეთ —</option>
      <?php foreach ($allowed as $k => $lb): ?>
        <option value="<?= cs_e($k) ?>" <?= ($_POST['incident_type'] ?? '') === $k ? 'selected' : '' ?>><?= cs_e($lb) ?></option>
      <?php endforeach; ?>
    </select>
    <label>როდის მოხდა?</label>
    <input type="date" name="incident_at" max="<?= date('Y-m-d') ?>" value="<?= cs_e($_POST['incident_at'] ?? date('Y-m-d')) ?>">
    <label>აღწერეთ დეტალურად *</label>
    <textarea name="description" rows="5" required placeholder="როგორ მოხდა, რა დაზიანდა, სად იმყოფებოდით…"><?= cs_e($_POST['description'] ?? '') ?></textarea>
    <button>განაცხადის გაგზავნა</button>
  </form>
  <p style="margin-top:14px"><a href="my_protection.php?t=<?= cs_e($token) ?>">← ჩემი დაცვა</a></p>
  <?php endif; ?>
<?php endif; ?>
</div><div class="brand">გაჯეტი · დაცვის პროგრამა</div></div></body></html>
