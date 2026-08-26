<?php
/** my_protection.php?t=TOKEN — PUBLIC: აქტიური დაცვა + განაცხადის ღილაკი (TASK 3). */
require_once 'includes/config.php';
require_once 'includes/protection.php';

$token = preg_replace('/[^a-f0-9]/i', '', $_GET['t'] ?? '');
$prot  = protLoadByToken($pdo, $token);
$TYPES = protIncidentTypes();

$claims = [];
if ($prot) {
    try {
        $q = $pdo->prepare("SELECT c.*, s.case_number, s.status AS case_status
            FROM gw_protection_claims c LEFT JOIN gw_service_cases s ON s.id = c.service_case_id
            WHERE c.protection_id = ? ORDER BY c.id DESC");
        $q->execute([(int)$prot['id']]);
        $claims = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
$live = $prot ? protIsLive($prot) : false;
$hasOpen = false;
foreach ($claims as $c) { if (in_array($c['status'], ['submitted', 'approved', 'in_service'], true)) { $hasOpen = true; break; } }
function mp_e($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ჩემი დაცვა — გაჯეტი</title>
<style>
  body{font-family:-apple-system,system-ui,"Noto Sans Georgian",sans-serif;background:#F6F8FC;color:#0F172A;margin:0;padding:18px;font-size:15px}
  .wrap{max-width:520px;margin:20px auto}
  .card{background:#fff;border:1px solid #E7EBF1;border-radius:16px;padding:22px;margin-bottom:14px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
  h1{font-size:20px;margin:0 0 4px}
  .sub{color:#64748B;font-size:13px}
  .badge{display:inline-block;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700}
  .b-on{background:#ECFDF5;color:#047857}.b-off{background:#FEF2F2;color:#991B1B}
  .b-mid{background:#FEF3C7;color:#92400E}
  .item{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px dashed #E7EBF1;font-size:14px}
  .item span:first-child{color:#64748B}
  .cov span{display:inline-block;background:#EEF0FF;color:#4338CA;border-radius:999px;padding:3px 10px;margin:2px 4px 2px 0;font-size:12px;font-weight:600}
  .terms{font-size:12px;color:#64748B;background:#F8FAFC;border-radius:9px;padding:11px;margin-top:10px;max-height:150px;overflow:auto;white-space:pre-wrap}
  a.btn{display:block;text-align:center;text-decoration:none;border-radius:12px;padding:14px;font-size:15px;font-weight:700;background:#4F46E5;color:#fff;margin-top:14px}
  a.btn.gray{background:#fff;color:#64748B;border:1px solid #E7EBF1}
  .state{text-align:center;padding:26px 6px}.state .big{font-size:44px;margin-bottom:10px}
  .cl{border:1px solid #EEF1F6;border-radius:11px;padding:12px;margin-top:10px;font-size:13.5px}
  .brand{text-align:center;color:#94A3B8;font-size:12px;margin-top:18px}
</style></head><body><div class="wrap">
<?php if (!$prot): ?>
  <div class="card"><div class="state"><div class="big">❓</div><h1>ვერ მოიძებნა</h1>
    <p class="sub">გადაამოწმეთ SMS-ის ბმული ან დაგვიკავშირდით.</p></div></div>
<?php else: ?>
  <div class="card">
    <h1>🛡 <?= mp_e($prot['plan_name_snapshot'] ?: 'დაცვის პაკეტი') ?></h1>
    <div class="sub" style="margin-bottom:12px">
      <span class="badge <?= $live ? 'b-on' : 'b-off' ?>"><?= $live ? '✓ აქტიური' : mp_e(protStatusLabel($prot['status'])) ?></span>
    </div>
    <div class="item"><span>ნივთი</span><b><?= mp_e($prot['product_name'] ?: '—') ?></b></div>
    <?php if (!empty($prot['serial_number'])): ?><div class="item"><span>კოდი</span><b><?= mp_e($prot['serial_number']) ?></b></div><?php endif; ?>
    <div class="item"><span>მფლობელი</span><b><?= mp_e(trim(($prot['first_name'] ?? '') . ' ' . ($prot['last_name'] ?? ''))) ?></b></div>
    <div class="item"><span>მოქმედებს</span><b><?= mp_e($prot['starts_at']) ?> — <?= mp_e($prot['ends_at']) ?></b></div>
    <div class="item" style="border-bottom:0"><span>გადახდილი</span><b><?= number_format((float)$prot['price_paid'], 2) ?> ₾</b></div>

    <?php $cov = protJson($prot['coverage_snapshot']); if ($cov): ?>
      <div class="cov" style="margin-top:12px"><div class="sub" style="margin-bottom:5px">ფარავს:</div>
        <?php foreach ($cov as $c): ?><span><?= mp_e($TYPES[$c] ?? $c) ?></span><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if (!empty($prot['terms_snapshot'])): ?><div class="terms"><?= mp_e($prot['terms_snapshot']) ?></div><?php endif; ?>

    <?php if ($live && !$hasOpen): ?>
      <a class="btn" href="claim_submit.php?t=<?= mp_e($token) ?>">📋 შემთხვევის განაცხადი</a>
    <?php elseif ($hasOpen): ?>
      <a class="btn gray" href="#claims">განაცხადი უკვე მიმდინარეობს ↓</a>
    <?php endif; ?>
  </div>

  <?php if ($claims): ?>
  <div class="card" id="claims">
    <h1 style="font-size:17px">განაცხადები</h1>
    <?php foreach ($claims as $c): ?>
      <div class="cl">
        <b><?= mp_e($TYPES[$c['incident_type']] ?? $c['incident_type']) ?></b>
        <span class="badge <?= $c['status'] === 'rejected' ? 'b-off' : ($c['status'] === 'resolved' ? 'b-on' : 'b-mid') ?>" style="float:right"><?= mp_e(protClaimStatusLabel($c['status'])) ?></span>
        <div class="sub" style="margin-top:6px"><?= mp_e(substr((string)$c['created_at'], 0, 16)) ?><?= $c['case_number'] ? ' · სერვისი #' . mp_e($c['case_number']) : '' ?></div>
        <?php if (!empty($c['description'])): ?><div style="margin-top:6px;color:#374151"><?= mp_e(mb_substr($c['description'], 0, 200)) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
  <div class="brand">გაჯეტი · დაცვის პროგრამა</div>
</div></body></html>
