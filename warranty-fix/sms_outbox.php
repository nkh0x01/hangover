<?php
/**
 * sms_outbox.php — SMS რიგის ადმინი (TASK 1): სია + ფილტრი + retry + ხელით დამუშავება + ტესტი.
 */
require_once 'includes/config.php';
require_once 'includes/sms_outbox.php';
requireLogin();
blockBranchUser();
if (!isAdmin()) { header('Location: index.php'); exit; }

$msg = null; $msgOk = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) { csrf_verify(); }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'retry' && !empty($_POST['id'])) {
            $st = $pdo->prepare("UPDATE gw_sms_outbox SET status='queued', attempts=0, error=NULL WHERE id=? AND status='failed'");
            $st->execute([(int)$_POST['id']]);
            $msg = $st->rowCount() ? '✓ დაბრუნდა რიგში (queued)' : 'ჩანაწერი ვერ მოიძებნა ან failed არ იყო';
            try { auditLog('sms_outbox', (int)$_POST['id'], 'retry', 'status', 'failed', 'queued'); } catch (Throwable $e) {}
        } elseif ($action === 'process') {
            $r = processSmsOutbox(20);
            $msg = "✓ დამუშავდა: აღებული {$r['picked']}, გაგზავნილი {$r['sent']}, ჩავარდნილი {$r['failed']}" . ($r['skipped'] ? " ({$r['skipped']})" : '');
            try { auditLog('sms_outbox', 0, 'process_manual', null, null, json_encode($r)); } catch (Throwable $e) {}
        } elseif ($action === 'test' && !empty($_POST['phone'])) {
            $id = queueSms('test', $_POST['phone'], trim($_POST['text'] ?? '') ?: 'WarrantyPro outbox ტესტი', 'test:' . bin2hex(random_bytes(8)));
            $msg = $id > 0 ? ('✓ ჩაემატა რიგში (#' . $id . ') — გაიგზავნება cron-ზე ან [ახლა დამუშავება] ღილაკით') : ($id === -1 ? '⚠ რიგი მიუწვდომელია — გაიგზავნა პირდაპირ' : '✗ არასწორი ნომერი (9 ციფრი)');
            $msgOk = $id !== 0;
            try { auditLog('sms_outbox', max(0, $id), 'test_queue', null, null, $_POST['phone']); } catch (Throwable $e) {}
        }
    } catch (Throwable $e) { $msg = '✗ ' . $e->getMessage(); $msgOk = false; }
}

$counts = smsOutboxCounts();
$f = $_GET['status'] ?? 'all';
if (!in_array($f, ['all', 'queued', 'sent', 'failed'], true)) $f = 'all';
$rows = [];
$listErr = null;
try {
    if ($f === 'all') {
        $q = $pdo->query("SELECT * FROM gw_sms_outbox ORDER BY id DESC LIMIT 200");
    } else {
        $q = $pdo->prepare("SELECT * FROM gw_sms_outbox WHERE status = ? ORDER BY id DESC LIMIT 200");
        $q->execute([$f]);
    }
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    $listErr = $ex->getMessage() . ' — ჯერ migrate_sms_outbox.php გაუშვი.';
}
$csrf = function_exists('csrf_field') ? csrf_field() : '';
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>📨 SMS Outbox</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>#pg{--ink:#0F172A;--muted:#64748B;--line:#E7EBF1;--bg:#F6F8FC;--primary:#4F46E5;--green:#059669;--red:#DC2626;--amber:#D97706}#pg, #pg *{box-sizing:border-box;margin:0;padding:0}#pg{font-family:system-ui,"Noto Sans Georgian",sans-serif;background:var(--bg);color:var(--ink);font-size:14px}#pg .top{background:#fff;border-bottom:1px solid var(--line);padding:14px 22px;display:flex;justify-content:space-between;align-items:center}#pg .top a{color:var(--muted);text-decoration:none;font-size:13px}#pg{max-width:1150px;margin:0 auto;padding:22px}#pg h1{font-size:21px;margin-bottom:16px}#pg .cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}#pg .card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px}#pg .card .v{font-size:26px;font-weight:800}#pg .card .l{color:var(--muted);font-size:12px}#pg .card.q .v{color:var(--amber)}#pg .card.s .v{color:var(--green)}#pg .card.f .v{color:var(--red)}#pg .bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px}#pg .chip{padding:8px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;text-decoration:none;color:var(--ink);font-size:13px;font-weight:600}#pg .chip.on{background:var(--primary);border-color:var(--primary);color:#fff}#pg .btn{background:var(--primary);color:#fff;border:0;border-radius:9px;padding:9px 16px;font-weight:600;cursor:pointer;font-size:13px}#pg .btn.small{padding:5px 12px;font-size:12px}#pg .btn.gray{background:#fff;color:var(--ink);border:1px solid var(--line)}#pg .panel{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-bottom:18px}#pg .panel h2{font-size:14px;padding:12px 16px;border-bottom:1px solid var(--line);background:#FAFBFE}#pg table{width:100%;border-collapse:collapse}#pg th, #pg td{padding:9px 13px;text-align:left;border-bottom:1px solid #EEF1F6;font-size:12.5px;vertical-align:top}#pg th{background:#FAFBFE;color:var(--muted);font-size:11px;text-transform:uppercase}#pg .b{padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700}#pg .b.queued{background:#FEF3C7;color:#92400E}#pg .b.sent{background:#ECFDF5;color:#047857}#pg .b.failed{background:#FEF2F2;color:#991B1B}#pg .note{background:#ECFDF5;border:1px solid #A7F3D0;color:#047857;padding:11px 14px;border-radius:10px;margin-bottom:14px;font-weight:600}#pg .note.bad{background:#FEF2F2;border-color:#FECACA;color:#991B1B}#pg .err{background:#FEF2F2;color:#991B1B;padding:13px;border-radius:10px;margin-bottom:14px;font-family:monospace;font-size:12.5px}#pg .inline{display:inline}#pg .test input{padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px;font-family:inherit}#pg .body-cell{max-width:340px;color:#374151}#pg .mono{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;color:var(--muted)}
</style></head><body>
<?php include 'includes/navbar.php'; ?><div class="wrap" id="pg">
<h1>SMS რიგი — მიწოდების კონტროლი</h1>

<?php if ($msg): ?><div class="note <?= $msgOk ? '' : 'bad' ?>"><?= e($msg) ?></div><?php endif; ?>
<?php if ($listErr): ?><div class="err">❌ <?= e($listErr) ?></div><?php endif; ?>

<div class="cards">
  <div class="card q"><div class="v"><?= $counts['queued'] ?></div><div class="l">რიგში (queued)</div></div>
  <div class="card s"><div class="v"><?= $counts['sent'] ?></div><div class="l">გაგზავნილი (sent)</div></div>
  <div class="card f"><div class="v"><?= $counts['failed'] ?></div><div class="l">ჩავარდნილი (failed)</div></div>
</div>

<div class="bar">
  <?php foreach (['all' => 'ყველა', 'queued' => 'რიგში', 'sent' => 'გაგზავნილი', 'failed' => 'ჩავარდნილი'] as $k => $lb): ?>
    <a class="chip <?= $f === $k ? 'on' : '' ?>" href="sms_outbox.php?status=<?= $k ?>"><?= $lb ?></a>
  <?php endforeach; ?>
  <form method="POST" class="inline"><?= $csrf ?><input type="hidden" name="action" value="process">
    <button class="btn">⚡ ახლა დამუშავება (20)</button></form>
</div>

<div class="panel"><h2>🧪 ტესტ-SMS რიგში</h2>
  <form method="POST" class="test" style="padding:14px;display:flex;gap:10px;flex-wrap:wrap">
    <?= $csrf ?><input type="hidden" name="action" value="test">
    <input name="phone" placeholder="5XXXXXXXX (9 ციფრი)" required>
    <input name="text" placeholder="ტექსტი (არასავალდებულო)" style="min-width:260px">
    <button class="btn gray">+ რიგში ჩამატება</button>
  </form>
</div>

<div class="panel"><h2>ბოლო <?= count($rows) ?> ჩანაწერი</h2>
<div style="overflow-x:auto"><table>
<tr><th>#</th><th>დანიშნულება</th><th>ნომერი</th><th>ტექსტი</th><th>სტატუსი</th><th>ცდები</th><th>msg id / შეცდომა</th><th>შეიქმნა</th><th>გაიგზავნა</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= e($r['purpose']) ?><?= $r['related_type'] ? '<br><span class="mono">' . e($r['related_type']) . '#' . e((string)($r['related_id'] ?? '')) . '</span>' : '' ?></td>
  <td><?= e($r['phone9']) ?></td>
  <td class="body-cell"><?= e(mb_strlen($r['body']) > 120 ? mb_substr($r['body'], 0, 120) . '…' : $r['body']) ?></td>
  <td><span class="b <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
  <td><?= (int)$r['attempts'] ?>/5</td>
  <td class="mono"><?= $r['status'] === 'failed' ? e($r['error'] ?? '') : e($r['provider_msg_id'] ?? '—') ?></td>
  <td class="mono"><?= e(substr((string)$r['created_at'], 0, 16)) ?></td>
  <td class="mono"><?= e($r['sent_at'] ? substr($r['sent_at'], 0, 16) : '—') ?></td>
  <td><?php if ($r['status'] === 'failed'): ?>
    <form method="POST" class="inline"><?= $csrf ?><input type="hidden" name="action" value="retry">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small">↻ retry</button></form>
  <?php endif; ?></td>
</tr>
<?php endforeach; if (!$rows && !$listErr): ?><tr><td colspan="10" style="text-align:center;color:#94A3B8;padding:24px">რიგი ცარიელია</td></tr><?php endif; ?>
</table></div></div>
<p style="color:var(--muted);font-size:12px">Cron: <span class="mono">* * * * * /opt/cpanel/ea-php82/root/usr/bin/php …/Warranty/cron/process_sms_outbox.php</span> · failed ხელახლა იცდება 10 წუთში, მაქს. 5-ჯერ.</p>
</div><?php include 'includes/footer.php'; ?>
</body></html>
