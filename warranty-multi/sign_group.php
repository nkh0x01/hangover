<?php
/**
 * sign_group.php?t=GROUP_TOKEN — PUBLIC: ერთი ხელმოწერა ჯგუფის ყველა ნივთზე.
 * ავტორიზაცია არ ჭირდება (token = წვდომა), sign_warranty.php-ის იდენტური ლოგიკით.
 */
require_once 'includes/config.php';
require_once 'includes/reggroup.php';

$token = preg_replace('/[^a-f0-9]/i', '', $_GET['t'] ?? '');
$group = rgLoad($pdo, $token, 'token');
if (!$group) { http_response_code(404); die('ლინკი არასწორია ან ვადაგასულია'); }

$items   = $group['items'] ?? [];
$pending = array_values(array_filter($items, function ($r) { return empty($r['signed_at']); }));
$alreadySigned = !$pending;

/* ვადა — ყველა მოსაწერი ნივთის token ვადაგასულია? (register.php: +72სთ) */
$expired = false;
if (!$alreadySigned) {
    $expired = true;
    foreach ($pending as $r) {
        if (empty($r['signature_token_expires']) || strtotime($r['signature_token_expires']) >= time()) { $expired = false; break; }
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySigned && !$expired) {
    $signedName = trim($_POST['signed_by_name'] ?? '');
    $signature  = $_POST['signature_data'] ?? '';

    if (!$signedName) {
        $error = 'სახელი გვარი აუცილებელია';
    } elseif (!$signature || strlen($signature) < 100) {
        $error = 'ხელმოწერა აუცილებელია';
    } else {
        $sigDir = UPLOAD_DIR . '/signatures/group_' . (int)$group['id'];
        if (!is_dir($sigDir)) { @mkdir($sigDir, 0755, true); }

        $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $signature);
        $data   = base64_decode($base64);
        $ts     = time();
        $sigRelPath  = 'signatures/group_' . (int)$group['id'] . '/warranty_' . $ts . '.png';
        $sigSavePath = UPLOAD_DIR . '/' . $sigRelPath;

        if (@file_put_contents($sigSavePath, $data)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            try {
                $pdo->beginTransaction();

                /* ერთი ხელმოწერა — ჯგუფის ყველა ხელმოუწერელ ნივთზე */
                $up = $pdo->prepare("UPDATE gw_registrations SET
                        signed_at=NOW(), signature_path=?, signed_by_name=?,
                        signature_ip=?, signature_user_agent=?, signature_token=NULL,
                        activation_status='active', activation_source='customer_signature', activated_at=NOW()
                    WHERE group_id=? AND signed_at IS NULL AND deleted_at IS NULL");
                $up->execute([$sigRelPath, $signedName, $ip, $ua, (int)$group['id']]);

                $pdo->prepare("UPDATE gw_registration_groups SET
                        signed_at=NOW(), signature_path=?, signed_by_name=?, signature_ip=?, signature_user_agent=?
                    WHERE id=?")
                    ->execute([$sigRelPath, $signedName, $ip, $ua, (int)$group['id']]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('sign_group: ' . $e->getMessage());
                $error = 'ხელმოწერის შენახვა ვერ მოხერხდა';
            }

            if (!$error) {
                foreach ($pending as $r) {
                    try { auditLog($pdo, 'registration', (int)$r['id'], 'signed', null, null,
                        ['signed_by' => $signedName, 'group' => (int)$group['id']]); } catch (Throwable $e) {}
                }
                try { auditLog($pdo, 'registration_group', (int)$group['id'], 'signed', null, null,
                    ['signed_by' => $signedName, 'items' => count($pending)]); } catch (Throwable $e) {}

                /* SMS-ისთვის მოკლე slug-ლინკი (როგორც g.php?c=), ტოკენი 48 სიმბოლოა */
                $cardLink  = SITE_URL . '/g_group.php?c=' . $group['public_slug'];
                $cardFull  = SITE_URL . '/g_group.php?t=' . $group['group_token'];
                try {
                    queueSmsNow('warranty_signed', $group['phone'],
                        'გაჯეტი: მადლობა! ბარათები: ' . $cardLink,
                        'gsign_thanks:' . (int)$group['id'], 'registration_group', (int)$group['id']);
                } catch (Throwable $e) { error_log('group thanks SMS: ' . $e->getMessage()); }

                if (!empty($group['customer_email'])) {
                    try {
                        $li = '';
                        foreach ($items as $r) {
                            $li .= '<li>' . htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8')
                                 . ' — #' . htmlspecialchars($r['short_code'], ENT_QUOTES, 'UTF-8')
                                 . ' (' . formatDate($r['warranty_end_date']) . '-მდე)</li>';
                        }
                        sendEmail($group['customer_email'], 'საგარანტიო ბარათები აქტიურია',
                            "<div style='font-family:Arial;max-width:520px;margin:0 auto'>
                             <h2>გამარჯობა {$signedName}!</h2>
                             <p>მადლობა ხელმოწერისთვის. თქვენი საგარანტიო ბარათები აქტიურია.</p>
                             <ul>{$li}</ul>
                             <p><a href='{$cardFull}' style='background:#111;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px'>ბარათების ნახვა</a></p></div>");
                    } catch (Throwable $e) { error_log('group email: ' . $e->getMessage()); }
                }

                if (ob_get_level()) { ob_end_clean(); }
                header('Location: ' . $cardFull);
                exit;
            }
        } else {
            $error = 'ხელმოწერის შენახვა ვერ მოხერხდა';
        }
    }
}

/* პირობები — პირველი ნივთის კატეგორიიდან (ჩვეულებრივ ერთი ჩეკი = ერთი პირობა) */
$terms = '';
try {
    if ($items) {
        $tq = $pdo->prepare("SELECT warranty_terms FROM gw_categories WHERE id=?");
        $tq->execute([(int)$items[0]['category_id']]);
        $terms = (string)$tq->fetchColumn();
    }
} catch (Throwable $e) {}

function sg_e($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
<title>საგარანტიო ხელმოწერა — გაჯეტი</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{font-family:'Segoe UI',-apple-system,"Noto Sans Georgian",sans-serif;background:#f4f4f7;color:#111;padding:16px}
.wrap{max-width:540px;margin:0 auto}
.card{background:#fff;border-radius:16px;padding:20px;margin-bottom:14px;box-shadow:0 6px 20px rgba(0,0,0,.06)}
h1{font-size:19px;margin-bottom:4px}
.sub{color:#666;font-size:13px}
.item{border:1px solid #eee;border-radius:11px;padding:12px;margin-top:10px}
.item b{font-size:14.5px}
.meta{color:#777;font-size:12.5px;margin-top:3px}
.badge{display:inline-block;background:#EEF0FF;color:#4338CA;border-radius:999px;padding:2px 9px;font-size:11px;font-weight:700}
.terms{max-height:190px;overflow:auto;background:#FAFAFC;border:1px solid #eee;border-radius:10px;padding:12px;font-size:12.5px;color:#444;white-space:pre-wrap;line-height:1.55}
label{display:block;font-size:13px;font-weight:600;margin:14px 0 5px}
input[type=text]{width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;font-size:16px;font-family:inherit}
#pad{width:100%;height:190px;border:2px dashed #ccc;border-radius:12px;background:#fff;touch-action:none;display:block}
.padbar{display:flex;justify-content:space-between;align-items:center;margin-top:7px}
.clear{background:none;border:0;color:#888;font-size:13px;cursor:pointer;text-decoration:underline;font-family:inherit}
button.go{width:100%;padding:15px;background:#111;color:#fff;border:0;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;margin-top:16px;font-family:inherit}
button.go:disabled{background:#bbb}
.err{background:#FEF2F2;color:#991B1B;padding:12px;border-radius:10px;margin-bottom:12px;font-size:13.5px}
.state{text-align:center;padding:26px 8px}.state .big{font-size:46px;margin-bottom:10px}
a.btn{display:inline-block;margin-top:14px;background:#111;color:#fff;padding:11px 22px;border-radius:10px;text-decoration:none;font-weight:600}
.brand{text-align:center;color:#aaa;font-size:12px;margin-top:16px}
</style></head><body><div class="wrap">

<?php if ($alreadySigned): ?>
  <div class="card"><div class="state"><div class="big">✅</div>
    <h1>უკვე ხელმოწერილია</h1>
    <p class="sub">თქვენი საგარანტიო ბარათები აქტიურია.</p>
    <a class="btn" href="g_group.php?t=<?= sg_e($group['group_token']) ?>">ბარათების ნახვა</a></div></div>

<?php elseif ($expired): ?>
  <div class="card"><div class="state"><div class="big">⏳</div>
    <h1>ლინკი ვადაგასულია</h1>
    <p class="sub">გთხოვთ დაუკავშირდეთ მაღაზიას ახალი ლინკისთვის.</p></div></div>

<?php else: ?>
  <div class="card">
    <h1>საგარანტიო პირობები</h1>
    <div class="sub"><?= sg_e(trim($group['first_name'] . ' ' . $group['last_name'])) ?> · <span class="badge"><?= count($pending) ?> ნივთი</span></div>
    <?php foreach ($pending as $r): ?>
      <div class="item">
        <b><?= sg_e($r['product_name']) ?></b>
        <div class="meta">#<?= sg_e($r['short_code']) ?><?= $r['serial_number'] ? ' · კოდი: ' . sg_e($r['serial_number']) : '' ?></div>
        <div class="meta"><?= sg_e($r['category_name'] ?? '') ?> · გარანტია <b><?= sg_e(formatDate($r['warranty_end_date'])) ?></b>-მდე</div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($terms): ?>
  <div class="card"><h1 style="font-size:15px;margin-bottom:9px">პირობები</h1><div class="terms"><?= sg_e($terms) ?></div></div>
  <?php endif; ?>

  <div class="card">
    <?php if ($error): ?><div class="err">❌ <?= sg_e($error) ?></div><?php endif; ?>
    <form method="POST" id="f">
      <label>სახელი და გვარი *</label>
      <input type="text" name="signed_by_name" required value="<?= sg_e($_POST['signed_by_name'] ?? trim($group['first_name'] . ' ' . $group['last_name'])) ?>">
      <label>ხელმოწერა * <span style="font-weight:400;color:#888">— ერთი ხელმოწერა ყველა ნივთზე</span></label>
      <canvas id="pad"></canvas>
      <div class="padbar"><span style="color:#999;font-size:12px">დახატეთ თითით ან მაუსით</span>
        <button type="button" class="clear" onclick="clearPad()">გასუფთავება</button></div>
      <input type="hidden" name="signature_data" id="sig">
      <button class="go" id="go" disabled>✓ ვეთანხმები და ვაწერ ხელს</button>
    </form>
  </div>
<?php endif; ?>
<div class="brand">გაჯეტი · warranty.gadget.ge</div>
</div>
<script>
(function () {
  var c = document.getElementById('pad');
  if (!c) return;
  var ctx = c.getContext('2d'), drawing = false, dirty = false;
  function size() {
    var r = c.getBoundingClientRect(), d = window.devicePixelRatio || 1;
    var img = dirty ? c.toDataURL() : null;
    c.width = r.width * d; c.height = r.height * d;
    ctx.scale(d, d); ctx.lineWidth = 2.2; ctx.lineCap = 'round';
    ctx.lineJoin = 'round'; ctx.strokeStyle = '#111';
    if (img) { var i = new Image(); i.onload = function () { ctx.drawImage(i, 0, 0, r.width, r.height); }; i.src = img; }
  }
  size();
  window.addEventListener('resize', size);
  function pos(e) {
    var r = c.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - r.left, y: t.clientY - r.top };
  }
  function start(e) { e.preventDefault(); drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
  function move(e) { if (!drawing) return; e.preventDefault(); var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); dirty = true; document.getElementById('go').disabled = false; }
  function end() { drawing = false; }
  c.addEventListener('mousedown', start); c.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  c.addEventListener('touchstart', start, { passive: false });
  c.addEventListener('touchmove', move, { passive: false });
  c.addEventListener('touchend', end);
  window.clearPad = function () { ctx.clearRect(0, 0, c.width, c.height); dirty = false; document.getElementById('go').disabled = true; };
  document.getElementById('f').addEventListener('submit', function (e) {
    if (!dirty) { e.preventDefault(); alert('გთხოვთ მოაწეროთ ხელი'); return; }
    document.getElementById('sig').value = c.toDataURL('image/png');
    document.getElementById('go').disabled = true;
    document.getElementById('go').textContent = 'ინახება…';
  });
})();
</script>
</body></html>
