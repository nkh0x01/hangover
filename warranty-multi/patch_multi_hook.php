<?php
/* patch_multi_hook.php — ჯგუფური საგარანტიოს ორი ADDITIVE ცვლილება:
     1) s.php            — მოკლე slug ჯგუფსაც ეძებს (რეგისტრაცია/სერვისი ჯერ, მერე ჯგუფი)
     2) includes/navbar.php — ლინკი „ჯგუფური საგარანტიო" (file_exists-შემოწმებით)
   თითო ფაილზე: backup + lint + rollback. იდემპოტენტური. გაშვების შემდეგ წაშალე. */

$root   = '/home/gadgetge/public_html/Warranty';
$phpbin = '/opt/cpanel/ea-php82/root/usr/bin/php';
$ts = date('Ymd-His');
$done = 0; $skip = 0; $fail = 0;

function mlint($phpbin, $f) { $o = []; $rc = 0; exec($phpbin . ' -l ' . escapeshellarg($f) . ' 2>&1', $o, $rc); return [$rc === 0, implode("\n", $o)]; }

function mpatch($root, $phpbin, $ts, $rel, $marker, $old, $new, &$done, &$skip, &$fail)
{
    $f = "$root/$rel";
    $s = @file_get_contents($f);
    if ($s === false) { echo "FAIL $rel: ver wavikithxe\n"; $fail++; return; }
    if (strpos($s, $marker) !== false) { echo "SKIP $rel: ukve daemata\n"; $skip++; return; }
    $cnt = substr_count($s, $old);
    if ($cnt !== 1) { echo "FAIL $rel: samizne bloki moidzebna $cnt-jer (unda iyos 1) — drift?\n"; $fail++; return; }
    copy($f, "$f.bak-$ts");
    file_put_contents($f, str_replace($old, $new, $s));
    [$ok, $out] = mlint($phpbin, $f);
    if (!$ok) { copy("$f.bak-$ts", $f); echo "FAIL $rel: lint chavarda — rollback\n$out\n"; $fail++; return; }
    echo "OK   $rel  (backup: $rel.bak-$ts)\n"; $done++;
}

/* ── 1) s.php: ჯგუფის slug ── */
$old1 = "        // Service-case sign (intake / handover act) — same short slug scheme.\n"
      . "        \$st = \$pdo->prepare(\"SELECT signature_token FROM gw_service_cases WHERE public_slug=? AND signature_token IS NOT NULL AND signature_token <> '' AND deleted_at IS NULL LIMIT 1\");\n"
      . "        \$st->execute([strtolower(\$token)]);\n"
      . "        if (\$row = \$st->fetch()) {\n"
      . "            header('Location: '.SITE_URL.'/sign.php?t='.\$row['signature_token']);\n"
      . "            exit;\n"
      . "        }";
$new1 = $old1 . "\n\n"
      . "        // ჯგუფური საგარანტიო (რამდენიმე ნივთი — ერთი ხელმოწერა), იგივე მოკლე slug.\n"
      . "        // ბოლოს მოწმდება, რომ არსებულ ლინკებს ქცევა არ შეეცვალოს.\n"
      . "        \$st = \$pdo->prepare(\"SELECT group_token FROM gw_registration_groups WHERE public_slug=? LIMIT 1\");\n"
      . "        \$st->execute([strtolower(\$token)]);\n"
      . "        if (\$row = \$st->fetch()) {\n"
      . "            header('Location: '.SITE_URL.'/sign_group.php?t='.\$row['group_token']);\n"
      . "            exit;\n"
      . "        }";
mpatch($root, $phpbin, $ts, 's.php', 'gw_registration_groups', $old1, $new1, $done, $skip, $fail);

/* ── 2) navbar.php: ლინკი ── */
$old2 = "        <a href=\"<?=SITE_URL?>/register.php\" class=\"nav-item <?=\$cp==='register.php'?'active':''?>\">\n"
      . "            <span class=\"ni\">➕</span> საგარანტიოს გამოწერა\n"
      . "        </a>";
$new2 = $old2 . "\n"
      . "        <?php if (file_exists(__DIR__ . '/../register_multi.php')): ?>\n"
      . "        <a href=\"<?=SITE_URL?>/register_multi.php\" class=\"nav-item <?=\$cp==='register_multi.php'?'active':''?>\">\n"
      . "            <span class=\"ni\">🧾</span> ჯგუფური საგარანტიო\n"
      . "        </a>\n"
      . "        <?php endif; ?>";
mpatch($root, $phpbin, $ts, 'includes/navbar.php', 'register_multi.php', $old2, $new2, $done, $skip, $fail);

echo "----\nDONE patch_multi: OK=$done SKIP=$skip FAIL=$fail\n";
if ($fail) { echo "⚠️ ჩავარდნილი ფაილები უცვლელი დარჩა (rollback). გამომიგზავნე ეს output.\n"; }
