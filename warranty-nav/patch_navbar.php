<?php
/* patch_navbar.php — ახალი გვერდების ლინკები საიდბარში (TASK 1/2/3 + ანალიტიკა).
   ყველა ლინკი file_exists-შემოწმებით: გამოჩნდება მხოლოდ მაშინ, როცა ფაილი ადგილზეა.
   backup + lint + rollback. იდემპოტენტური. გაშვების შემდეგ წაშალე. */

$root   = '/home/gadgetge/public_html/Warranty';
$phpbin = '/opt/cpanel/ea-php82/root/usr/bin/php';
$f  = "$root/includes/navbar.php";
$ts = date('Ymd-His');

$s = @file_get_contents($f);
if ($s === false) exit("ERR: ver wavikithxe $f\n");
if (strpos($s, 'reviews_analytics.php') !== false) exit("SKIP: navbar-shi ukve daemata\n");

/* ── 1) ანალიტიკა (admin + manager) — nps_dashboard-ის შემდეგ ── */
$anchor1 = "        <a href=\"<?=SITE_URL?>/nps_dashboard.php\" class=\"nav-item <?=\$cp==='nps_dashboard.php'?'active':''?>\">\n"
         . "            <span class=\"ni\">⭐</span> NPS / შეფასებები\n"
         . "        </a>";
$add1 = $anchor1 . "\n"
      . "        <?php if (file_exists(__DIR__ . '/../reviews_analytics.php')): ?>\n"
      . "        <a href=\"<?=SITE_URL?>/reviews_analytics.php\" class=\"nav-item <?=\$cp==='reviews_analytics.php'?'active':''?>\">\n"
      . "            <span class=\"ni\">📈</span> შეფასებების ანალიტიკა\n"
      . "        </a>\n"
      . "        <?php endif; ?>\n"
      . "        <?php if (file_exists(__DIR__ . '/../loyalty_marketing.php')): ?>\n"
      . "        <a href=\"<?=SITE_URL?>/loyalty_marketing.php\" class=\"nav-item <?=\$cp==='loyalty_marketing.php'?'active':''?>\">\n"
      . "            <span class=\"ni\">💳</span> ლოიალურობა და მარკეტინგი\n"
      . "        </a>\n"
      . "        <?php endif; ?>\n"
      . "        <?php if (file_exists(__DIR__ . '/../claims.php')): ?>\n"
      . "        <a href=\"<?=SITE_URL?>/claims.php\" class=\"nav-item <?=\$cp==='claims.php'?'active':''?>\">\n"
      . "            <span class=\"ni\">📋</span> დაცვის განაცხადები\n"
      . "        </a>\n"
      . "        <?php endif; ?>";

/* ── 2) მართვა (admin) — კუპონების შემდეგ ── */
$anchor2 = "        <a href=\"<?=SITE_URL?>/coupons_dashboard.php\" class=\"nav-item <?=\$cp==='coupons_dashboard.php'?'active':''?>\">\n"
         . "            <span class=\"ni\">🎟</span> კუპონები\n"
         . "        </a>";
$add2 = $anchor2 . "\n"
      . "        <?php if (file_exists(__DIR__ . '/../protection_plans.php')): ?>\n"
      . "        <a href=\"<?=SITE_URL?>/protection_plans.php\" class=\"nav-item <?=\$cp==='protection_plans.php'?'active':''?>\">\n"
      . "            <span class=\"ni\">🛡</span> დაცვის პაკეტები\n"
      . "        </a>\n"
      . "        <?php endif; ?>\n"
      . "        <?php if (file_exists(__DIR__ . '/../sms_outbox.php')): ?>\n"
      . "        <a href=\"<?=SITE_URL?>/sms_outbox.php\" class=\"nav-item <?=\$cp==='sms_outbox.php'?'active':''?>\">\n"
      . "            <span class=\"ni\">📨</span> SMS რიგი\n"
      . "        </a>\n"
      . "        <?php endif; ?>";

foreach ([['ანალიტიკა', $anchor1, $add1], ['მართვა', $anchor2, $add2]] as [$lbl, $a, $n]) {
    $cnt = substr_count($s, $a);
    if ($cnt !== 1) exit("ERR: '$lbl' sekciis samizne moidzebna $cnt-jer (unda iyos 1) — navbar shecvlilia.\n");
    $s = str_replace($a, $n, $s);
}

copy($f, "$f.bak-$ts");
file_put_contents($f, $s);

$o = []; $rc = 0;
exec($phpbin . ' -l ' . escapeshellarg($f) . ' 2>&1', $o, $rc);
if ($rc !== 0) { copy("$f.bak-$ts", $f); exit("ERR: lint chavarda — rollback:\n" . implode("\n", $o) . "\n"); }

echo "OK: navbar-shi daemata 5 linki (backup: includes/navbar.php.bak-$ts)\n";
echo implode("\n", $o) . "\n";
echo "--- ekranze gamochndeba is, rac serverzea: ---\n";
foreach (['reviews_analytics.php', 'loyalty_marketing.php', 'claims.php', 'protection_plans.php', 'sms_outbox.php'] as $p) {
    echo (file_exists("$root/$p") ? '  ✓ ' : '  – (jer ar aris) ') . $p . "\n";
}
