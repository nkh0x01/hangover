<?php
/**
 * migrate_service_v2.php — TASK 2 migration (idempotent, schema-safe).
 *  - database_service_v2.sql: 3 ცხრილი (skip 1050/1060/1061/1091)
 *  - gw_service_cases.status ENUM-ის გაფართოება: +waiting_customer_approval,+waiting_part,+quality_check
 *    (არსებული მნიშვნელობები/დეფოლტი/NULL-ობა უცვლელი რჩება)
 *  - სვეტების დამატება INFORMATION_SCHEMA-შემოწმებით: technician_id, repair_finished_at, coverage_source, payment_status
 * გაშვება: CLI (php migrate_service_v2.php) ან ბრაუზერით admin-ით.
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
        echo '<meta charset="utf-8"><body style="font-family:system-ui;padding:30px"><h2>Migration: Service v2 (TASK 2)</h2>'
           . '<p>3 ახალი ცხრილი + status ENUM გაფართოება + 4 სვეტი. Idempotent.</p>'
           . '<form method="POST">' . $csrf . '<button style="padding:10px 22px;font-size:15px">▶ გაშვება</button></form>';
        exit;
    }
    if (function_exists('csrf_verify')) { csrf_verify(); }
    echo '<meta charset="utf-8"><body style="font-family:ui-monospace,monospace;padding:30px;white-space:pre-wrap">';
}

$out = function ($m) use ($isCli) { echo $isCli ? $m . "\n" : htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . "\n"; };
$fail = 0;

/* 1) ცხრილები SQL ფაილიდან */
$sqlFile = __DIR__ . '/database_service_v2.sql';
if (!is_file($sqlFile)) { $out("ERR: ვერ ვიპოვე $sqlFile"); exit; }
foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents($sqlFile)))) as $stmt) {
    try { $pdo->exec($stmt); $out('OK: ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 60) . '…'); }
    catch (PDOException $e) {
        $code = (int)($e->errorInfo[1] ?? 0);
        if (in_array($code, [1050, 1060, 1061, 1091], true)) $out('SKIP (არსებობს): ' . substr($stmt, 0, 50) . '…');
        else { $out('ERR: ' . $e->getMessage()); $fail++; }
    }
}

/* 2) status ENUM-ის გაფართოება — არსებულის შენარჩუნებით */
try {
    $col = $pdo->query("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gw_service_cases' AND COLUMN_NAME='status'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) { $out('ERR: status სვეტი ვერ მოიძებნა'); $fail++; }
    else {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $col['COLUMN_TYPE'], $m);
        $vals = $m[1];
        $need = ['waiting_customer_approval', 'waiting_part', 'quality_check'];
        $missing = array_values(array_diff($need, $vals));
        if (!$missing) { $out('SKIP: status ENUM უკვე გაფართოებულია'); }
        else {
            $newVals = array_merge($vals, $missing);
            $enum = "ENUM('" . implode("','", array_map(fn($v) => str_replace("'", "''", $v), $newVals)) . "')";
            $nullSql = ($col['IS_NULLABLE'] === 'NO') ? ' NOT NULL' : ' NULL';
            $defSql = '';
            if ($col['COLUMN_DEFAULT'] !== null) {
                $d = trim((string)$col['COLUMN_DEFAULT'], "'");
                if (strtoupper($d) !== 'NULL') $defSql = " DEFAULT '" . str_replace("'", "''", $d) . "'";
            }
            $pdo->exec("ALTER TABLE gw_service_cases MODIFY status $enum$nullSql$defSql");
            $out('OK: status ENUM +' . implode(', +', $missing));
        }
    }
} catch (Throwable $e) { $out('ERR (enum): ' . $e->getMessage()); $fail++; }

/* 3) სვეტების დამატება — INFORMATION_SCHEMA შემოწმებით */
$addCols = [
    'technician_id'      => 'INT NULL',
    'repair_finished_at' => 'DATETIME NULL',
    'coverage_source'    => 'VARCHAR(16) NULL',
    'payment_status'     => 'VARCHAR(12) NULL',
];
foreach ($addCols as $name => $ddl) {
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gw_service_cases' AND COLUMN_NAME=?");
        $q->execute([$name]);
        if ((int)$q->fetchColumn() > 0) { $out("SKIP: $name უკვე არსებობს"); continue; }
        $pdo->exec("ALTER TABLE gw_service_cases ADD COLUMN `$name` $ddl");
        $out("OK: +$name");
    } catch (Throwable $e) { $out("ERR (+$name): " . $e->getMessage()); $fail++; }
}

try { auditLog($pdo, 'migration', 0, 'run', 'migrate_service_v2', null, $fail ? 'partial' : 'applied'); } catch (Throwable $e) {}
$out($fail ? "---- დასრულდა შეცდომებით ($fail) — გამომიგზავნე ეს output" : '---- ✅ Applied — Service v2 schema მზადაა');
