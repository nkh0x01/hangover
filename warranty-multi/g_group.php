<?php
/**
 * g_group.php — PUBLIC: ჯგუფის ყველა საგარანტიო ბარათი ერთად.
 *   ?t=GROUP_TOKEN  — სრული ტოკენი (ელფოსტა, შიდა ლინკები)
 *   ?c=PUBLIC_SLUG  — მოკლე ფორმა SMS-ისთვის (g.php?c= -ის მსგავსად)
 */
require_once 'includes/config.php';
require_once 'includes/reggroup.php';

/* Rate limiting (s.php/g.php-ის იდენტური): მოკლე slug დაბალ-ენტროპიულია,
   ამიტომ მხოლოდ ვერ-პოვნები ითვლება — 50 შეცდომა/საათში ერთ IP-ზე. */
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = sys_get_temp_dir() . '/gg_' . md5($ip) . '.txt';
$now = time();
$attempts = [];
if (file_exists($rateFile)) {
    $rc = @file_get_contents($rateFile);
    $attempts = $rc ? array_filter(json_decode($rc, true) ?: [], function ($t) use ($now) {
        return $t > $now - 3600;
    }) : [];
}
if (count($attempts) >= 50) { http_response_code(429); die('Too many requests. Try again later.'); }

$group = null;
if (!empty($_GET['t'])) {
    $group = rgLoad($pdo, preg_replace('/[^a-f0-9]/i', '', $_GET['t']), 'token');
} elseif (!empty($_GET['c'])) {
    $group = rgLoad($pdo, preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['c']), 'slug');
}
if (!$group) {
    $attempts[] = $now;
    @file_put_contents($rateFile, json_encode($attempts));
    http_response_code(404);
    die('ბარათი ვერ მოიძებნა');
}

$items = $group['items'] ?? [];
$today = date('Y-m-d');
$unsigned = 0;
foreach ($items as $r) { if (empty($r['signed_at'])) { $unsigned++; } }

$branchName = '';
try {
    if (!empty($group['branch_id'])) {
        $b = $pdo->prepare("SELECT name FROM gw_branches WHERE id=?");
        $b->execute([(int)$group['branch_id']]);
        $branchName = (string)$b->fetchColumn();
    }
} catch (Throwable $e) {}

function gg_e($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>საგარანტიო ბარათები — გაჯეტი</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',-apple-system,"Noto Sans Georgian",sans-serif;background:#f4f4f7;color:#111;padding:16px}
.wrap{max-width:540px;margin:0 auto}
.card{background:#fff;border-radius:16px;padding:20px;margin-bottom:14px;box-shadow:0 6px 20px rgba(0,0,0,.06)}
h1{font-size:20px;margin-bottom:4px}
.sub{color:#666;font-size:13px}
.item{border:1px solid #eee;border-radius:12px;padding:14px;margin-top:11px;text-decoration:none;color:inherit;display:block}
.item:active{background:#fafafa}
.item b{font-size:15px}
.meta{color:#777;font-size:12.5px;margin-top:4px}
.row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed #eee;font-size:14px}
.row span:first-child{color:#777}
.b{display:inline-block;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700}
.ok{background:#ECFDF5;color:#047857}.warn{background:#FEF3C7;color:#92400E}.dead{background:#FEF2F2;color:#991B1B}
.note{background:#FEF3C7;border:1px solid #FDE68A;color:#92400E;padding:12px;border-radius:11px;margin-bottom:14px;font-size:13.5px}
a.btn{display:block;text-align:center;background:#111;color:#fff;padding:13px;border-radius:11px;text-decoration:none;font-weight:700;margin-top:12px}
.brand{text-align:center;color:#aaa;font-size:12px;margin-top:16px}
.arrow{float:right;color:#bbb}
</style></head><body><div class="wrap">

<?php if ($unsigned): ?>
  <div class="note">✍️ <?= $unsigned ?> ნივთზე ხელმოწერა ჯერ არ დასრულებულა.
    <a href="sign_group.php?t=<?= gg_e($group['group_token']) ?>" style="color:#92400E;font-weight:700">ხელმოწერა →</a></div>
<?php endif; ?>

<div class="card">
  <h1>🛡 საგარანტიო ბარათები</h1>
  <div class="sub"><?= gg_e(trim($group['first_name'] . ' ' . $group['last_name'])) ?> · <?= count($items) ?> ნივთი</div>
  <div style="margin-top:14px">
    <?php if ($branchName): ?><div class="row"><span>ფილიალი</span><b><?= gg_e($branchName) ?></b></div><?php endif; ?>
    <div class="row"><span>შეძენის თარიღი</span><b><?= gg_e(!empty($items[0]['purchase_date']) ? formatDate($items[0]['purchase_date']) : '—') ?></b></div>
    <div class="row" style="border-bottom:0"><span>ხელმოწერა</span>
      <b><?= $group['signed_at'] ? gg_e(substr($group['signed_at'], 0, 16)) : '—' ?></b></div>
  </div>
</div>

<div class="card">
  <h1 style="font-size:16px">ნივთები</h1>
  <?php foreach ($items as $r):
    $exp = !empty($r['warranty_end_date']) && $r['warranty_end_date'] < $today;
    $cls = $exp ? 'dead' : (empty($r['signed_at']) ? 'warn' : 'ok');
    $lbl = $exp ? 'ვადაგასული' : (empty($r['signed_at']) ? 'ხელმოსაწერი' : 'აქტიური'); ?>
    <a class="item" href="public.php?t=<?= gg_e($r['token']) ?>">
      <span class="arrow">›</span>
      <b><?= gg_e($r['product_name']) ?></b>
      <div class="meta">#<?= gg_e($r['short_code']) ?><?= $r['serial_number'] ? ' · ' . gg_e($r['serial_number']) : '' ?></div>
      <div class="meta"><?= gg_e($r['category_name'] ?? '') ?> · <?= gg_e(formatDate($r['warranty_end_date'])) ?>-მდე
        <span class="b <?= $cls ?>" style="margin-left:6px"><?= $lbl ?></span></div>
    </a>
  <?php endforeach; ?>
  <?php if (!$items): ?><p class="sub" style="margin-top:12px">ნივთები ვერ მოიძებნა</p><?php endif; ?>
</div>

<?php if ($unsigned): ?>
  <a class="btn" href="sign_group.php?t=<?= gg_e($group['group_token']) ?>">✍️ ხელმოწერა</a>
<?php endif; ?>
<div class="brand">გაჯეტი · warranty.gadget.ge</div>
</div></body></html>
