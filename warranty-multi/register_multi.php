<?php
/**
 * register_multi.php — ერთ ჩეკზე რამდენიმე ნივთის საგარანტიო.
 * თითო ნივთი ცალკე gw_registrations ჩანაწერია; ერთი group → ერთი SMS → ერთი ხელმოწერა.
 * ADDITIVE — register.php უცვლელია.
 */
require_once 'includes/config.php';
require_once 'includes/reggroup.php';
requireCanRegister();

$err = null; $rowErrors = []; $done = null;

/* ── ცნობარები ── */
$branches = [];
try { $branches = getActiveBranches($pdo); } catch (Throwable $e) { $branches = []; }

$cats = [];
try { $cats = $pdo->query("SELECT id, name, warranty_months, warranty_days FROM gw_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); }
catch (Throwable $e) { $cats = []; }

$staff = [];
foreach (["SELECT id, full_name FROM gw_users WHERE is_active=1 ORDER BY full_name",
          "SELECT id, full_name FROM gw_users ORDER BY full_name"] as $sql) {
    try { $staff = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); break; } catch (Throwable $e) {}
}

$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) { csrf_verify(); }

    $fn    = trim($_POST['first_name'] ?? '');
    $ln    = trim($_POST['last_name'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    if (strlen($phone) > 9) { $phone = substr($phone, -9); }
    $pid   = trim($_POST['personal_id'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $pd    = trim($_POST['purchase_date'] ?? '') ?: date('Y-m-d');
    $branchId = (int)($_POST['branch_id'] ?? 0) ?: (int)(currentBranchId() ?: 0);
    $servedBy = (int)($_POST['served_by'] ?? 0);
    $consent  = !empty($_POST['marketing_consent']) ? 1 : 0;

    $rawItems = $_POST['item'] ?? [];
    $items = [];
    foreach ((array)$rawItems as $i => $it) {
        $code  = trim($it['code'] ?? '');
        $name  = trim($it['name'] ?? '');
        $price = trim($it['price'] ?? '');
        $cid   = (int)($it['category_id'] ?? 0);
        if ($code === '' && $name === '' && $price === '' && !$cid) { continue; }   // ცარიელი რიგი
        $items[$i] = ['code' => $code, 'name' => $name, 'price' => $price, 'category_id' => $cid];
    }

    if ($fn === '' || $ln === '')          { $err = 'სახელი და გვარი აუცილებელია'; }
    elseif (strlen($phone) !== 9)          { $err = 'ტელეფონი უნდა იყოს 9 ციფრი'; }
    elseif (!$branchId)                    { $err = 'აირჩიეთ ფილიალი'; }
    elseif (!$servedBy && $staff)          { $err = 'გთხოვთ აირჩიოთ თანამშრომელი (ვინ გასცემს საგარანტიოს)'; }
    elseif (count($items) < 1)             { $err = 'დაამატეთ მინიმუმ ერთი ნივთი'; }
    else {
        /* ── თითო ნივთის წინასწარი შემოწმება (ჩაწერამდე) ── */
        $prepared = [];
        foreach ($items as $i => $it) {
            if ($it['name'] === '') { $rowErrors[$i] = 'დასახელება აუცილებელია'; continue; }

            $catId = $it['category_id'] ?: rgResolveCategoryId($pdo, $it['code']);
            if (!$catId) { $catId = rgSuggestCategoryId($pdo, $it['name']); }
            if (!$catId) { $rowErrors[$i] = 'კატეგორია ვერ განისაზღვრა — აირჩიეთ ხელით'; continue; }

            $cq = $pdo->prepare("SELECT * FROM gw_categories WHERE id=?");
            $cq->execute([$catId]);
            $cat = $cq->fetch(PDO::FETCH_ASSOC);
            if (!$cat) { $rowErrors[$i] = 'კატეგორია ვერ მოიძებნა'; continue; }

            /* დუბლის დაცვა — იგივე კოდი იმავე დღეს (register.php-ის იდენტური წესი) */
            if ($it['code'] !== '') {
                $dq = $pdo->prepare("SELECT short_code FROM gw_registrations
                    WHERE serial_number = ? AND DATE(created_at) = CURDATE() AND deleted_at IS NULL
                    ORDER BY id DESC LIMIT 1");
                $dq->execute([$it['code']]);
                if ($dup = $dq->fetchColumn()) {
                    $rowErrors[$i] = 'ⓘ ამ კოდზე დღეს უკვე გამოწერილია — #' . $dup;
                    continue;
                }
            }

            $prepared[$i] = [
                'code'  => $it['code'],
                'name'  => $it['name'],
                'price' => $it['price'] === '' ? null : round((float)str_replace(',', '.', $it['price']), 2),
                'cat'   => $cat,
                'wend'  => rgWarrantyEnd($cat, $pd),
            ];
        }

        if (!$prepared) {
            $err = 'ვერცერთი ნივთი ვერ დამუშავდა — იხილეთ შენიშვნები ქვემოთ';
        } else {
            try {
                $pdo->beginTransaction();

                $gToken = bin2hex(random_bytes(24));
                $gSlug  = rgUniqueSlug($pdo);
                $pdo->prepare("INSERT INTO gw_registration_groups
                    (group_token, public_slug, first_name, last_name, phone, customer_email, personal_id,
                     branch_id, registered_by, served_by, item_count, marketing_consent)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$gToken, $gSlug, $fn, $ln, $phone, $email ?: null, $pid ?: null,
                               $branchId, currentUserId(), $servedBy ?: null, count($prepared), $consent]);
                $groupId = (int)$pdo->lastInsertId();

                $consentTime = $consent ? date('Y-m-d H:i:s') : null;
                $created = [];

                foreach ($prepared as $i => $p) {
                    $token   = generateToken();
                    $sc      = generateShortCode($pdo);
                    $pubSlug = generatePublicSlug($pdo);
                    do {
                        $sigToken = bin2hex(random_bytes(24));
                        $e = $pdo->prepare("SELECT id FROM gw_registrations WHERE signature_token=?");
                        $e->execute([$sigToken]);
                    } while ($e->fetch());
                    $sigExpires = date('Y-m-d H:i:s', strtotime('+72 hours'));

                    $pdo->prepare("INSERT INTO gw_registrations
                        (token,signature_token,signature_token_expires,short_code,public_slug,first_name,last_name,
                         personal_id,customer_email,phone,category_id,serial_number,product_price,product_name,
                         purchase_date,warranty_end_date,branch_id,registered_by,marketing_consent,marketing_consent_at,
                         served_by,group_id)
                        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$token, $sigToken, $sigExpires, $sc, $pubSlug, $fn, $ln,
                                   $pid ?: null, $email ?: null, $phone, (int)$p['cat']['id'], $p['code'] ?: null,
                                   $p['price'], $p['name'], $pd, $p['wend'], $branchId, currentUserId(),
                                   $consent, $consentTime, $servedBy ?: null, $groupId]);
                    $regId = (int)$pdo->lastInsertId();
                    $created[] = ['id' => $regId, 'short_code' => $sc, 'name' => $p['name'],
                                  'wend' => $p['wend'], 'cat' => $p['cat']['name'] ?? ''];
                }

                $pdo->commit();

                foreach ($created as $c) {
                    try { auditLog($pdo, 'registration', $c['id'], 'created', null, null,
                        ['customer' => "$fn $ln", 'phone' => $phone, 'group' => $groupId]); } catch (Throwable $e) {}
                }
                try { auditLog($pdo, 'registration_group', $groupId, 'created', null, null,
                    ['items' => count($created), 'phone' => $phone]); } catch (Throwable $e) {}

                /* ── ერთი SMS ჯგუფის ლინკით ── */
                $signLink = SITE_URL . '/s/' . $gSlug;
                $n = count($created);
                try {
                    queueSmsNow('sign', $phone,
                        "გაჯეტი: თქვენს {$n} ნივთზე საგარანტიო მზადაა. გთხოვთ გაეცნოთ პირობებს და მოაწეროთ ხელი: {$signLink}",
                        'gsign:' . $groupId, 'registration_group', $groupId);
                    $pdo->prepare("UPDATE gw_registration_groups SET sms_sent_at=NOW() WHERE id=?")->execute([$groupId]);
                    $pdo->prepare("UPDATE gw_registrations SET sms_sent_at=NOW() WHERE group_id=?")->execute([$groupId]);
                } catch (Throwable $e) { error_log('group sign SMS: ' . $e->getMessage()); }

                $done = ['group_id' => $groupId, 'slug' => $gSlug, 'token' => $gToken,
                         'items' => $created, 'phone' => $phone, 'link' => $signLink,
                         'skipped' => $rowErrors];
                $old = [];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $err = 'ჩაწერა ვერ მოხერხდა: ' . $e->getMessage();
                error_log('register_multi: ' . $e->getMessage());
            }
        }
    }
}

function rm_e($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
$csrf = function_exists('csrf_field') ? csrf_field() : '';
$oldItems = $old['item'] ?? [['code' => '', 'name' => '', 'price' => '', 'category_id' => 0],
                             ['code' => '', 'name' => '', 'price' => '', 'category_id' => 0]];
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>🧾 ჯგუფური საგარანტიო</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
#pg{max-width:1000px;margin:0 auto;padding:4px 0 28px;color:#0F172A;font-size:14px}
#pg *{box-sizing:border-box}
#pg h1{font-size:21px;margin-bottom:6px}
#pg .sub{color:#64748B;font-size:13px;margin-bottom:18px}
#pg .card{background:#fff;border:1px solid #E7EBF1;border-radius:12px;padding:18px;margin-bottom:16px}
#pg .card h3{font-size:14px;margin-bottom:12px;color:#334155}
#pg .g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
#pg .g2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
#pg label{display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#475569}
#pg input,#pg select{width:100%;padding:9px 11px;border:1px solid #E7EBF1;border-radius:9px;font-size:14px;font-family:inherit}
#pg .row{display:grid;grid-template-columns:1.1fr 2fr .8fr 1.4fr 34px;gap:10px;align-items:end;padding:11px;border:1px solid #EEF1F6;border-radius:11px;margin-bottom:10px;background:#FBFCFE}
#pg .row .del{background:#fff;border:1px solid #FECACA;color:#DC2626;border-radius:8px;height:37px;cursor:pointer;font-size:15px}
#pg .rowerr{background:#FEF2F2;color:#991B1B;border-radius:8px;padding:7px 10px;font-size:12px;margin:-4px 0 10px}
#pg .btn{background:#4F46E5;color:#fff;border:0;border-radius:10px;padding:11px 22px;font-weight:700;cursor:pointer;font-size:14px;font-family:inherit}
#pg .btn.gray{background:#fff;color:#334155;border:1px solid #E7EBF1;font-weight:600}
#pg .err{background:#FEF2F2;color:#991B1B;padding:12px;border-radius:10px;margin-bottom:14px}
#pg .ok{background:#ECFDF5;border:1px solid #A7F3D0;color:#047857;padding:14px;border-radius:11px;margin-bottom:14px}
#pg .lnk{font-family:ui-monospace,Menlo,monospace;font-size:13px;background:#fff;border:1px solid #A7F3D0;border-radius:8px;padding:9px 11px;margin-top:9px;word-break:break-all}
#pg table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
#pg th,#pg td{padding:8px 10px;border-bottom:1px solid #EEF1F6;text-align:left}
#pg .hint{color:#94A3B8;font-size:11.5px;margin-top:3px}
@media(max-width:820px){#pg .row{grid-template-columns:1fr 1fr;}#pg .g3,#pg .g2{grid-template-columns:1fr}}
</style></head><body>
<?php include 'includes/navbar.php'; ?>
<div id="pg">
<h1>🧾 ჯგუფური საგარანტიო</h1>
<div class="sub">ერთ ჩეკზე რამდენიმე ნივთი — კლიენტს მიდის <b>ერთი SMS</b> და აწერს <b>ერთხელ</b>.</div>

<?php if ($done): ?>
  <div class="ok">
    ✅ გამოიწერა <b><?= count($done['items']) ?></b> საგარანტიო · SMS გაეგზავნა <b><?= rm_e($done['phone']) ?></b>-ს
    <div class="lnk"><?= rm_e($done['link']) ?></div>
    <table>
      <tr><th>#</th><th>ნივთი</th><th>კატეგორია</th><th>გარანტია</th></tr>
      <?php foreach ($done['items'] as $c): ?>
      <tr><td><a href="registration.php?id=<?= (int)$c['id'] ?>">#<?= rm_e($c['short_code']) ?></a></td>
          <td><?= rm_e($c['name']) ?></td><td><?= rm_e($c['cat']) ?></td><td><?= rm_e($c['wend']) ?>-მდე</td></tr>
      <?php endforeach; ?>
    </table>
    <?php if (!empty($done['skipped'])): ?>
      <div style="margin-top:10px;color:#92400E">⚠ გამოტოვდა <?= count($done['skipped']) ?> რიგი: <?= rm_e(implode(' · ', $done['skipped'])) ?></div>
    <?php endif; ?>
    <div style="margin-top:12px">
      <a class="btn gray" style="text-decoration:none;display:inline-block;padding:9px 18px" href="g_group.php?t=<?= rm_e($done['token']) ?>">ჯგუფის ბარათი</a>
      <a class="btn gray" style="text-decoration:none;display:inline-block;padding:9px 18px" href="register_multi.php">+ ახალი ჯგუფი</a>
    </div>
  </div>
<?php endif; ?>

<?php if ($err): ?><div class="err">❌ <?= rm_e($err) ?></div><?php endif; ?>

<form method="POST" id="f">
  <?= $csrf ?>
  <div class="card">
    <h3>👤 კლიენტი</h3>
    <div class="g3">
      <div><label>სახელი *</label><input name="first_name" required value="<?= rm_e($old['first_name'] ?? '') ?>"></div>
      <div><label>გვარი *</label><input name="last_name" required value="<?= rm_e($old['last_name'] ?? '') ?>"></div>
      <div><label>ტელეფონი * (9 ციფრი)</label><input name="phone" required placeholder="5XXXXXXXX" value="<?= rm_e($old['phone'] ?? '') ?>"></div>
    </div>
    <div class="g3" style="margin-top:12px">
      <div><label>პირადი №</label><input name="personal_id" value="<?= rm_e($old['personal_id'] ?? '') ?>"></div>
      <div><label>ელფოსტა</label><input type="email" name="customer_email" value="<?= rm_e($old['customer_email'] ?? '') ?>"></div>
      <div><label>შეძენის თარიღი</label><input type="date" name="purchase_date" value="<?= rm_e($old['purchase_date'] ?? date('Y-m-d')) ?>"></div>
    </div>
    <div class="g2" style="margin-top:12px">
      <div><label>ფილიალი *</label><select name="branch_id" required>
        <option value="">— აირჩიეთ —</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= (int)($old['branch_id'] ?? currentBranchId()) === (int)$b['id'] ? 'selected' : '' ?>><?= rm_e($b['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
      <?php if ($staff): ?>
      <div><label>თანამშრომელი *</label><select name="served_by" required>
        <option value="">— აირჩიეთ —</option>
        <?php foreach ($staff as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (int)($old['served_by'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= rm_e($s['full_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
      <?php endif; ?>
    </div>
    <label style="display:flex;gap:8px;align-items:center;margin-top:14px;font-weight:500">
      <input type="checkbox" name="marketing_consent" value="1" style="width:auto" <?= !empty($old['marketing_consent']) ? 'checked' : '' ?>>
      კლიენტი თანახმაა მიიღოს სიახლეები და შეთავაზებები
    </label>
  </div>

  <div class="card">
    <h3>📦 ნივთები</h3>
    <div id="rows">
      <?php foreach ($oldItems as $i => $it): ?>
      <?php if (isset($rowErrors[$i])): ?><div class="rowerr">⚠ <?= rm_e($rowErrors[$i]) ?></div><?php endif; ?>
      <div class="row">
        <div><label>კოდი / სერიული</label><input name="item[<?= $i ?>][code]" value="<?= rm_e($it['code'] ?? '') ?>">
          <div class="hint">Fina-კოდით კატეგორია თავისით შეირჩევა</div></div>
        <div><label>დასახელება *</label><input name="item[<?= $i ?>][name]" value="<?= rm_e($it['name'] ?? '') ?>"></div>
        <div><label>ფასი ₾</label><input name="item[<?= $i ?>][price]" value="<?= rm_e($it['price'] ?? '') ?>"></div>
        <div><label>კატეგორია</label><select name="item[<?= $i ?>][category_id]">
          <option value="0">— ავტომატურად —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)($it['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= rm_e($c['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
        <button type="button" class="del" onclick="delRow(this)">✕</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn gray" onclick="addRow()">+ ნივთის დამატება</button>
  </div>

  <button class="btn" onclick="return confirm('გამოიწეროს საგარანტიო და გაეგზავნოს SMS?')">✓ გამოწერა და SMS</button>
  <a class="btn gray" style="text-decoration:none;display:inline-block;padding:11px 22px" href="register.php">ერთ ნივთზე →</a>
</form>
</div>
<script>
var CATS = <?= json_encode(array_map(function ($c) { return ['id' => (int)$c['id'], 'name' => $c['name']]; }, $cats), JSON_UNESCAPED_UNICODE) ?>;
var n = <?= count($oldItems) ?>;
function addRow() {
  var opts = '<option value="0">— ავტომატურად —</option>';
  CATS.forEach(function (c) { opts += '<option value="' + c.id + '">' + c.name.replace(/[<>&]/g, '') + '</option>'; });
  var d = document.createElement('div');
  d.className = 'row';
  d.innerHTML = '<div><label>კოდი / სერიული</label><input name="item[' + n + '][code]"><div class="hint">Fina-კოდით კატეგორია თავისით შეირჩევა</div></div>'
    + '<div><label>დასახელება *</label><input name="item[' + n + '][name]"></div>'
    + '<div><label>ფასი ₾</label><input name="item[' + n + '][price]"></div>'
    + '<div><label>კატეგორია</label><select name="item[' + n + '][category_id]">' + opts + '</select></div>'
    + '<button type="button" class="del" onclick="delRow(this)">✕</button>';
  document.getElementById('rows').appendChild(d);
  n++;
}
function delRow(b) {
  var rows = document.querySelectorAll('#rows .row');
  if (rows.length <= 1) { return; }
  b.parentNode.remove();
}
</script>
<?php include 'includes/footer.php'; ?>
</body></html>
