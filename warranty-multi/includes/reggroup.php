<?php
/**
 * includes/reggroup.php — რამდენიმე ნივთზე ერთი საგარანტიო (ჯგუფი). ADDITIVE.
 * თითო ნივთი ისევ ცალკე gw_registrations ჩანაწერია; ჯგუფი მხოლოდ აკავშირებს და
 * აძლევს კლიენტს ერთ public ლინკს + ერთ ხელმოწერას.
 */

/** slug, რომელიც არც რეგისტრაციებში და არც ჯგუფებში არ მეორდება (s.php ორივეს ეძებს) */
function rgUniqueSlug(PDO $pdo)
{
    for ($i = 0; $i < 40; $i++) {
        $slug = function_exists('generatePublicSlug') ? generatePublicSlug($pdo) : substr(bin2hex(random_bytes(6)), 0, 10);
        try {
            $q = $pdo->prepare("SELECT 1 FROM gw_registration_groups WHERE public_slug = ? LIMIT 1");
            $q->execute([$slug]);
            if ($q->fetchColumn()) { continue; }
        } catch (Throwable $e) { /* ცხრილი ჯერ არ არის — slug მაინც ვარგისია */ }
        return $slug;
    }
    return substr(bin2hex(random_bytes(8)), 0, 12);
}

/** გარანტიის დასრულების თარიღი — register.php-ის იდენტური ლოგიკა */
function rgWarrantyEnd(array $cat, string $purchaseDate)
{
    $days   = (int)($cat['warranty_days'] ?? 0);
    $months = (int)($cat['warranty_months'] ?? 12);
    return $days > 0
        ? date('Y-m-d', strtotime($purchaseDate . " + {$days} days"))
        : date('Y-m-d', strtotime($purchaseDate . " + {$months} months"));
}

/** ვადის ადამიანური ჩაწერა (register.php-ის იდენტური) */
function rgPeriodLabel(array $cat)
{
    $d = (int)($cat['warranty_days'] ?? 0);
    $m = (int)($cat['warranty_months'] ?? 12);
    if ($d > 0) return ($d % 7 === 0 && $d <= 28) ? ($d / 7) . ' კვირა' : $d . ' დღე';
    return ($m >= 12 && $m % 12 === 0) ? ($m / 12) . ' წელი' : $m . ' თვე';
}

/**
 * კატეგორიის ამოცნობა Fina-კოდით (config.php-ის resolver-ს ეყრდნობა).
 * დამცავია: resolver-ის დაბრუნებული ფორმა შეიძლება განსხვავდებოდეს.
 */
function rgResolveCategoryId(PDO $pdo, $code)
{
    $code = trim((string)$code);
    if ($code === '') return null;
    $r = null;
    foreach (['resolveWarrantyCategoryWithCache', 'resolveWarrantyCategoryByFinaCode'] as $fn) {
        if (!function_exists($fn)) continue;
        try { $r = $fn($pdo, $code); } catch (Throwable $e) { $r = null; }
        if (is_array($r)) {
            foreach (['category_id', 'id', 'cat_id'] as $k) {
                if (!empty($r[$k]) && is_numeric($r[$k])) return (int)$r[$k];
            }
            if (!empty($r['category']) && is_array($r['category']) && !empty($r['category']['id'])) {
                return (int)$r['category']['id'];
            }
        }
    }
    return null;
}

/** სათადარიგო: კატეგორია დასახელებით */
function rgSuggestCategoryId(PDO $pdo, $name)
{
    $name = trim((string)$name);
    if ($name === '' || !function_exists('suggestCategoryByName')) return null;
    try { $s = suggestCategoryByName($pdo, $name); } catch (Throwable $e) { return null; }
    if (is_array($s) && !empty($s['id'])) return (int)$s['id'];
    return null;
}

/** ჯგუფის ჩატვირთვა slug-ით ან token-ით, ნივთებთან ერთად */
function rgLoad(PDO $pdo, $key, $by = 'slug')
{
    $col = $by === 'token' ? 'group_token' : 'public_slug';
    $k = $by === 'token' ? preg_replace('/[^a-f0-9]/i', '', (string)$key)
                         : preg_replace('/[^A-Za-z0-9_-]/', '', (string)$key);
    if ($k === '') return null;
    try {
        $q = $pdo->prepare("SELECT * FROM gw_registration_groups WHERE $col = ? LIMIT 1");
        $q->execute([$k]);
        $g = $q->fetch(PDO::FETCH_ASSOC);
        if (!$g) return null;
        $it = $pdo->prepare("SELECT r.*, c.name AS category_name, c.warranty_months, c.warranty_days
            FROM gw_registrations r LEFT JOIN gw_categories c ON c.id = r.category_id
            WHERE r.group_id = ? AND r.deleted_at IS NULL ORDER BY r.id");
        $it->execute([(int)$g['id']]);
        $g['items'] = $it->fetchAll(PDO::FETCH_ASSOC);
        return $g;
    } catch (Throwable $e) { return null; }
}

/** ჯგუფის ნივთების რაოდენობის სინქრონიზაცია */
function rgSyncCount(PDO $pdo, $groupId)
{
    try {
        $pdo->prepare("UPDATE gw_registration_groups SET item_count =
            (SELECT COUNT(*) FROM gw_registrations WHERE group_id = ? AND deleted_at IS NULL) WHERE id = ?")
            ->execute([(int)$groupId, (int)$groupId]);
    } catch (Throwable $e) {}
}

/** აქვს თუ არა ეს რეგისტრაცია ჯგუფს (ცალკეულ გვერდებზე ბანერისთვის) */
function rgGroupOf(PDO $pdo, $registrationId)
{
    try {
        $q = $pdo->prepare("SELECT g.* FROM gw_registration_groups g
            JOIN gw_registrations r ON r.group_id = g.id WHERE r.id = ? LIMIT 1");
        $q->execute([(int)$registrationId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { return null; }
}
