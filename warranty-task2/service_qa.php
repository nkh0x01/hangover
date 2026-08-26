<?php
/**
 * service_qa.php?case_id=X — ხარისხის კონტროლი (TASK 2).
 * PASS → ready + SMS კლიენტს (idempotent). FAIL → in_repair (rework).
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) { csrf_verify(); }
    $note = trim($_POST['qa_note'] ?? '');
    $decision = $_POST['qa_decision'] ?? '';
    try {
        if ($decision === 'pass') {
            if ($case['status'] === 'in_repair') {
                svSetStatus($pdo, $caseId, 'quality_check', currentUserId(), 'შეკეთება დასრულდა — QA');
                try { $pdo->prepare("UPDATE gw_service_cases SET repair_finished_at=COALESCE(repair_finished_at,NOW()) WHERE id=?")->execute([$caseId]); } catch (Throwable $e) {}
                $case['status'] = 'quality_check';
            }
            [$ok, $terr] = svSetStatus($pdo, $caseId, 'ready', currentUserId(), 'QA: გავიდა ✓' . ($note ? ' — ' . $note : ''));
            if (!$ok) { $err = $terr; }
            else {
                svAddActivity($pdo, $caseId, 'qa', 'QA PASS' . ($note ? ': ' . $note : ''), ['result' => 'pass'], currentUserId());
                try { $pdo->prepare("UPDATE gw_service_cases SET ready_at=COALESCE(ready_at,NOW()) WHERE id=?")->execute([$caseId]); } catch (Throwable $e) {}
                svSendReadySms($pdo, $caseId);
                try { auditLog($pdo, 'service_case', $caseId, 'qa_pass', 'status', 'quality_check', 'ready'); } catch (Throwable $e) {}
                header('Location: service_case.php?id=' . $caseId . '&ok=1'); exit;
            }
        } elseif ($decision === 'fail') {
            if ($note === '') { $err = 'ჩავარდნისას მიზეზი სავალდებულოა'; }
            else {
                if ($case['status'] === 'in_repair') { $case['status'] = 'in_repair'; }
                if ($case['status'] === 'quality_check') {
                    [$ok, $terr] = svSetStatus($pdo, $caseId, 'in_repair', currentUserId(), 'QA: ჩავარდა — ' . $note);
                    if (!$ok) $err = $terr;
                }
                if (!$err) {
                    svAddActivity($pdo, $caseId, 'qa', 'QA FAIL: ' . $note, ['result' => 'fail'], currentUserId());
                    try { auditLog($pdo, 'service_case', $caseId, 'qa_fail', null, null, $note); } catch (Throwable $e) {}
                    header('Location: service_case.php?id=' . $caseId . '&ok=1'); exit;
                }
            }
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$qaHistory = [];
try {
    $h = $pdo->prepare("SELECT a.*, u.full_name FROM gw_service_activities a LEFT JOIN gw_users u ON u.id=a.user_id
        WHERE a.service_case_id=? AND a.type='qa' ORDER BY a.id DESC LIMIT 20");
    $h->execute([$caseId]);
    $qaHistory = $h->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$csrf = function_exists('csrf_field') ? csrf_field() : '';
function qe($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>✅ QA — <?= qe($case['case_number']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.qa-wrap{max-width:640px;margin:24px auto;padding:0 16px}
.qa-card{background:#fff;border:1px solid #E7EBF1;border-radius:12px;padding:22px;margin-bottom:18px}
.qa-btns{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}
.qa-btns button{border:0;border-radius:11px;padding:14px;font-size:15px;font-weight:700;cursor:pointer}
.qa-pass{background:#059669;color:#fff}
.qa-fail{background:#fff;color:#DC2626;border:2px solid #FECACA}
.qa-err{background:#FEF2F2;color:#991B1B;padding:12px;border-radius:9px;margin-bottom:14px}
.qa-hist{font-size:13px;color:#374151}
.qa-hist li{margin-bottom:8px}
</style></head><body>
<?php include 'includes/navbar.php'; ?>
<div class="qa-wrap">
  <p><a href="service_case.php?id=<?= $caseId ?>">← ქეისზე დაბრუნება</a></p>
  <h1>✅ ხარისხის კონტროლი — #<?= qe($case['case_number']) ?></h1>
  <p style="color:#64748B"><?= qe($case['product_name'] ?? '') ?> · სტატუსი: <b><?= svLabel($case['status']) ?></b></p>

  <?php if ($err): ?><div class="qa-err">❌ <?= qe($err) ?></div><?php endif; ?>

  <?php if (in_array($case['status'], ['in_repair', 'quality_check'], true)): ?>
  <div class="qa-card">
    <h3>შემოწმების დასკვნა</h3>
    <p style="color:#64748B;font-size:13px;margin:6px 0 12px">ჩართვა/ფუნქციები · ვიზუალი · კომპლექტაცია · გაწმენდა</p>
    <form method="POST">
      <?= $csrf ?>
      <input type="hidden" name="case_id" value="<?= $caseId ?>">
      <textarea class="form-control" name="qa_note" rows="3" placeholder="შენიშვნა (FAIL-ის შემთხვევაში სავალდებულო)"></textarea>
      <div class="qa-btns">
        <button class="qa-pass" name="qa_decision" value="pass" onclick="return confirm('QA გავიდა — ნივთი გადავა «მზადაა»-ზე და კლიენტს SMS გაეგზავნება. ვადასტურებ.')">✓ გავიდა — მზადაა</button>
        <button class="qa-fail" name="qa_decision" value="fail" onclick="return confirm('QA ჩავარდა — ნივთი ბრუნდება შეკეთებაში?')">✗ ჩავარდა — rework</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="qa-card">ℹ️ QA ხელმისაწვდომია მხოლოდ სტატუსებზე: <b>შეკეთებაში</b> / <b>ხარისხის კონტროლი</b>. ახლანდელი: <b><?= svLabel($case['status']) ?></b></div>
  <?php endif; ?>

  <div class="qa-card">
    <h3 style="margin-bottom:10px">QA ისტორია</h3>
    <ul class="qa-hist">
      <?php foreach ($qaHistory as $h): ?>
      <li><b><?= qe(substr((string)$h['created_at'], 0, 16)) ?></b> · <?= qe($h['full_name'] ?? '—') ?> — <?= qe($h['note']) ?></li>
      <?php endforeach; if (!$qaHistory): ?><li style="color:#94A3B8">ჯერ არ არის</li><?php endif; ?>
    </ul>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
</body></html>
