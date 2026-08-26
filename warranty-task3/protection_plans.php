<?php
/** protection_plans.php — დაცვის პაკეტების ადმინი (TASK 3). */
require_once 'includes/config.php';
require_once 'includes/protection.php';
requireLogin();
blockBranchUser();
if (!isAdmin()) { header('Location: index.php'); exit; }

$err = null; $ok = null;
$TYPES = protIncidentTypes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) { csrf_verify(); }
    try {
        if (isset($_POST['toggle'], $_POST['id'])) {
            $pdo->prepare("UPDATE gw_protection_plans SET is_active = 1 - is_active WHERE id = ?")->execute([(int)$_POST['id']]);
            try { auditLog($pdo, 'protection_plan', (int)$_POST['id'], 'toggled', 'is_active', null, null); } catch (Throwable $e) {}
            header('Location: protection_plans.php?ok=1'); exit;
        }
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $err = 'დასახელება სავალდებულოა'; }
        else {
            $cov = array_values(array_intersect(array_keys($TYPES), (array)($_POST['coverage'] ?? [])));
            $excl = array_values(array_filter(array_map('trim', explode("\n", (string)($_POST['exclusions'] ?? '')))));
            $args = [
                $name,
                trim($_POST['plan_type'] ?? 'general') ?: 'general',
                ($_POST['price_type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed',
                round((float)str_replace(',', '.', $_POST['price_value'] ?? '0'), 2),
                max(1, (int)($_POST['duration_months'] ?? 12)),
                json_encode($cov, JSON_UNESCAPED_UNICODE),
                json_encode($excl, JSON_UNESCAPED_UNICODE),
                trim($_POST['terms'] ?? ''),
                ($_POST['min_price'] ?? '') !== '' ? round((float)$_POST['min_price'], 2) : null,
                ($_POST['max_price'] ?? '') !== '' ? round((float)$_POST['max_price'], 2) : null,
                !empty($_POST['is_active']) ? 1 : 0,
            ];
            $editId = (int)($_POST['id'] ?? 0);
            if ($editId) {
                $args[] = $editId;
                $pdo->prepare("UPDATE gw_protection_plans SET name=?, plan_type=?, price_type=?, price_value=?,
                    duration_months=?, coverage_json=?, exclusions_json=?, terms=?, min_price=?, max_price=?, is_active=?,
                    terms_version = terms_version + 1 WHERE id=?")->execute($args);
                try { auditLog($pdo, 'protection_plan', $editId, 'updated', null, null, $name); } catch (Throwable $e) {}
            } else {
                $pdo->prepare("INSERT INTO gw_protection_plans
                    (name, plan_type, price_type, price_value, duration_months, coverage_json, exclusions_json, terms, min_price, max_price, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($args);
                try { auditLog($pdo, 'protection_plan', (int)$pdo->lastInsertId(), 'created', null, null, $name); } catch (Throwable $e) {}
            }
            header('Location: protection_plans.php?ok=1'); exit;
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $q = $pdo->prepare("SELECT * FROM gw_protection_plans WHERE id = ?");
    $q->execute([(int)$_GET['edit']]);
    $editing = $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
$plans = [];
$listErr = null;
try { $plans = $pdo->query("SELECT * FROM gw_protection_plans ORDER BY is_active DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC); }
catch (Throwable $e) { $listErr = $e->getMessage() . ' — ჯერ migrate_protection.php გაუშვი.'; }
$csrf = function_exists('csrf_field') ? csrf_field() : '';
$E = $editing ?: [];
$curCov = protJson($E['coverage_json'] ?? null);
function pl_e($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>🛡 დაცვის პაკეტები</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.pp-wrap{max-width:1000px;margin:24px auto;padding:0 16px}
.pp-card{background:#fff;border:1px solid #E7EBF1;border-radius:12px;padding:20px;margin-bottom:18px}
.pp-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:12px}
.pp-grid2{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.pp-tbl{width:100%;border-collapse:collapse;font-size:13px}
.pp-tbl th,.pp-tbl td{padding:9px 11px;border-bottom:1px solid #EEF1F6;text-align:left;vertical-align:top}
.pp-b{padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700}
.pp-on{background:#ECFDF5;color:#047857}.pp-off{background:#F1F5F9;color:#64748B}
.pp-btn{background:#4F46E5;color:#fff;border:0;border-radius:9px;padding:9px 18px;font-weight:600;cursor:pointer;font-size:13px}
.pp-btn.sm{padding:5px 12px;font-size:12px}
.pp-btn.gray{background:#fff;color:#64748B;border:1px solid #E7EBF1}
.pp-err{background:#FEF2F2;color:#991B1B;padding:12px;border-radius:9px;margin-bottom:14px}
.pp-ok{background:#ECFDF5;border:1px solid #A7F3D0;color:#047857;padding:11px 14px;border-radius:10px;margin-bottom:14px;font-weight:600}
.pp-cov label{display:inline-flex;gap:5px;align-items:center;margin:0 12px 6px 0;font-size:13px}
</style></head><body>
<?php include 'includes/navbar.php'; ?>
<div class="pp-wrap">
  <h1>🛡 დაცვის პაკეტები</h1>
  <p style="color:#64748B">პაკეტს ყიდულობს მომხმარებელი საგარანტიო ბარათიდან. ფასი: ფიქსირებული ან ნივთის ფასის %.</p>

  <?php if (!empty($_GET['ok'])): ?><div class="pp-ok">✓ შენახულია</div><?php endif; ?>
  <?php if ($err): ?><div class="pp-err">❌ <?= pl_e($err) ?></div><?php endif; ?>
  <?php if ($listErr): ?><div class="pp-err">❌ <?= pl_e($listErr) ?></div><?php endif; ?>

  <div class="pp-card">
    <h3 style="margin-bottom:14px"><?= $editing ? '✏️ რედაქტირება: ' . pl_e($E['name']) : '➕ ახალი პაკეტი' ?></h3>
    <form method="POST">
      <?= $csrf ?>
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$E['id'] ?>"><?php endif; ?>
      <div class="pp-grid">
        <div><label>დასახელება *</label><input class="form-control" name="name" required value="<?= pl_e($E['name'] ?? '') ?>"></div>
        <div><label>ტიპი (key)</label><input class="form-control" name="plan_type" value="<?= pl_e($E['plan_type'] ?? 'general') ?>"></div>
        <div><label>ფასის ტიპი</label><select class="form-control" name="price_type">
          <option value="fixed" <?= ($E['price_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>ფიქსირებული ₾</option>
          <option value="percent" <?= ($E['price_type'] ?? '') === 'percent' ? 'selected' : '' ?>>% ნივთის ფასიდან</option>
        </select></div>
        <div><label>მნიშვნელობა</label><input class="form-control" name="price_value" value="<?= pl_e($E['price_value'] ?? '0') ?>"></div>
      </div>
      <div class="pp-grid2" style="margin-top:12px">
        <div><label>ხანგრძლივობა (თვე)</label><input class="form-control" name="duration_months" value="<?= pl_e($E['duration_months'] ?? 12) ?>"></div>
        <div><label>მინ. ნივთის ფასი ₾</label><input class="form-control" name="min_price" value="<?= pl_e($E['min_price'] ?? '') ?>"></div>
        <div><label>მაქს. ნივთის ფასი ₾</label><input class="form-control" name="max_price" value="<?= pl_e($E['max_price'] ?? '') ?>"></div>
      </div>
      <div class="pp-cov" style="margin-top:14px"><label style="display:block;font-weight:600;margin-bottom:6px">რას ფარავს</label>
        <?php foreach ($TYPES as $k => $lb): ?>
          <label><input type="checkbox" name="coverage[]" value="<?= pl_e($k) ?>" <?= in_array($k, $curCov, true) ? 'checked' : '' ?>> <?= pl_e($lb) ?></label>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:12px"><label>გამონაკლისები (თითო ხაზზე)</label>
        <textarea class="form-control" name="exclusions" rows="2"><?= pl_e(implode("\n", protJson($E['exclusions_json'] ?? null))) ?></textarea></div>
      <div style="margin-top:12px"><label>პირობები (კლიენტი ნახავს; ყიდვისას იყინება)</label>
        <textarea class="form-control" name="terms" rows="4"><?= pl_e($E['terms'] ?? '') ?></textarea></div>
      <label style="display:flex;gap:7px;align-items:center;margin:14px 0"><input type="checkbox" name="is_active" value="1" <?= !isset($E['is_active']) || $E['is_active'] ? 'checked' : '' ?>> აქტიური (გამოჩნდება მომხმარებელთან)</label>
      <button class="pp-btn"><?= $editing ? 'განახლება' : 'დამატება' ?></button>
      <?php if ($editing): ?><a class="pp-btn gray" href="protection_plans.php" style="text-decoration:none;display:inline-block;padding:9px 18px">გაუქმება</a><?php endif; ?>
    </form>
  </div>

  <div class="pp-card">
    <h3 style="margin-bottom:10px">პაკეტები (<?= count($plans) ?>)</h3>
    <div style="overflow-x:auto"><table class="pp-tbl">
      <tr><th>#</th><th>დასახელება</th><th>ტიპი</th><th>ფასი</th><th>ვადა</th><th>ფარავს</th><th>სტატუსი</th><th></th></tr>
      <?php foreach ($plans as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><b><?= pl_e($p['name']) ?></b></td>
        <td><?= pl_e($p['plan_type']) ?></td>
        <td><?= $p['price_type'] === 'percent' ? pl_e($p['price_value']) . '%' : number_format((float)$p['price_value'], 2) . '₾' ?></td>
        <td><?= (int)$p['duration_months'] ?> თვე</td>
        <td><?php $c = protJson($p['coverage_json']); $names = [];
              foreach ($c as $k) { $names[] = $TYPES[$k] ?? $k; }
              echo pl_e(implode(', ', $names) ?: '—'); ?></td>
        <td><span class="pp-b <?= $p['is_active'] ? 'pp-on' : 'pp-off' ?>"><?= $p['is_active'] ? 'აქტიური' : 'გამორთული' ?></span></td>
        <td style="white-space:nowrap">
          <a class="pp-btn sm gray" style="text-decoration:none" href="protection_plans.php?edit=<?= (int)$p['id'] ?>">✏️</a>
          <form method="POST" style="display:inline"><?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="pp-btn sm gray" name="toggle" value="1"><?= $p['is_active'] ? '⏸' : '▶' ?></button></form>
        </td>
      </tr>
      <?php endforeach; if (!$plans && !$listErr): ?><tr><td colspan="8" style="text-align:center;color:#94A3B8;padding:22px">პაკეტები არ არის</td></tr><?php endif; ?>
    </table></div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
</body></html>
