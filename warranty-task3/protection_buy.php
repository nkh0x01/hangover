<?php
/**
 * protection_buy.php?slug=XXXX — PUBLIC: დაცვის პაკეტის შეძენა BOG-ით (TASK 3).
 * external_order_id იწყება "PROT-"-ით → callback მას ცალკე ჰენდლერზე მიმართავს.
 */
require_once 'includes/config.php';
require_once 'includes/bog_pay.php';
require_once 'includes/protection.php';

$slug = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['slug'] ?? $_POST['slug'] ?? '');
$reg = null; $err = null; $notice = null;

if ($slug !== '') {
    try {
        $q = $pdo->prepare("SELECT * FROM gw_registrations WHERE public_slug = ? AND deleted_at IS NULL LIMIT 1");
        $q->execute([$slug]);
        $reg = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { $err = 'ბაზის შეცდომა'; }
}
if (!$reg) { $err = $err ?: 'საგარანტიო ვერ მოიძებნა — გადაამოწმეთ ბმული.'; }

if (($_GET['payment'] ?? '') === 'fail') { $notice = '❌ გადახდა არ დასრულდა. სცადეთ ხელახლა.'; }
if (($_GET['payment'] ?? '') === 'success') { $notice = '✅ გადახდა მიღებულია — პაკეტი გააქტიურდება წამებში და SMS მოგივათ.'; }

$plans = $reg ? protEligiblePlans($pdo, $reg) : [];

if (!$err && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plan_id'])) {
    $planId = (int)$_POST['plan_id'];
    $plan = null;
    foreach ($plans as $p) { if ((int)$p['id'] === $planId) { $plan = $p; break; } }
    if (!$plan) {
        $err = 'პაკეტი მიუწვდომელია.';
    } else {
        $price = (float)$plan['calc_price'];
        try {
            $ext = 'PROT-' . (int)$reg['id'] . '-' . time() . '-' . bin2hex(random_bytes(3));
            $pdo->prepare("INSERT INTO gw_payments
                (registration_id, external_order_id, amount, months, reason, notes, status, created_at)
                VALUES (?,?,?,?,?,?,'pending',NOW())")
                ->execute([(int)$reg['id'], $ext, $price, (int)$plan['duration_months'], 'protection',
                           json_encode(['plan_id' => (int)$plan['id'], 'plan' => $plan['name']], JSON_UNESCAPED_UNICODE)]);

            $bog = BogPay::createOrder([
                'external_order_id' => $ext,
                'amount'            => $price,
                'description'       => 'დაცვის პაკეტი: ' . $plan['name'] . ' — #' . $reg['short_code'],
                'reg_id'            => (int)$reg['id'],
                'success_url'       => SITE_URL . '/protection_buy.php?slug=' . urlencode($slug) . '&payment=success',
                'fail_url'          => SITE_URL . '/protection_buy.php?slug=' . urlencode($slug) . '&payment=fail',
            ]);
            header('Location: ' . $bog['redirect_url']);
            exit;
        } catch (Throwable $e) {
            error_log('PROT buy error: ' . $e->getMessage());
            $err = 'გადახდის სისტემასთან კავშირის შეცდომა. სცადეთ მოგვიანებით.';
        }
    }
}
$csrf = function_exists('csrf_field') ? csrf_field() : '';
$TYPES = protIncidentTypes();
function pb_e($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>დაცვის პაკეტი — გაჯეტი</title>
<style>
  body{font-family:-apple-system,system-ui,"Noto Sans Georgian",sans-serif;background:#F6F8FC;color:#0F172A;margin:0;padding:18px;font-size:15px}
  .wrap{max-width:520px;margin:20px auto}
  h1{font-size:21px;margin:0 0 6px}
  .sub{color:#64748B;font-size:13.5px;margin-bottom:18px}
  .card{background:#fff;border:1px solid #E7EBF1;border-radius:16px;padding:20px;margin-bottom:14px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
  .plan{border:2px solid #E7EBF1;border-radius:14px;padding:16px;margin-bottom:12px}
  .plan h3{margin:0 0 4px;font-size:17px}
  .price{font-size:26px;font-weight:800;color:#4F46E5;margin:8px 0}
  .cov{font-size:13px;color:#374151;margin:8px 0}
  .cov span{display:inline-block;background:#EEF0FF;color:#4338CA;border-radius:999px;padding:3px 10px;margin:2px 4px 2px 0;font-size:12px;font-weight:600}
  .terms{font-size:12px;color:#64748B;background:#F8FAFC;border-radius:9px;padding:10px;margin-top:8px;max-height:120px;overflow:auto;white-space:pre-wrap}
  button{width:100%;border:0;border-radius:11px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;background:#4F46E5;color:#fff;font-family:inherit;margin-top:10px}
  .item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #E7EBF1;font-size:14px}
  .item span:first-child{color:#64748B}
  .err{background:#FEF2F2;color:#991B1B;padding:13px;border-radius:11px;margin-bottom:14px}
  .note{background:#ECFDF5;border:1px solid #A7F3D0;color:#047857;padding:13px;border-radius:11px;margin-bottom:14px;font-weight:600}
  .brand{text-align:center;color:#94A3B8;font-size:12px;margin-top:18px}
</style></head><body><div class="wrap">
<?php if ($notice): ?><div class="note"><?= pb_e($notice) ?></div><?php endif; ?>
<?php if ($err): ?>
  <div class="card"><div class="err">❌ <?= pb_e($err) ?></div></div>
<?php else: ?>
  <h1>🛡 დაცვის პაკეტი</h1>
  <div class="sub">დაიცავი შენი ნივთი მოულოდნელი დაზიანებისგან</div>
  <div class="card">
    <div class="item"><span>ნივთი</span><b><?= pb_e($reg['product_name'] ?: '—') ?></b></div>
    <div class="item"><span>საგარანტიო №</span><b>#<?= pb_e($reg['short_code']) ?></b></div>
    <?php if ((float)$reg['product_price'] > 0): ?>
    <div class="item"><span>ნივთის ღირებულება</span><b><?= number_format((float)$reg['product_price'], 2) ?> ₾</b></div>
    <?php endif; ?>
    <div class="item" style="border-bottom:0"><span>მფლობელი</span><b><?= pb_e(trim($reg['first_name'] . ' ' . $reg['last_name'])) ?></b></div>
  </div>

  <?php if (!$plans): ?>
    <div class="card" style="text-align:center;color:#64748B">ამ ნივთისთვის ხელმისაწვდომი პაკეტი ამჟამად არ არის.<br><small>(შესაძლოა უკვე გაქვთ აქტიური დაცვა)</small></div>
  <?php else: foreach ($plans as $p): ?>
    <div class="card plan">
      <h3><?= pb_e($p['name']) ?></h3>
      <div class="sub" style="margin:0"><?= (int)$p['duration_months'] ?> თვე</div>
      <div class="price"><?= number_format((float)$p['calc_price'], 2) ?> ₾</div>
      <?php $cov = protJson($p['coverage_json']); if ($cov): ?>
        <div class="cov">ფარავს:<br><?php foreach ($cov as $c): ?><span><?= pb_e($TYPES[$c] ?? $c) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
      <?php $ex = protJson($p['exclusions_json']); if ($ex): ?>
        <div class="cov" style="color:#991B1B">არ ფარავს: <?= pb_e(implode(' · ', $ex)) ?></div>
      <?php endif; ?>
      <?php if (!empty($p['terms'])): ?><div class="terms"><?= pb_e($p['terms']) ?></div><?php endif; ?>
      <form method="POST">
        <?= $csrf ?>
        <input type="hidden" name="slug" value="<?= pb_e($slug) ?>">
        <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
        <button onclick="return confirm('გადახდა <?= number_format((float)$p['calc_price'], 2) ?> ₾. გავაგრძელოთ?')">💳 შეძენა — <?= number_format((float)$p['calc_price'], 2) ?> ₾</button>
      </form>
    </div>
  <?php endforeach; endif; ?>
<?php endif; ?>
  <div class="brand">გაჯეტი · უსაფრთხო გადახდა Bank of Georgia-ს მეშვეობით</div>
</div></body></html>
