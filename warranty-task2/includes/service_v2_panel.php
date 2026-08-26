<?php
/**
 * includes/service_v2_panel.php — service_case.php-ში ჩასართავი პანელი (TASK 2).
 * მოელის scope-ში: $pdo, $caseId, $case. თუ v2 ცხრილები ჯერ არ არის — მინიშნებას აჩვენებს, არ ვარდება.
 */
if (!isset($pdo, $caseId, $case)) { return; }
require_once __DIR__ . '/service_v2.php';

$svReady = true;
try { $pdo->query("SELECT 1 FROM gw_service_activities LIMIT 1"); } catch (Throwable $e) { $svReady = false; }

$svCsrf = function_exists('csrf_field') ? csrf_field() : '';
$svErr = isset($_GET['sv_err']) ? (string)$_GET['sv_err'] : '';
$svNext = svAllowed()[$case['status']] ?? [];
$svEst = $svReady ? svLatestEstimate($pdo, $caseId) : null;
$svPartsSum = $svReady ? svPartsCost($pdo, $caseId) : 0;
$svOpenCrit = $svReady ? svOpenCriticalParts($pdo, $caseId) : 0;

$svActs = [];
$svTechs = [];
$svTechName = null;
if ($svReady) {
    try {
        $a = $pdo->prepare("SELECT a.*, u.full_name FROM gw_service_activities a LEFT JOIN gw_users u ON u.id=a.user_id
            WHERE a.service_case_id=? ORDER BY a.id DESC LIMIT 25");
        $a->execute([$caseId]);
        $svActs = $a->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    try {
        $svTechs = $pdo->query("SELECT id, full_name FROM gw_users WHERE role IN ('service','admin','manager') ORDER BY full_name")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) { try { $svTechs = $pdo->query("SELECT id, full_name FROM gw_users ORDER BY full_name")->fetchAll(PDO::FETCH_KEY_PAIR); } catch (Throwable $e2) {} }
    if (!empty($case['technician_id']) && isset($svTechs[$case['technician_id']])) $svTechName = $svTechs[$case['technician_id']];
}
$svEstLabels = ['draft' => 'დრაფტი', 'awaiting_customer' => '⏳ ელოდება კლიენტს', 'approved' => '✅ დადასტურებული',
                'rejected' => '❌ უარყოფილი', 'superseded' => 'ჩანაცვლებული'];
$svTypeIco = ['assign' => '👤', 'diagnosis' => '🔎', 'worklog' => '🛠', 'qa' => '✅', 'handover' => '📦'];
?>
<div style="max-width:1100px;margin:10px auto 26px;padding:0 16px">
  <div style="background:#fff;border:1px solid #E7EBF1;border-radius:12px;padding:20px">
    <h3 style="margin:0 0 4px">🛠 სერვისი v2 — ხარჯთაღრიცხვა · ნაწილები · QA</h3>

    <?php if (!$svReady): ?>
      <p style="background:#FFFBEB;border:1px solid #FDE68A;color:#92400E;padding:10px 12px;border-radius:9px;font-size:13px">
        ⚙️ ჯერ გაუშვი მიგრაცია: <code>migrate_service_v2.php</code></p>
    <?php else: ?>

    <?php if ($svErr): ?>
      <p style="background:#FEF2F2;color:#991B1B;padding:10px 12px;border-radius:9px;font-size:13px">❌ <?= htmlspecialchars($svErr, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p style="margin:6px 0 14px;font-size:14px">
      სტატუსი: <b><?= svLabel($case['status']) ?></b>
      <?php if ($svTechName): ?> · ხელოსანი: <b><?= htmlspecialchars($svTechName, ENT_QUOTES, 'UTF-8') ?></b><?php endif; ?>
      <?php if ($svOpenCrit): ?> · <span style="color:#D97706">🔴 ღია კრიტიკული ნაწილი: <?= $svOpenCrit ?></span><?php endif; ?>
    </p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <?php foreach ($svNext as $svTo): ?>
      <form method="POST" action="service_v2_action.php" style="display:inline">
        <?= $svCsrf ?>
        <input type="hidden" name="case_id" value="<?= (int)$caseId ?>">
        <input type="hidden" name="sv_action" value="status">
        <input type="hidden" name="to" value="<?= htmlspecialchars($svTo, ENT_QUOTES, 'UTF-8') ?>">
        <button style="border:1px solid #C7D2FE;background:#EEF0FF;color:#4338CA;border-radius:999px;padding:7px 14px;font-size:13px;font-weight:600;cursor:pointer"
          <?= in_array($svTo, ['cancelled', 'returned'], true) ? 'onclick="return confirm(\'დარწმუნებული ხარ?\')"' : '' ?>>→ <?= svLabel($svTo) ?></button>
      </form>
      <?php endforeach; ?>
      <a href="service_estimate.php?case_id=<?= (int)$caseId ?>" style="border:1px solid #E7EBF1;border-radius:999px;padding:7px 14px;font-size:13px;font-weight:600;text-decoration:none;color:#0F172A">💰 ხარჯთაღრიცხვა</a>
      <a href="service_parts.php?case_id=<?= (int)$caseId ?>" style="border:1px solid #E7EBF1;border-radius:999px;padding:7px 14px;font-size:13px;font-weight:600;text-decoration:none;color:#0F172A">🔩 ნაწილები (<?= number_format($svPartsSum, 2) ?>₾)</a>
      <a href="service_qa.php?case_id=<?= (int)$caseId ?>" style="border:1px solid #E7EBF1;border-radius:999px;padding:7px 14px;font-size:13px;font-weight:600;text-decoration:none;color:#0F172A">✅ QA</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div style="border:1px solid #EEF1F6;border-radius:10px;padding:14px">
        <b style="font-size:13px">💰 ბოლო ხარჯთაღრიცხვა</b>
        <?php if ($svEst): ?>
          <p style="margin:8px 0 0;font-size:13px">v<?= (int)$svEst['version'] ?> · <b><?= number_format((float)$svEst['total'], 2) ?>₾</b> ·
            <?= $svEstLabels[$svEst['status']] ?? htmlspecialchars($svEst['status'], ENT_QUOTES, 'UTF-8') ?><br>
            <small style="color:#94A3B8"><?= htmlspecialchars(substr((string)$svEst['created_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?><?= $svEst['decided_at'] ? ' · გადაწყდა: ' . htmlspecialchars(substr($svEst['decided_at'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?></small></p>
        <?php else: ?>
          <p style="margin:8px 0 0;font-size:13px;color:#94A3B8">ჯერ არ არის</p>
        <?php endif; ?>
      </div>
      <div style="border:1px solid #EEF1F6;border-radius:10px;padding:14px">
        <b style="font-size:13px">👤 ხელოსანი</b>
        <form method="POST" action="service_v2_action.php" style="display:flex;gap:8px;margin-top:8px">
          <?= $svCsrf ?>
          <input type="hidden" name="case_id" value="<?= (int)$caseId ?>">
          <input type="hidden" name="sv_action" value="technician">
          <select name="technician_id" style="flex:1;padding:7px;border:1px solid #E7EBF1;border-radius:8px;font-size:13px">
            <option value="">— აირჩიე —</option>
            <?php foreach ($svTechs as $svTid => $svTn): ?>
            <option value="<?= (int)$svTid ?>" <?= (int)($case['technician_id'] ?? 0) === (int)$svTid ? 'selected' : '' ?>><?= htmlspecialchars($svTn, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <button style="border:0;background:#4F46E5;color:#fff;border-radius:8px;padding:7px 14px;font-size:13px;font-weight:600;cursor:pointer">OK</button>
        </form>
      </div>
    </div>

    <div style="margin-top:14px;border:1px solid #EEF1F6;border-radius:10px;padding:14px">
      <b style="font-size:13px">📜 ქრონოლოგია</b>
      <ul style="list-style:none;margin:10px 0 0;padding:0;font-size:13px">
        <?php foreach ($svActs as $svA): ?>
        <li style="padding:6px 0;border-bottom:1px dashed #EEF1F6">
          <?= $svTypeIco[$svA['type']] ?? '·' ?> <b><?= htmlspecialchars(substr((string)$svA['created_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?></b>
          · <?= htmlspecialchars($svA['full_name'] ?? 'სისტემა', ENT_QUOTES, 'UTF-8') ?>
          — <?= htmlspecialchars($svA['note'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </li>
        <?php endforeach; if (!$svActs): ?><li style="color:#94A3B8">ჩანაწერები არ არის</li><?php endif; ?>
      </ul>
    </div>

    <?php endif; ?>
  </div>
</div>
