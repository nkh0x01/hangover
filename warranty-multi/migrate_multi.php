<?php
/**
 * migrate_multi.php — ჯგუფური საგარანტიო (idempotent, schema-safe).
 *  - gw_registration_groups
 *  - gw_registrations.group_id (INFORMATION_SCHEMA-შემოწმებით) + ინდექსი
 * გაშვება: CLI ან ბრაუზერით admin-ით.
 */
$isCli = (php_sapi_name() === 'cli');
if ($isCli) { define('CRON_RUN', true); }
require_once __DIR__ . '/includes/config.php';
if (!$isCli) {
    requireLogin();
    blockBranchUser();
    if (!isAdmin()) { header('Location: index.php'); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $csrf = function_exists('csrf_field') ? csrf_field() : '';
        echo '<meta charset="utf-8"><body style="font-family:system-ui;padding:30px"><h2>Migration: ჯგუფური საგარანტიო</h2>'
           . '<p>1 ცხრილი + 1 სვეტი. Idempotent — ხელახლა გაშვება უსაფრთხოა.</p>'
           . '<form method="POST">' . $csrf . '<button style="padding:10px 22px;font-size:15px">▶ გაშვება</button></form>';
        exit;
    }
    if (function_exists('csrf_verify')) { csrf_verify(); }
    echo '<meta charset="utf-8"><body style="font-family:ui-monospace,monospace;padding:30px;white-space:pre-wrap">';
}
$out = function ($m) use ($isCli) { echo $isCli ? $m . "\n" : htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . "\n"; };
$fail = 0;

/* 1) ცხრილი */
$sqlFile = __DIR__ . '/database_multi.sql';
if (!is_file($sqlFile)) { $out('ERR: ვერ ვიპოვე ' . $sqlFile); exit; }
foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents($sqlFile)))) as $stmt) {
    try { $pdo->exec($stmt); $out('OK: ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 62) . '…'); }
    catch (PDOException $e) {
        $code = (int)($e->errorInfo[1] ?? 0);
        if (in_array($code, [1050, 1060, 1061, 1091], true)) $out('SKIP (არსებობს): ' . substr($stmt, 0, 50) . '…');
        else { $out('ERR: ' . $e->getMessage()); $fail++; }
    }
}

/* 2) gw_registrations.group_id */
try {
    $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gw_registrations' AND COLUMN_NAME='group_id'");
    $q->execute();
    if ((int)$q->fetchColumn() > 0) { $out('SKIP: group_id უკვე არსებობს'); }
    else { $pdo->exec("ALTER TABLE gw_registrations ADD COLUMN group_id INT NULL"); $out('OK: +group_id'); }
} catch (Throwable $e) { $out('ERR (+group_id): ' . $e->getMessage()); $fail++; }

/* 3) ინდექსი group_id-ზე */
try {
    $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gw_registrations' AND INDEX_NAME='idx_reg_group'");
    $q->execute();
    if ((int)$q->fetchColumn() > 0) { $out('SKIP: idx_reg_group უკვე არსებობს'); }
    else { $pdo->exec("ALTER TABLE gw_registrations ADD INDEX idx_reg_group (group_id)"); $out('OK: +idx_reg_group'); }
} catch (Throwable $e) { $out('ERR (+idx_reg_group): ' . $e->getMessage()); $fail++; }

try { auditLog($pdo, 'migration', 0, 'run', 'migrate_multi', null, $fail ? 'partial' : 'applied'); } catch (Throwable $e) {}
$out($fail ? "---- დასრულდა შეცდომებით ($fail) — გამომიგზავნე ეს output" : '---- ✅ Applied — ჯგუფური საგარანტიო მზადაა');
