<?php
/**
 * service_parts.php?case_id=X — ნაწილების მოთხოვნა/სტატუსები (TASK 2).
 * critical requested → case waiting_part; ყველა critical მიღებული → case in_repair.
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

$err = null;
$FLOW = ['requested' => 'ordered', 'ordered' => 'received', 'received' => 'installed'];
$PLBL = ['requested' => 'მოთხოვნილი', 'ordered' => 'შეკვეთილი', 'received' => 'მიღებული',
         'installed' => 'დაყენებული', 'unavailable' => 'მიუწვდომელი', 'cancelled' => 'გაუქმებული'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) { csrf_verify(); }
    try {
        if (isset($_POST['add_part'])) {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') { $err = 'დასახელება ცარიელია'; }
            else {
                $qty = max(1, (int)($_POST['qty'] ?? 1));
                $cost = $_POST['est_unit_cost'] !== '' ? round((float)str_replace(',', '.', $_POST['est_unit_cost']), 2) : null;
                $crit = !empty($_POST['is_critical']) ? 1 : 0;
                $pdo->prepare("INSERT INTO gw_service_parts
                    (service_case_id, name, sku, qty, est_unit_cost, status, is_critical, requested_by, requested_at)
                    VALUES (?,?,?,?,?,'requested',?,?,NOW())")
                    ->execute([$caseId, $name, trim($_POST['sku'] ?? '') ?: null, $qty, $cost, $crit, currentUserId()]);
                svAddActivity($pdo, $caseId, 'worklog', 'ნაწილი მოთხოვნილია: ' . $name . ' ×' . $qty,
                    ['part_id' => (int)$pdo->lastInsertId(), 'critical' => $crit], currentUserId());
                if ($crit && in_array($case['status'], ['in_diagnostic', 'in_repair'], true)) {
                    svSetStatus($pdo, $caseId, 'waiting_part', currentUserId(), 'კრიტიკული ნაწილის მოლოდინი: ' . $name);
                }
                header('Location: service_parts.php?case_id=' . $caseId . '&ok=1'); exit;
            }
        } elseif (isset($_POST['part_action'], $_POST['part_id'])) {
            $pid = (int)$_POST['part_id'];
            $pq = $pdo->prepare("SELECT * FROM gw_service_parts WHERE id=? AND service_case_id=?");
            $pq->execute([$pid, $caseId]);
            $part = $pq->fetch(PDO::FETCH_ASSOC);
            if ($part) {
                $act = $_POST['part_action'];
                $new = null;
                if ($act === 'advance' && isset($FLOW[$part['status']])) $new = $FLOW[$part['status']];
                elseif ($act === 'unavailable' && !in_array($part['status'], ['installed'], true)) $new = 'unavailable';
                elseif ($act === 'cancel' && !in_array($part['status'], ['installed'], true)) $new = 'cancelled';
                if ($new) {
                    $extra = '';
                    if ($new === 'received') $extra = ', received_at=NOW()';
                    if ($new === 'installed') $extra = ', installed_at=NOW()';
                    $pdo->prepare("UPDATE gw_service_parts SET status=?$extra WHERE id=?")->execute([$new, $pid]);
                    if ($new === 'received' && $_POST['actual_unit_cost'] !== '' && isset($_POST['actual_unit_cost'])) {
                        $ac = round((float)str_replace(',', '.', $_POST['actual_unit_cost']), 2);
                        $pdo->prepare("UPDATE gw_service_parts SET actual_unit_cost=? WHERE id=?")->execute([$ac, $pid]);
                    }
                    svAddActivity($pdo, $caseId, 'worklog', 'ნაწილი "' . $part['name'] . '": ' . $PLBL[$part['status']] . ' → ' . $PLBL[$new],
                        ['part_id' => $pid], currentUserId());
                    if ($case['status'] === 'waiting_part' && svOpenCriticalParts($pdo, $caseId) === 0) {
                        svSetStatus($pdo, $caseId, 'in_repair', currentUserId(), 'ნაწილები ადგილზეა — შეკეთება გრძელდება');
                    }
                }
            }
            header('Location: service_parts.php?case_id=' . $caseId . '&ok=1'); exit;
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$parts = [];
try {
    $p = $pdo->prepare("SELECT p.*, u.full_name FROM gw_service_parts p LEFT JOIN gw_users u ON u.id=p.requested_by
        WHERE p.service_case_id=? ORDER BY p.id DESC");
    $p->execute([$caseId]);
    $parts = $p->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $err = $err ?: $e->getMessage(); }
$csrf = function_exists('csrf_field') ? csrf_field() : '';
function pe($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>🔩 ნაწილები — <?= pe($case['case_number']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.pt-wrap{max-width:880px;margin:24px auto;padding:0 16px}
.pt-card{background:#fff;border:1px solid #E7EBF1;border-radius:12px;padding:20px;margin-bottom:18px}
.pt-tbl{width:100%;border-collapse:collapse;font-size:13px}
.pt-tbl th,.pt-tbl td{padding:9px 10px;border-bottom:1px solid #EEF1F6;text-align:left;vertical-align:middle}
.pt-grid{display:grid;grid-template-columns:2fr 1fr 70px 1fr 110px;gap:10px;align-items:end}
.pt-b{padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;background:#EEF0FF;color:#4338CA}
.pt-b.received{background:#ECFDF5;color:#047857}.pt-b.installed{background:#DCFCE7;color:#166534}
.pt-b.unavailable,.pt-b.cancelled{background:#FEF2F2;color:#991B1B}
.pt-btn{border:0;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;background:#4F46E5;color:#fff}
.pt-btn.gray{background:#fff;color:#64748B;border:1px solid #E7EBF1}
.pt-err{background:#FEF2F2;color:#991B1B;padding:12px;border-radius:9px;margin-bottom:14px}
.pt-cost{width:90px;padding:5px 7px;border:1px solid #E7EBF1;border-radius:7px;font-size:12px}
</style></head><body>
<?php include 'includes/navbar.php'; ?>
<div class="pt-wrap">
  <p><a href="service_case.php?id=<?= $caseId ?>">← ქეისზე დაბრუნება</a></p>
  <h1>🔩 ნაწილები — #<?= pe($case['case_number']) ?></h1>
  <p style="color:#64748B"><?= pe($case['product_name'] ?? '') ?> · სტატუსი: <b><?= svLabel($case['status']) ?></b> ·
     ნაწილების ჯამი: <b><?= number_format(svPartsCost($pdo, $caseId), 2) ?>₾</b></p>

  <?php if ($err): ?><div class="pt-err">❌ <?= pe($err) ?></div><?php endif; ?>

  <div class="pt-card">
    <h3 style="margin-bottom:12px">➕ ნაწილის მოთხოვნა</h3>
    <form method="POST">
      <?= $csrf ?>
      <input type="hidden" name="case_id" value="<?= $caseId ?>">
      <input type="hidden" name="add_part" value="1">
      <div class="pt-grid">
        <div><label>დასახელება *</label><input class="form-control" name="name" required></div>
        <div><label>SKU</label><input class="form-control" name="sku"></div>
        <div><label>რაოდ.</label><input class="form-control" name="qty" value="1" inputmode="numeric"></div>
        <div><label>სავარ. ფასი (₾)</label><input class="form-control" name="est_unit_cost" value="" inputmode="decimal"></div>
        <div><label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="is_critical" value="1" checked> კრიტიკული</label>
          <button class="pt-btn" style="width:100%;margin-top:6px">დამატება</button></div>
      </div>
      <p style="color:#94A3B8;font-size:12px;margin-top:8px">კრიტიკული = შეკეთება ვერ გაგრძელდება მის გარეშე (ქეისი გადავა „ელოდება ნაწილს"-ზე)</p>
    </form>
  </div>

  <div class="pt-card">
    <h3 style="margin-bottom:10px">სია (<?= count($parts) ?>)</h3>
    <div style="overflow-x:auto"><table class="pt-tbl">
      <tr><th>ნაწილი</th><th>SKU</th><th>რაოდ.</th><th>ფასი (სავ./ფაქტ.)</th><th>კრიტ.</th><th>სტატუსი</th><th>მოქმედება</th></tr>
      <?php foreach ($parts as $p): ?>
      <tr>
        <td><b><?= pe($p['name']) ?></b><br><small style="color:#94A3B8"><?= pe($p['full_name'] ?? '') ?> · <?= pe(substr((string)$p['requested_at'], 0, 16)) ?></small></td>
        <td><?= pe($p['sku'] ?? '—') ?></td>
        <td><?= (int)$p['qty'] ?></td>
        <td><?= $p['est_unit_cost'] !== null ? number_format((float)$p['est_unit_cost'], 2) : '—' ?> /
            <?= $p['actual_unit_cost'] !== null ? number_format((float)$p['actual_unit_cost'], 2) : '—' ?></td>
        <td><?= $p['is_critical'] ? '🔴' : '—' ?></td>
        <td><span class="pt-b <?= pe($p['status']) ?>"><?= $PLBL[$p['status']] ?? pe($p['status']) ?></span></td>
        <td>
          <?php if (isset($FLOW[$p['status']])): ?>
          <form method="POST" style="display:inline-flex;gap:6px;align-items:center">
            <?= $csrf ?><input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="part_action" value="advance">
            <?php if ($FLOW[$p['status']] === 'received'): ?>
              <input class="pt-cost" name="actual_unit_cost" placeholder="ფაქტ. ₾" inputmode="decimal">
            <?php else: ?><input type="hidden" name="actual_unit_cost" value=""><?php endif; ?>
            <button class="pt-btn">→ <?= $PLBL[$FLOW[$p['status']]] ?></button>
          </form>
          <?php endif; ?>
          <?php if (!in_array($p['status'], ['installed', 'cancelled', 'unavailable'], true)): ?>
          <form method="POST" style="display:inline">
            <?= $csrf ?><input type="hidden" name="case_id" value="<?= $caseId ?>">
            <input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="actual_unit_cost" value="">
            <button class="pt-btn gray" name="part_action" value="unavailable">∅</button>
            <button class="pt-btn gray" name="part_action" value="cancel">✗</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; if (!$parts): ?><tr><td colspan="7" style="color:#94A3B8;text-align:center;padding:20px">ნაწილები არ არის</td></tr><?php endif; ?>
    </table></div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
</body></html>
