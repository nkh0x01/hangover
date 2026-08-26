<?php
/* WarrantyPro / Warranty — შეფასებების ანალიტიკა (F1)
   ფილიალი / თანამშრომელი / შენაძენი / მომხმარებელი + თანამშრომლების რეიტინგი.
   ატვირთე Warranty/ საქაღალდეში, გახსენი: /reviews_analytics.php */

require_once 'includes/config.php';
requireLogin();
blockBranchUser();
if (!isAdmin() && !isManager()) { header('Location: index.php'); exit; }

function rv_stars($n){ $n=(int)$n; return $n>0 ? str_repeat('★',$n) . str_repeat('☆',max(0,5-$n)) : '—'; }

$fBranch = ($_GET['branch'] ?? '') !== '' ? (int)$_GET['branch'] : null;
$fEmp    = ($_GET['emp'] ?? '') !== '' ? (int)$_GET['emp'] : null;
$fSent   = $_GET['sent'] ?? 'all';           // all | neg | pos
$fFrom   = trim($_GET['from'] ?? '');
$fTo     = trim($_GET['to'] ?? '');

$where = ['1=1']; $params = [];
if ($fBranch !== null) { $where[] = 'r.branch_id = ?'; $params[] = $fBranch; }
if ($fEmp !== null)    { $where[] = 'r.employee_id = ?'; $params[] = $fEmp; }
if ($fSent === 'neg')  { $where[] = '(r.stars <= 2 OR r.nps_category = "detractor")'; }
if ($fSent === 'pos')  { $where[] = 'r.stars >= 4'; }
if ($fFrom !== '')     { $where[] = 'r.created_at >= ?'; $params[] = $fFrom . ' 00:00:00'; }
if ($fTo !== '')       { $where[] = 'r.created_at <= ?'; $params[] = $fTo . ' 23:59:59'; }
$W = implode(' AND ', $where);

$fatal = null;
try {
    $branches = $pdo->query("SELECT id, name FROM gw_branches ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
    $emps     = $pdo->query("SELECT id, full_name FROM gw_users ORDER BY full_name")->fetchAll(PDO::FETCH_KEY_PAIR);

    $st = $pdo->prepare("SELECT COUNT(*) n, ROUND(AVG(stars),2) avg_stars,
        SUM(stars<=2) neg, SUM(stars>=4) pos, SUM(is_addressed=1) addressed
        FROM gw_ratings r WHERE $W");
    $st->execute($params); $S = $st->fetch(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT r.employee_id, u.full_name, COUNT(*) n, ROUND(AVG(r.stars),2) avg_stars, SUM(r.stars<=2) neg
        FROM gw_ratings r LEFT JOIN gw_users u ON u.id=r.employee_id
        WHERE $W AND r.employee_id IS NOT NULL
        GROUP BY r.employee_id, u.full_name HAVING n > 0 ORDER BY avg_stars DESC, n DESC");
    $st->execute($params); $empRank = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT r.branch_id, b.name, COUNT(*) n, ROUND(AVG(r.stars),2) avg_stars, SUM(r.stars<=2) neg
        FROM gw_ratings r LEFT JOIN gw_branches b ON b.id=r.branch_id
        WHERE $W GROUP BY r.branch_id, b.name ORDER BY n DESC");
    $st->execute($params); $brRank = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT r.*, u.full_name emp_name, b.name branch_name,
        reg.product_name, reg.short_code, reg.purchase_date, reg.first_name, reg.last_name
        FROM gw_ratings r
        LEFT JOIN gw_users u ON u.id=r.employee_id
        LEFT JOIN gw_branches b ON b.id=r.branch_id
        LEFT JOIN gw_registrations reg ON reg.id=r.reference_id AND r.type='warranty'
        WHERE $W ORDER BY r.created_at DESC LIMIT 300");
    $st->execute($params); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    $fatal = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>შეფასებების ანალიტიკა</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>#pg{--ink:#0F172A;--muted:#64748B;--line:#E7EBF1;--bg:#F6F8FC;--primary:#4F46E5;--red:#DC2626;--green:#059669;--amber:#D97706}#pg, #pg *{box-sizing:border-box;margin:0;padding:0}#pg{font-family:system-ui,"Noto Sans Georgian",sans-serif;background:var(--bg);color:var(--ink);font-size:14px}#pg .top{background:#fff;border-bottom:1px solid var(--line);padding:14px 22px;display:flex;justify-content:space-between;align-items:center}#pg .top a{color:var(--muted);text-decoration:none;font-size:13px}#pg{max-width:1200px;margin:0 auto;padding:22px}#pg h1{font-size:22px;margin-bottom:4px}#pg .sub{color:var(--muted);margin-bottom:20px}#pg .cards{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px}#pg .card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px}#pg .card .v{font-size:26px;font-weight:800}#pg .card .l{color:var(--muted);font-size:12px;margin-top:2px}#pg .card.red .v{color:var(--red)}#pg .grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px}#pg .panel{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden}#pg .panel h2{font-size:15px;padding:14px 16px;border-bottom:1px solid var(--line);background:#FAFBFE}#pg table{width:100%;border-collapse:collapse}#pg th, #pg td{padding:10px 14px;text-align:left;border-bottom:1px solid #EEF1F6;font-size:13px;vertical-align:top}#pg th{background:#FAFBFE;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.03em}#pg .stars{color:var(--amber);letter-spacing:1px;white-space:nowrap}#pg .neg{color:var(--red);font-weight:700}#pg .badge{padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600}#pg .b-neg{background:#FEF2F2;color:var(--red)}#pg .b-pos{background:#ECFDF5;color:var(--green)}#pg .b-mid{background:#FEF3C7;color:var(--amber)}#pg form.filters{background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:20px}#pg .f{display:flex;flex-direction:column;gap:4px}#pg .f label{font-size:11px;color:var(--muted);font-weight:600}#pg .f select, #pg .f input{padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px;font-family:inherit}#pg .btn{background:var(--primary);color:#fff;border:0;border-radius:8px;padding:9px 18px;font-weight:600;cursor:pointer;font-size:13px}#pg .btn.clear{background:#fff;color:var(--muted);border:1px solid var(--line)}#pg .comment{color:#374151;max-width:280px}#pg .err{background:#FEF2F2;color:#991B1B;padding:14px;border-radius:10px;margin-bottom:18px;font-family:monospace;font-size:13px}@media(max-width:900px){.cards{grid-template-columns:repeat(2,1fr)}.grid2{grid-template-columns:1fr}}
</style></head><body>
<?php include 'includes/navbar.php'; ?><div class="wrap" id="pg">
  <h1>შეფასებები და თანამშრომლების რეიტინგი</h1>
  <p class="sub">ვინ, სად, რომელი შენაძენის შემდეგ და როგორ შეაფასა.</p>

<?php if ($fatal): ?>
  <div class="err">❌ შეცდომა: <?= e($fatal) ?><br>(გამომიგზავნე ეს ტექსტი — გავასწორებ)</div>
<?php else: ?>

  <form class="filters" method="GET">
    <div class="f"><label>ფილიალი</label><select name="branch"><option value="">ყველა</option>
      <?php foreach ($branches as $id=>$nm): ?><option value="<?= (int)$id ?>" <?= $fBranch===(int)$id?'selected':'' ?>><?= e($nm) ?></option><?php endforeach; ?>
    </select></div>
    <div class="f"><label>თანამშრომელი</label><select name="emp"><option value="">ყველა</option>
      <?php foreach ($emps as $id=>$nm): ?><option value="<?= (int)$id ?>" <?= $fEmp===(int)$id?'selected':'' ?>><?= e($nm) ?></option><?php endforeach; ?>
    </select></div>
    <div class="f"><label>შეფასება</label><select name="sent">
      <option value="all" <?= $fSent==='all'?'selected':'' ?>>ყველა</option>
      <option value="neg" <?= $fSent==='neg'?'selected':'' ?>>უარყოფითი (≤2★)</option>
      <option value="pos" <?= $fSent==='pos'?'selected':'' ?>>დადებითი (≥4★)</option>
    </select></div>
    <div class="f"><label>დან</label><input type="date" name="from" value="<?= e($fFrom) ?>"></div>
    <div class="f"><label>მდე</label><input type="date" name="to" value="<?= e($fTo) ?>"></div>
    <button class="btn">ფილტრი</button>
    <a class="btn clear" href="reviews_analytics.php">გასუფთავება</a>
  </form>

  <div class="cards">
    <div class="card"><div class="v"><?= (int)($S['n']??0) ?></div><div class="l">სულ შეფასება</div></div>
    <div class="card"><div class="v"><?= $S['avg_stars']!==null?e($S['avg_stars']):'—' ?></div><div class="l">საშ. ვარსკვლავი</div></div>
    <div class="card red"><div class="v"><?= (int)($S['neg']??0) ?></div><div class="l">უარყოფითი (≤2★)</div></div>
    <div class="card"><div class="v"><?= (int)($S['pos']??0) ?></div><div class="l">დადებითი (≥4★)</div></div>
    <div class="card"><div class="v"><?= (int)($S['addressed']??0) ?></div><div class="l">დამუშავებული</div></div>
  </div>

  <div class="grid2">
    <div class="panel"><h2>🏆 თანამშრომლების რეიტინგი</h2>
      <table><tr><th>თანამშრომელი</th><th>შეფასება</th><th>საშ.★</th><th>უარყ.</th></tr>
      <?php foreach ($empRank as $r): ?>
        <tr><td><?= e($r['full_name'] ?: '#'.$r['employee_id']) ?></td><td><?= (int)$r['n'] ?></td>
        <td class="stars"><?= e($r['avg_stars']) ?></td><td class="<?= $r['neg']>0?'neg':'' ?>"><?= (int)$r['neg'] ?></td></tr>
      <?php endforeach; if(!$empRank): ?><tr><td colspan="4" style="color:#94A3B8">მონაცემი არ არის</td></tr><?php endif; ?>
      </table>
    </div>
    <div class="panel"><h2>🏪 ფილიალების ჭრილი</h2>
      <table><tr><th>ფილიალი</th><th>შეფასება</th><th>საშ.★</th><th>უარყ.</th></tr>
      <?php foreach ($brRank as $r): ?>
        <tr><td><?= e($r['name'] ?: '#'.$r['branch_id']) ?></td><td><?= (int)$r['n'] ?></td>
        <td class="stars"><?= e($r['avg_stars']) ?></td><td class="<?= $r['neg']>0?'neg':'' ?>"><?= (int)$r['neg'] ?></td></tr>
      <?php endforeach; if(!$brRank): ?><tr><td colspan="4" style="color:#94A3B8">მონაცემი არ არის</td></tr><?php endif; ?>
      </table>
    </div>
  </div>

  <div class="panel"><h2>📝 შეფასებები (<?= count($rows) ?><?= count($rows)>=300?'+':'' ?>)</h2>
    <div style="overflow-x:auto"><table>
      <tr><th>თარიღი</th><th>ფილიალი</th><th>თანამშრომელი</th><th>მომხმარებელი</th><th>შენაძენი</th><th>შეფასება</th><th>NPS</th><th>კომენტარი</th><th>სტატუსი</th></tr>
      <?php foreach ($rows as $r):
        $isNeg = ($r['stars']!==null && $r['stars']<=2) || ($r['nps_category']??'')==='detractor'; ?>
        <tr>
          <td><?= e(substr($r['created_at'],0,16)) ?></td>
          <td><?= e($r['branch_name'] ?: ($r['branch_id']?'#'.$r['branch_id']:'—')) ?></td>
          <td><?= e($r['emp_name'] ?: ($r['employee_id']?'#'.$r['employee_id']:'—')) ?></td>
          <td><?= e(trim(($r['customer_name'] ?: trim(($r['first_name']??'').' '.($r['last_name']??''))) )) ?><br><small style="color:#94A3B8"><?= e($r['customer_phone']) ?></small></td>
          <td><?= e($r['product_name'] ?: '—') ?><?= $r['short_code']?'<br><small style="color:#94A3B8">#'.e($r['short_code']).'</small>':'' ?></td>
          <td class="stars"><?= rv_stars($r['stars']) ?></td>
          <td><?php if($r['nps_score']!==null): ?><span class="badge <?= ($r['nps_category']??'')==='detractor'?'b-neg':((($r['nps_category']??'')==='promoter')?'b-pos':'b-mid') ?>"><?= (int)$r['nps_score'] ?></span><?php else: ?>—<?php endif; ?></td>
          <td class="comment <?= $isNeg?'neg':'' ?>"><?= e($r['comment']) ?: '—' ?></td>
          <td><?= $r['is_addressed']? '<span class="badge b-pos">✓</span>' : ($isNeg?'<span class="badge b-neg">ღია</span>':'—') ?></td>
        </tr>
      <?php endforeach; if(!$rows): ?><tr><td colspan="9" style="text-align:center;color:#94A3B8;padding:26px">შეფასება ვერ მოიძებნა</td></tr><?php endif; ?>
    </table></div>
  </div>
<?php endif; ?>
</div><?php include 'includes/footer.php'; ?>
</body></html>
