<?php
/* patch_task2_hook.php — service_case.php-ში v2 პანელის ჩართვა (1 ხაზი, footer-ის წინ).
   backup + lint + rollback. გაშვების შემდეგ წაშალე. */
$f = '/home/gadgetge/public_html/Warranty/service_case.php';
$phpbin = '/opt/cpanel/ea-php82/root/usr/bin/php';
$ts = date('Ymd-His');

$s = file_get_contents($f);
if ($s === false) exit("ERR: ver wavikithxe $f\n");
if (strpos($s, 'service_v2_panel.php') !== false) exit("SKIP: hook ukve arsebobs\n");

$old = "<?php include 'includes/footer.php'; ?>";
$new = "<?php include 'includes/service_v2_panel.php'; ?>\n<?php include 'includes/footer.php'; ?>";
if (substr_count($s, $old) !== 1) exit("ERR: footer-marker ver vipove zusts erti (drift?) — gamomigzavne: grep -n footer service_case.php\n");

copy($f, "$f.bak-$ts");
file_put_contents($f, str_replace($old, $new, $s));
$o = []; $rc = 0;
exec($phpbin . ' -l ' . escapeshellarg($f) . ' 2>&1', $o, $rc);
if ($rc !== 0) { copy("$f.bak-$ts", $f); exit("ERR: lint chavarda - rollback:\n" . implode("\n", $o) . "\n"); }
echo "OK: v2 paneli chaerto service_case.php-shi (backup: $f.bak-$ts)\n";
echo implode("\n", $o) . "\n";
