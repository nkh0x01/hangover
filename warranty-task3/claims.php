<?php
/** claims.php — განაცხადების ადმინი (TASK 3): სია + approve/reject; approve → სერვის-ქეისი. */
require_once 'includes/config.php';
require_once 'includes/protection.php';
requireLogin();
blockBranchUser();
if (!isAdmin() && !isManager()) { header('Location: index.php'); exit; }

$TYPES = protIncidentTypes();
$err = null; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_verify')) { csrf_verify(); }
    $claimId = (int)($_POST['claim_id'] ?? 0);
    $act = $_POST['act'] ?? '';
    try {
        $q = $pdo->prepare("SELECT c.*, p.registration_id, p.plan_name_snapshot, p.customer_phone, p.public_token
            FROM gw_protection_claims c JOIN gw_customer_protections p ON p.id = c.protection_id
            WHERE c.id = ? LIMIT 1");
        $q->execute([$claimId]);
        $cl = $q->fetch(PDO::FETCH_ASSOC);
        if (!$cl) { $err = 'განაცხადი ვერ მოიძებნა'; }
        elseif ($act === 'reject') {
            $u = $pdo->prepare("UPDATE gw_protection_claims SET status='rejected', decided_by=?, decided_at=NOW() WHERE id=? AND status='submitted'");
            $u->execute([currentUserId(), $claimId]);
            if ($u->rowCount() > 0) {
                try { auditLog($pdo, 'protection_claim', $claimId, 'rejected', 'status', 'submitted', 'rejected'); } catch (Throwable $e) {}
                if (!empty($cl['customer_phone']) && function_exists('queueSmsNow')) {
                    queueSmsNow('claim', $cl['customer_phone'],
                        'გაჯეტი: განაცხადი #' . $claimId . ' ვერ დაკმაყოფილდა. დეტალებისთვის დაგვიკავშირდით.',
                        'claim:' . $claimId . ':rejected', 'protection_claim', $claimId);
                }
                $ok = 'განაცხადი #' . $claimId . ' უარყოფილია';
            } else { $err = 'სტატუსი უკვე შეცვლილია'; }
        } elseif ($act === 'approve') {
            if ($cl['status'] !== 'submitted') { $err = 'სტატუსი უკვე შეცვლილია'; }
            else {
                $rq = $pdo->prepare("SELECT * FROM gw_registrations WHERE id = ?");
                $rq->execute([(int)$cl['registration_id']]);
                $reg = $rq->fetch(PDO::FETCH_ASSOC) ?: [];

                $pdo->beginTransaction();
                $caseId = null;
                for ($try = 0; $try < 4; $try++) {
                    try {
                        $caseNo = protNextCaseNumber($pdo);
                        $slug = function_exists('generateServiceCaseSlug') ? generateServiceCaseSlug($pdo) : bin2hex(random_bytes(6));
                        $pdo->prepare("INSERT INTO gw_service_cases
                            (case_number, public_token, public_slug, registration_id, customer_name, customer_phone, customer_email,
                             product_name, serial_number, problem_description, visual_condition, branch_id, received_by, received_at, status,
                             coverage_source, protection_claim_id)
                            VALUES (?,?,?,?,?,?,?,?,?,?,'other',?,?,NOW(),'received','protection',?)")
                            ->execute([
                                $caseNo, bin2hex(random_bytes(24)), $slug, (int)$cl['registration_id'],
                                trim(($reg['first_name'] ?? '') . ' ' . ($reg['last_name'] ?? '')),
                                $reg['phone'] ?? $cl['customer_phone'], $reg['customer_email'] ?? null,
                                $reg['product_name'] ?? null, $reg['serial_number'] ?? null,
                                '[დაცვის განაცხადი #' . $claimId . '] ' . ($TYPES[$cl['incident_type']] ?? $cl['incident_type']) . ': ' . $cl['description'],
                                $reg['branch_id'] ?? null, currentUserId(),
                                $claimId,
                            ]);
                        $caseId = (int)$pdo->lastInsertId();
                        break;
                    } catch (PDOException $e) {
                        if ((int)($e->errorInfo[1] ?? 0) === 1062 && $try < 3) { continue; }
                        throw $e;
                    }
                }
                $pdo->prepare("UPDATE gw_protection_claims SET status='in_service', service_case_id=?, decided_by=?, decided_at=NOW() WHERE id=?")
                    ->execute([$caseId, currentUserId(), $claimId]);
                $pdo->commit();

                try { auditLog($pdo, 'protection_claim', $claimId, 'approved', 'status', 'submitted', 'in_service'); } catch (Throwable $e) {}
                if (!empty($cl['customer_phone']) && function_exists('queueSmsNow')) {
                    queueSmsNow('claim', $cl['customer_phone'],
                        'გაჯეტი: განაცხადი #' . $claimId . ' დამტკიცდა. მოიტანეთ ნივთი სერვის ცენტრში — საქმე #' . $caseNo . '.',
                        'claim:' . $claimId . ':approved', 'protection_claim', $claimId);
                }
                $ok = 'დამტკიცდა — შეიქმნა სერვის-ქეისი #' . $caseNo;
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $err = $e->getMessage();
    }
}

$f = $_GET['status'] ?? 'submitted';
if (!in_array($f, ['all', 'submitted', 'in_service', 'rejected', 'resolved'], true)) $f = 'submitted';
$rows = []; $listErr = null; $counts = [];
try {
    /* ღირებულების სინქრონიზაცია: დასრულებულ/გაცემულ ქეისებზე claim.recorded_cost ივსება */
    if (function_exists('svPartsCost')) {
        $sync = $pdo->query("SELECT c.id, c.service_case_id FROM gw_protection_claims c
            JOIN gw_service_cases s ON s.id = c.service_case_id
            WHERE c.service_case_id IS NOT NULL AND c.recorded_cost IS NULL
              AND s.status IN ('ready','returned')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sync as $s) {
            protSyncRecordedCost($pdo, (int)$s['service_case_id']);
            if (($pdo->query("SELECT status FROM gw_service_cases WHERE id=" . (int)$s['service_case_id'])->fetchColumn()) === 'returned') {
                $pdo->prepare("UPDATE gw_protection_claims SET status='resolved' WHERE id=? AND status='in_service'")->execute([(int)$s['id']]);
            }
        }
    }
    foreach ($pdo->query("SELECT status, COUNT(*) n FROM gw_protection_claims GROUP BY status") as $r) $counts[$r['status']] = (int)$r['n'];
    $sql = "SELECT c.*, p.plan_name_snapshot, p.customer_phone, p.starts_at, p.ends_at, p.price_paid,
                r.first_name, r.last_name, r.product_name, r.short_code, s.case_number, s.status AS case_status
            FROM gw_protection_claims c
            JOIN gw_customer_protections p ON p.id = c.protection_id
            LEFT JOIN gw_registrations r ON r.id = p.registration_id
            LEFT JOIN gw_service_cases s ON s.id = c.service_case_id";
    if ($f !== 'all') { $sql .= " WHERE c.status = ?"; }
    $sql .= " ORDER BY c.id DESC LIMIT 200";
    $st = $pdo->prepare($sql);
    $st->execute($f !== 'all' ? [$f] : []);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $listErr = $e->getMessage() . ' — ჯერ migrate_protection.php გაუშვი.'; }
$csrf = function_exists('csrf_field') ? csrf_field() : '';
function cm_e($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>📋 დაცვის განაცხადები</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.cm-wrap{max-width:1150px;margin:24px auto;padding:0 16px}
.cm-card{background:#fff;border:1px solid #E7EBF1;border-radius:12px;padding:18px;margin-bottom:16px}
.cm-tbl{width:100%;border-collapse:collapse;font-size:13px}
.cm-tbl th,.cm-tbl td{padding:10px 12px;border-bottom:1px solid #EEF1F6;text-align:left;vertical-align:top}
.cm-chip{display:inline-block;padding:8px 14px;border-radius:999px;border:1px solid #E7EBF1;background:#fff;text-decoration:none;color:#0F172A;font-size:13px;font-weight:600;margin-right:7px}
.cm-chip.on{background:#4F46E5;border-color:#4F46E5;color:#fff}
.cm-b{padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700}
.b-sub{background:#FEF3C7;color:#92400E}.b-serv{background:#EEF0FF;color:#4338CA}
.b-rej{background:#FEF2F2;color:#991B1B}.b-res{background:#ECFDF5;color:#047857}
.cm-btn{border:0;border-radius:8px;padding:6px 13px;font-size:12px;font-weight:600;cursor:pointer;background:#059669;color:#fff}
.cm-btn.no{background:#fff;color:#DC2626;border:1px solid #FECACA}
.cm-err{background:#FEF2F2;color:#991B1B;padding:12px;border-radius:9px;margin-bottom:14px}
.cm-ok{background:#ECFDF5;border:1px solid #A7F3D0;color:#047857;padding:11px 14px;border-radius:10px;margin-bottom:14px;font-weight:600}
.flag{background:#FFFBEB;border:1px solid #FDE68A;color:#92400E;border-radius:7px;padding:3px 8px;font-size:11px;display:inline-block;margin-top:4px}
</style></head><body>
<?php include 'includes/navbar.php'; ?>
<div class="cm-wrap">
  <h1>📋 დაცვის განაცხადები</h1>
  <?php if ($ok): ?><div class="cm-ok">✓ <?= cm_e($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="cm-err">❌ <?= cm_e($err) ?></div><?php endif; ?>
  <?php if ($listErr): ?><div class="cm-err">❌ <?= cm_e($listErr) ?></div><?php endif; ?>

  <div style="margin-bottom:16px">
    <?php foreach (['submitted' => 'შემოსული', 'in_service' => 'სერვისში', 'resolved' => 'დასრულებული', 'rejected' => 'უარყოფილი', 'all' => 'ყველა'] as $k => $lb): ?>
      <a class="cm-chip <?= $f === $k ? 'on' : '' ?>" href="claims.php?status=<?= $k ?>"><?= $lb ?><?= isset($counts[$k]) ? ' (' . $counts[$k] . ')' : '' ?></a>
    <?php endforeach; ?>
  </div>

  <div class="cm-card">
    <div style="overflow-x:auto"><table class="cm-tbl">
      <tr><th>#</th><th>მომხმარებელი</th><th>ნივთი / პაკეტი</th><th>შემთხვევა</th><th>სტატუსი</th><th>სერვისი</th><th>ღირებ.</th><th></th></tr>
      <?php foreach ($rows as $r):
        $bcls = ['submitted' => 'b-sub', 'in_service' => 'b-serv', 'rejected' => 'b-rej', 'resolved' => 'b-res'][$r['status']] ?? 'b-sub';
        $flags = protJson($r['review_flags_json']); ?>
      <tr>
        <td><?= (int)$r['id'] ?><br><small style="color:#94A3B8"><?= cm_e(substr((string)$r['created_at'], 0, 10)) ?></small></td>
        <td><?= cm_e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?><br><small style="color:#94A3B8"><?= cm_e($r['customer_phone']) ?></small></td>
        <td><?= cm_e($r['product_name'] ?: '—') ?><br><small style="color:#94A3B8"><?= cm_e($r['plan_name_snapshot']) ?> · <?= number_format((float)$r['price_paid'], 2) ?>₾</small></td>
        <td><b><?= cm_e($TYPES[$r['incident_type']] ?? $r['incident_type']) ?></b>
            <?= $r['incident_at'] ? '<br><small style="color:#94A3B8">' . cm_e($r['incident_at']) . '</small>' : '' ?>
            <div style="color:#374151;max-width:260px;margin-top:4px"><?= cm_e(mb_substr((string)$r['description'], 0, 140)) ?></div>
            <?php foreach ($flags as $fl): ?><div class="flag">⚠ <?= cm_e($fl) ?></div><?php endforeach; ?></td>
        <td><span class="cm-b <?= $bcls ?>"><?= cm_e(protClaimStatusLabel($r['status'])) ?></span></td>
        <td><?php if ($r['service_case_id']): ?>
              <a href="service_case.php?id=<?= (int)$r['service_case_id'] ?>">#<?= cm_e($r['case_number']) ?></a>
              <?php if ($r['case_status']): ?><br><small style="color:#94A3B8"><?= cm_e(function_exists('svLabel') ? svLabel($r['case_status']) : $r['case_status']) ?></small><?php endif; ?>
            <?php else: ?>—<?php endif; ?></td>
        <td><?= $r['recorded_cost'] !== null ? number_format((float)$r['recorded_cost'], 2) . '₾' : '—' ?></td>
        <td style="white-space:nowrap">
          <?php if ($r['status'] === 'submitted'): ?>
          <form method="POST" style="display:inline"><?= $csrf ?>
            <input type="hidden" name="claim_id" value="<?= (int)$r['id'] ?>">
            <button class="cm-btn" name="act" value="approve" onclick="return confirm('დამტკიცება? შეიქმნება სერვის-ქეისი და კლიენტს SMS გაეგზავნება.')">✓ დამტკიცება</button>
            <button class="cm-btn no" name="act" value="reject" onclick="return confirm('უარყოფა? კლიენტს SMS გაეგზავნება.')">✗</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; if (!$rows && !$listErr): ?><tr><td colspan="8" style="text-align:center;color:#94A3B8;padding:24px">განაცხადები არ არის</td></tr><?php endif; ?>
    </table></div>
  </div>
  <p style="color:#64748B;font-size:12px">დამტკიცებისას იქმნება სერვის-ქეისი <code>coverage_source='protection'</code>-ით. ქეისის დახურვისას ღირებულება ავტომატურად აისახება განაცხადზე.</p>
</div>
<?php include 'includes/footer.php'; ?>
</body></html>
