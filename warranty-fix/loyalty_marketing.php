<?php
/* WarrantyPro / Warranty — ლოიალურობა + მარკეტინგი (F3)
   Warranty (marketing_consent) + Byte (loyalty_cards) ერთ სეგმენტაციაში, phone-ით.
   Byte-ის კრედენშალებს კითხულობს მისი .env-დან runtime-ზე (არაფერი hardcoded).
   ატვირთე Warranty/-ში, გახსენი: /loyalty_marketing.php */

require_once 'includes/config.php';
requireLogin();
blockBranchUser();
if (!isAdmin() && !isManager()) { header('Location: index.php'); exit; }

/* ── helpers (rv_ პრეფიქსი, რომ security.php-ს არ დაეჯახოს) ── */
function rv_p9($s){ $d = preg_replace('/\D/', '', (string)$s); return strlen($d) >= 9 ? substr($d, -9) : $d; }
function rv_cols(PDO $pdo, $t){
    try { $o = []; foreach ($pdo->query("SHOW COLUMNS FROM `$t`") as $c) $o[] = $c['Field']; return $o; }
    catch (Throwable $e) { return []; }
}
function rv_pick(array $cols, array $prefer, $rx = null){
    foreach ($prefer as $p) if (in_array($p, $cols, true)) return $p;
    if ($rx) foreach ($cols as $c) if (preg_match($rx, $c)) return $c;
    return null;
}

$fatal = null; $byteErr = null; $byteNote = null;

/* ── Byte DB კავშირი (Laravel .env) ── */
$bytePdo = null;
$byteEnvFile = '/home/gadgetge/byte.gadget.ge_app/.env';
try {
    if (is_file($byteEnvFile) && is_readable($byteEnvFile)) {
        $benv = [];
        foreach (file($byteEnvFile, FILE_IGNORE_NEW_LINES) as $ln) {
            $ln = trim($ln);
            if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) continue;
            [$k, $v] = explode('=', $ln, 2);
            $k = trim($k); $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'")) $v = substr($v, 1, -1);
            $benv[$k] = $v;
        }
        $bytePdo = new PDO(
            'mysql:host=' . ($benv['DB_HOST'] ?? '127.0.0.1') . ';port=' . ($benv['DB_PORT'] ?? '3306')
            . ';dbname=' . ($benv['DB_DATABASE'] ?? 'gadgetge_byte') . ';charset=utf8mb4',
            $benv['DB_USERNAME'] ?? '', $benv['DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } else {
        $byteErr = 'Byte .env ვერ მოიძებნა (' . $byteEnvFile . ') — ლოიალურობის მონაცემების გარეშე ვაჩვენებ.';
    }
} catch (Throwable $e) {
    $byteErr = 'Byte DB კავშირი ვერ შედგა: ' . $e->getMessage();
    $bytePdo = null;
}

/* ── Byte სტატისტიკა + გაწევრიანებულთა phone-სია ── */
$byteStats = ['customers' => null, 'cards' => null, 'registrations' => null, 'wallet' => null];
$cardStatusBreakdown = [];
$loyal = [];   // p9 => ['name' => ...]
if ($bytePdo) {
    foreach (['customers' => 'customers', 'cards' => 'loyalty_cards',
              'registrations' => 'loyalty_registrations', 'wallet' => 'wallet_pass_registrations'] as $key => $t) {
        try { $byteStats[$key] = (int)$bytePdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
        catch (Throwable $e) { /* ცხრილი არ არის — გამოვტოვოთ */ }
    }
    $cCols  = rv_cols($bytePdo, 'customers');
    $lcCols = rv_cols($bytePdo, 'loyalty_cards');
    $cPhone = rv_pick($cCols, ['phone', 'mobile', 'phone_number', 'msisdn'], '/phone|mobile|tel/i');
    $cName  = rv_pick($cCols, ['name', 'full_name'], null);
    $cFirst = rv_pick($cCols, ['first_name'], null);
    $cLast  = rv_pick($cCols, ['last_name'], null);
    $lcCust = rv_pick($lcCols, ['customer_id'], '/customer.*id/i');
    $lcStat = rv_pick($lcCols, ['status', 'state'], null);
    $lcPhone = rv_pick($lcCols, ['phone', 'mobile', 'phone_number'], null);

    if ($lcStat) {
        try {
            foreach ($bytePdo->query("SELECT `$lcStat` s, COUNT(*) n FROM loyalty_cards GROUP BY `$lcStat` ORDER BY n DESC") as $r)
                $cardStatusBreakdown[(string)$r['s']] = (int)$r['n'];
        } catch (Throwable $e) {}
    }

    $nameExpr = $cName ? "c.`$cName`"
              : (($cFirst && $cLast) ? "CONCAT(COALESCE(c.`$cFirst`,''),' ',COALESCE(c.`$cLast`,''))"
              : ($cFirst ? "c.`$cFirst`" : "''"));
    try {
        if ($lcCust && $cPhone) {
            $q = $bytePdo->query("SELECT DISTINCT c.`$cPhone` p, $nameExpr n FROM loyalty_cards lc JOIN customers c ON c.id = lc.`$lcCust`");
        } elseif ($lcPhone) {
            $q = $bytePdo->query("SELECT DISTINCT `$lcPhone` p, '' n FROM loyalty_cards");
        } elseif ($cPhone) {
            $q = $bytePdo->query("SELECT `$cPhone` p, $nameExpr n FROM customers");
            $byteNote = 'loyalty_cards↔customers ბმა ვერ ამოვიცანი — „გაწევრიანებულად" customers ცხრილს ვთვლი.';
        } else {
            $q = null;
            $byteErr = 'Byte-ში phone-სვეტი ვერ ვიპოვე (customers: ' . implode(', ', $cCols) . ')';
        }
        if ($q) foreach ($q as $r) { $p = rv_p9($r['p']); if (strlen($p) === 9) $loyal[$p] = ['name' => trim((string)$r['n'])]; }
    } catch (Throwable $e) {
        $byteErr = 'Byte query შეცდომა: ' . $e->getMessage();
    }
}

/* ── Warranty მომხმარებლები (უნიკალური phone-ით) ── */
$rowsAll = [];
try {
    $q = $pdo->query("SELECT phone,
            MAX(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) nm,
            COUNT(*) purchases,
            MAX(COALESCE(marketing_consent,0)) mk,
            MAX(created_at) last_p
        FROM gw_registrations
        WHERE deleted_at IS NULL AND phone IS NOT NULL AND phone <> ''
        GROUP BY phone");
    foreach ($q as $r) {
        $p = rv_p9($r['phone']);
        if (strlen($p) !== 9) continue;
        $isL = isset($loyal[$p]);
        $mk  = (int)$r['mk'] === 1;
        $rowsAll[$p] = [
            'phone' => $p, 'name' => trim((string)$r['nm']) ?: ($isL ? $loyal[$p]['name'] : ''),
            'purchases' => (int)$r['purchases'], 'last' => substr((string)$r['last_p'], 0, 10),
            'mk' => $mk, 'loyal' => $isL,
            'seg' => $mk && $isL ? 'both' : ($mk ? 'marketing' : ($isL ? 'loyalty' : 'none')),
        ];
    }
    /* Byte-ის წევრები, რომლებსაც Warranty-ში ნასყიდობა არ უფიქსირდებათ */
    foreach ($loyal as $p => $info) {
        if (!isset($rowsAll[$p])) {
            $rowsAll[$p] = ['phone' => $p, 'name' => $info['name'], 'purchases' => 0, 'last' => '',
                            'mk' => false, 'loyal' => true, 'seg' => 'byte_only'];
        }
    }
} catch (Throwable $e) {
    $fatal = $e->getMessage();
}

/* ── სეგმენტების დათვლა ── */
$segCnt = ['all' => count($rowsAll), 'both' => 0, 'marketing' => 0, 'loyalty' => 0, 'none' => 0, 'byte_only' => 0];
foreach ($rowsAll as $r) $segCnt[$r['seg']]++;

/* ── ფილტრი / ძებნა ── */
$seg = $_GET['seg'] ?? 'all';
if (!isset($segCnt[$seg])) $seg = 'all';
$qs  = trim($_GET['q'] ?? '');
$rows = array_values(array_filter($rowsAll, function ($r) use ($seg, $qs) {
    if ($seg !== 'all' && $r['seg'] !== $seg) return false;
    if ($qs !== '' && stripos($r['name'], $qs) === false && strpos($r['phone'], preg_replace('/\D/', '', $qs) ?: $qs) === false) return false;
    return true;
}));
usort($rows, function ($a, $b) { return strcmp($b['last'], $a['last']); });

$segLabels = ['all' => 'ყველა', 'both' => 'მარკეტინგი + ლოიალურობა', 'marketing' => 'მხოლოდ მარკეტინგი',
              'loyalty' => 'მხოლოდ ლოიალურობა', 'none' => 'არაფერი', 'byte_only' => 'ლოიალურობა (უნასყიდობო)'];

/* ── CSV export ── */
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="loyalty_marketing_' . $seg . '_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM Excel-ისთვის
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ტელეფონი', 'სახელი', 'ნასყიდობები', 'ბოლო ნასყიდობა', 'მარკეტინგი', 'ლოიალურობა', 'სეგმენტი']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['phone'], $r['name'], $r['purchases'], $r['last'] ?: '—',
                       $r['mk'] ? 'კი' : 'არა', $r['loyal'] ? 'კი' : 'არა', $segLabels[$r['seg']]]);
    }
    fclose($out); exit;
}

$shown = array_slice($rows, 0, 500);
function rv_url($params){ return 'loyalty_marketing.php?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null)); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>💳 ლოიალურობა და მარკეტინგი</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>#pg{--ink:#0F172A;--muted:#64748B;--line:#E7EBF1;--bg:#F6F8FC;--primary:#4F46E5;--green:#059669;--red:#DC2626;--amber:#D97706}#pg, #pg *{box-sizing:border-box;margin:0;padding:0}#pg{font-family:system-ui,"Noto Sans Georgian",sans-serif;background:var(--bg);color:var(--ink);font-size:14px}#pg .top{background:#fff;border-bottom:1px solid var(--line);padding:14px 22px;display:flex;justify-content:space-between;align-items:center}#pg .top a{color:var(--muted);text-decoration:none;font-size:13px}#pg{max-width:1150px;margin:0 auto;padding:22px}#pg h1{font-size:22px;margin-bottom:4px}#pg .sub{color:var(--muted);margin-bottom:20px}#pg .cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}#pg .card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px}#pg .card .v{font-size:26px;font-weight:800}#pg .card .l{color:var(--muted);font-size:12px;margin-top:2px}#pg .chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}#pg .chip{display:inline-block;padding:9px 15px;border-radius:999px;border:1px solid var(--line);background:#fff;
        text-decoration:none;color:var(--ink);font-size:13px;font-weight:600}#pg .chip small{color:var(--muted);font-weight:700}#pg .chip.on{background:var(--primary);border-color:var(--primary);color:#fff}#pg .chip.on small{color:#C7D2FE}#pg .bar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}#pg .bar input{padding:9px 12px;border:1px solid var(--line);border-radius:9px;font-size:13px;font-family:inherit;min-width:220px}#pg .btn{background:var(--primary);color:#fff;border:0;border-radius:9px;padding:9px 16px;font-weight:600;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block}#pg .btn.gray{background:#fff;color:var(--muted);border:1px solid var(--line)}#pg .panel{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden}#pg .panel h2{font-size:15px;padding:13px 16px;border-bottom:1px solid var(--line);background:#FAFBFE}#pg table{width:100%;border-collapse:collapse}#pg th, #pg td{padding:10px 14px;text-align:left;border-bottom:1px solid #EEF1F6;font-size:13px}#pg th{background:#FAFBFE;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.03em}#pg .y{color:var(--green);font-weight:700}#pg .n{color:#CBD5E1}#pg .warn{background:#FFFBEB;border:1px solid #FDE68A;color:#92400E;padding:12px 14px;border-radius:10px;margin-bottom:16px;font-size:13px}#pg .err{background:#FEF2F2;color:#991B1B;padding:13px;border-radius:10px;margin-bottom:16px;font-family:monospace;font-size:12.5px}#pg .statuses{color:var(--muted);font-size:12px;margin:-8px 0 16px}@media(max-width:820px){.cards{grid-template-columns:repeat(2,1fr)}}
</style></head><body>
<?php include 'includes/navbar.php'; ?><div class="wrap" id="pg">
  <h1>ლოიალურობა (Byte) + მარკეტინგი (Warranty)</h1>
  <p class="sub">ვინ არის გაწევრიანებული, ვის აქვს მარკეტინგი ჩართული და ვის — არაფერი. კავშირი ტელეფონის ნომრით.</p>

<?php if ($fatal): ?><div class="err">❌ <?= e($fatal) ?><br>(ჩამომიგზავნე ეს ტექსტი)</div><?php endif; ?>
<?php if ($byteErr): ?><div class="err">⚠️ Byte: <?= e($byteErr) ?></div><?php endif; ?>
<?php if ($byteNote): ?><div class="warn">ℹ️ <?= e($byteNote) ?></div><?php endif; ?>

  <div class="cards">
    <div class="card"><div class="v"><?= $byteStats['cards'] !== null ? number_format($byteStats['cards']) : '—' ?></div><div class="l">ლოიალურობის ბარათი (Byte)</div></div>
    <div class="card"><div class="v"><?= $byteStats['customers'] !== null ? number_format($byteStats['customers']) : '—' ?></div><div class="l">მომხმარებელი Byte-ში</div></div>
    <div class="card"><div class="v"><?= $byteStats['wallet'] !== null ? number_format($byteStats['wallet']) : '—' ?></div><div class="l">Wallet-პასი (Apple/Google)</div></div>
    <div class="card"><div class="v"><?= number_format($segCnt['all']) ?></div><div class="l">უნიკალური მომხმარებელი სულ</div></div>
  </div>
<?php if ($cardStatusBreakdown): ?>
  <p class="statuses">ბარათების სტატუსები:
    <?php $i = 0; foreach ($cardStatusBreakdown as $s => $n): ?><?= $i++ ? ' · ' : '' ?><b><?= e($s ?: '(ცარიელი)') ?></b>: <?= number_format($n) ?><?php endforeach; ?>
  </p>
<?php endif; ?>

  <div class="chips">
    <?php foreach ($segLabels as $k => $lb): ?>
      <a class="chip <?= $seg === $k ? 'on' : '' ?>" href="<?= e(rv_url(['seg' => $k, 'q' => $qs])) ?>"><?= e($lb) ?> <small><?= number_format($segCnt[$k]) ?></small></a>
    <?php endforeach; ?>
  </div>

  <form class="bar" method="GET">
    <input type="hidden" name="seg" value="<?= e($seg) ?>">
    <input name="q" value="<?= e($qs) ?>" placeholder="ძებნა: სახელი ან ტელეფონი…">
    <button class="btn">ძებნა</button>
    <a class="btn gray" href="<?= e(rv_url(['seg' => $seg, 'q' => $qs, 'export' => 1])) ?>">⬇ CSV ექსპორტი (<?= number_format(count($rows)) ?>)</a>
  </form>

  <div class="panel">
    <h2><?= e($segLabels[$seg]) ?> — ნაჩვენებია <?= number_format(count($shown)) ?><?= count($rows) > count($shown) ? ' / ' . number_format(count($rows)) . ' (სრული სია CSV-ში)' : '' ?></h2>
    <div style="overflow-x:auto"><table>
      <tr><th>მომხმარებელი</th><th>ტელეფონი</th><th>ნასყიდობა</th><th>ბოლო ნასყიდობა</th><th>მარკეტინგი</th><th>ლოიალურობა</th></tr>
      <?php foreach ($shown as $r): ?>
        <tr>
          <td><?= e($r['name'] ?: '—') ?></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= $r['purchases'] ?: '—' ?></td>
          <td><?= e($r['last'] ?: '—') ?></td>
          <td class="<?= $r['mk'] ? 'y' : 'n' ?>"><?= $r['mk'] ? '✓ ჩართულია' : '—' ?></td>
          <td class="<?= $r['loyal'] ? 'y' : 'n' ?>"><?= $r['loyal'] ? '✓ წევრია' : '—' ?></td>
        </tr>
      <?php endforeach; if (!$shown): ?>
        <tr><td colspan="6" style="text-align:center;color:#94A3B8;padding:26px">ამ სეგმენტში ვერავინ მოიძებნა</td></tr>
      <?php endif; ?>
    </table></div>
  </div>
</div><?php include 'includes/footer.php'; ?>
</body></html>
