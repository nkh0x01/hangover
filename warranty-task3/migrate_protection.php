<?php
/**
 * migrate_protection.php — TASK 3 migration (idempotent, schema-safe).
 *  - database_protection.sql: 3 ცხრილი
 *  - gw_service_cases.protection_claim_id (INFORMATION_SCHEMA-შემოწმებით)
 *  - სადემონსტრაციო გეგმა, თუ ცხრილი ცარიელია (is_active=0 — ადმინი თვითონ ჩართავს)
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
        echo '<meta charset="utf-8"><body style="font-family:system-ui;padding:30px"><h2>Migration: Protection (TASK 3)</h2>'
           . '<p>3 ახალი ცხრილი + 1 სვეტი. Idempotent — ხელახლა გაშვება უსაფრთხოა.</p>'
           . '<form method="POST">' . $csrf . '<button style="padding:10px 22px;font-size:15px">▶ გაშვება</button></form>';
        exit;
    }
    if (function_exists('csrf_verify')) { csrf_verify(); }
    echo '<meta charset="utf-8"><body style="font-family:ui-monospace,monospace;padding:30px;white-space:pre-wrap">';
}
$out = function ($m) use ($isCli) { echo $isCli ? $m . "\n" : htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . "\n"; };
$fail = 0;

/* 1) ცხრილები */
$sqlFile = __DIR__ . '/database_protection.sql';
if (!is_file($sqlFile)) { $out('ERR: ვერ ვიპოვე ' . $sqlFile); exit; }
foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents($sqlFile)))) as $stmt) {
    try { $pdo->exec($stmt); $out('OK: ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 62) . '…'); }
    catch (PDOException $e) {
        $code = (int)($e->errorInfo[1] ?? 0);
        if (in_array($code, [1050, 1060, 1061, 1091], true)) $out('SKIP (არსებობს): ' . substr($stmt, 0, 50) . '…');
        else { $out('ERR: ' . $e->getMessage()); $fail++; }
    }
}

/* 2) gw_service_cases.protection_claim_id */
try {
    $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gw_service_cases' AND COLUMN_NAME='protection_claim_id'");
    $q->execute();
    if ((int)$q->fetchColumn() > 0) { $out('SKIP: protection_claim_id უკვე არსებობს'); }
    else { $pdo->exec("ALTER TABLE gw_service_cases ADD COLUMN protection_claim_id INT NULL"); $out('OK: +protection_claim_id'); }
} catch (Throwable $e) { $out('ERR (+protection_claim_id): ' . $e->getMessage()); $fail++; }

/* 3) სადემონსტრაციო გეგმა (გამორთული) — მხოლოდ თუ ცხრილი ცარიელია */
try {
    if ((int)$pdo->query("SELECT COUNT(*) FROM gw_protection_plans")->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO gw_protection_plans
            (name, plan_type, price_type, price_value, duration_months, coverage_json, exclusions_json, terms, is_active)
            VALUES (?,?,?,?,?,?,?,?,0)")
            ->execute([
                'ეკრანის დაცვა 12 თვე', 'screen', 'percent', 12, 12,
                json_encode(['screen_damage', 'mechanical'], JSON_UNESCAPED_UNICODE),
                json_encode(['განზრახ დაზიანება', 'ქურდობა'], JSON_UNESCAPED_UNICODE),
                "დაცვა მოქმედებს შეძენიდან მითითებული ვადით. ერთი შემთხვევა პერიოდში. განზრახ დაზიანება არ იფარება.",
            ]);
        $out('OK: სადემონსტრაციო გეგმა დაემატა (გამორთული — protection_plans.php-ში ჩართე)');
    } else { $out('SKIP: გეგმები უკვე არსებობს'); }
} catch (Throwable $e) { $out('ERR (demo plan): ' . $e->getMessage()); $fail++; }

try { auditLog($pdo, 'migration', 0, 'run', 'migrate_protection', null, $fail ? 'partial' : 'applied'); } catch (Throwable $e) {}
$out($fail ? "---- დასრულდა შეცდომებით ($fail) — გამომიგზავნე ეს output" : '---- ✅ Applied — Protection schema მზადაა');
