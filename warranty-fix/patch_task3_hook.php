<?php
/* patch_task3_hook.php — TASK 3-ის ორი მინიმალური, ADDITIVE ცვლილება ცოცხალ კოდში:
     1) includes/bog_pay.php  — redirect URL-ების არასავალდებულო override (ძველი ქცევა უცვლელი)
     2) bog_payment_callback.php — "PROT-" order-ების ცალკე მარშრუტი (extension flow-ს არ ეხება)
   თითო ფაილზე: backup + lint + rollback. გაშვების შემდეგ წაშალე. */

$root   = '/home/gadgetge/public_html/Warranty';
$phpbin = '/opt/cpanel/ea-php82/root/usr/bin/php';
$ts = date('Ymd-His');
$done = 0; $skip = 0; $fail = 0;

function t3lint($phpbin, $f) { $o = []; $rc = 0; exec($phpbin . ' -l ' . escapeshellarg($f) . ' 2>&1', $o, $rc); return [$rc === 0, implode("\n", $o)]; }

function t3patch($root, $phpbin, $ts, $rel, $marker, $old, $new, &$done, &$skip, &$fail)
{
    $f = "$root/$rel";
    $s = @file_get_contents($f);
    if ($s === false) { echo "FAIL $rel: ver wavikithxe\n"; $fail++; return; }
    if (strpos($s, $marker) !== false) { echo "SKIP $rel: ukve daemata\n"; $skip++; return; }
    $cnt = substr_count($s, $old);
    if ($cnt !== 1) { echo "FAIL $rel: samizne bloki moidzebna $cnt-jer (unda iyos 1) — drift?\n"; $fail++; return; }
    copy($f, "$f.bak-$ts");
    file_put_contents($f, str_replace($old, $new, $s));
    [$ok, $out] = t3lint($phpbin, $f);
    if (!$ok) { copy("$f.bak-$ts", $f); echo "FAIL $rel: lint chavarda — rollback\n$out\n"; $fail++; return; }
    echo "OK   $rel  (backup: $rel.bak-$ts)\n"; $done++;
}

/* ── 1) bog_pay.php: redirect override ───────────────────────────────── */
$old1 = "            'redirect_urls'  => [\n"
      . "                'success' => SITE_URL . '/warranty_extend.php?payment=success&ref=' . urlencode(\$params['external_order_id']),\n"
      . "                'fail'    => SITE_URL . '/warranty_extend.php?id=' . \$params['reg_id'] . '&payment=fail',\n"
      . "            ],";
$new1 = "            'redirect_urls'  => [\n"
      . "                // TASK 3: არასავალდებულო override; გადაცემის გარეშე ქცევა უცვლელია\n"
      . "                'success' => \$params['success_url'] ?? (SITE_URL . '/warranty_extend.php?payment=success&ref=' . urlencode(\$params['external_order_id'])),\n"
      . "                'fail'    => \$params['fail_url'] ?? (SITE_URL . '/warranty_extend.php?id=' . (\$params['reg_id'] ?? '') . '&payment=fail'),\n"
      . "            ],";
t3patch($root, $phpbin, $ts, 'includes/bog_pay.php', "success_url", $old1, $new1, $done, $skip, $fail);

/* ── 2) bog_payment_callback.php: PROT- მარშრუტი ─────────────────────── */
$old2 = "if (!\$externalOrderId || !\$status) {\n"
      . "    http_response_code(400); die('Missing fields');\n"
      . "}";
$new2 = "if (!\$externalOrderId || !\$status) {\n"
      . "    http_response_code(400); die('Missing fields');\n"
      . "}\n\n"
      . "// ── TASK 3: დაცვის პაკეტის გადახდა ცალკე ჰენდლერზე (extension flow ხელუხლებელია) ──\n"
      . "if (strncmp(\$externalOrderId, 'PROT-', 5) === 0) {\n"
      . "    require_once __DIR__ . '/includes/protection.php';\n"
      . "    try {\n"
      . "        protectionHandleBogCallback(\$pdo, \$bogOrderId, \$externalOrderId, \$status, \$raw);\n"
      . "    } catch (Throwable \$e) {\n"
      . "        error_log('PROT callback fatal: ' . \$e->getMessage());\n"
      . "    }\n"
      . "    http_response_code(200); echo 'OK'; exit;\n"
      . "}";
t3patch($root, $phpbin, $ts, 'bog_payment_callback.php', "PROT-", $old2, $new2, $done, $skip, $fail);

echo "----\nDONE patch_task3: OK=$done SKIP=$skip FAIL=$fail\n";
if ($fail) { echo "⚠️ ჩავარდნილი ფაილები უცვლელი დარჩა (rollback). გამომიგზავნე ეს output.\n"; }
