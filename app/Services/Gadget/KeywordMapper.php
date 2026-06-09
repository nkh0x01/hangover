<?php

namespace App\Services\Gadget;

/**
 * Georgian → English keyword synonyms for WooCommerce product search.
 * WC's ?search= uses LIKE-style full-text on the product title/excerpt.
 * Georgian terms often have no direct hit in the catalog (which uses
 * English product names like "iPhone Case" or "Samsung S24").
 *
 * Strategy: expand the customer's query into multiple variants, search
 * WC for each, dedupe by product id. Keep the original query in the
 * mix so legitimate Georgian-named products still match.
 */
class KeywordMapper
{
    /**
     * Direct token mappings. Lowercase keys, English values (also lowercase).
     * One Georgian token can map to multiple English equivalents.
     */
    private const MAP = [
        // Brands
        'აიფონ' => ['iphone'],
        'აიფონი' => ['iphone'],
        'აიფონის' => ['iphone'],
        'სამსუნგ' => ['samsung'],
        'სამსუნგი' => ['samsung'],
        'სამსუნგის' => ['samsung'],
        'შაომი' => ['xiaomi'],
        'შაოომი' => ['xiaomi'],
        'რედმი' => ['redmi'],
        'ჰუავეი' => ['huawei'],
        'ჰონორ' => ['honor'],
        'ეპლი' => ['apple'],
        'ეპლის' => ['apple'],
        'ეპლ' => ['apple'],
        'სონი' => ['sony'],
        'სონის' => ['sony'],
        'ჯიბიელი' => ['jbl'],
        'ანკერი' => ['anker'],
        'ბოუსი' => ['bose'],
        'ბოუსე' => ['bose'],
        'რემაქსი' => ['remax'],
        'ბასეუსი' => ['baseus'],
        'ჰოკო' => ['hoco'],
        'ბეითსი' => ['beats'],
        'შოქსი' => ['shokz'],
        'ლოჯიტეკი' => ['logitech'],
        'რეიზერი' => ['razer'],
        'ეარპოდსი' => ['airpods'],

        // Cases / covers
        'ქეისი' => ['case', 'cover'],
        'ქეისები' => ['case'],
        'ქეისის' => ['case'],
        'ქოვერი' => ['cover', 'case'],
        'ქოვერის' => ['cover', 'case'],
        'ჩასადები' => ['case', 'cover'],
        'ჩასადებები' => ['case', 'cover'],
        'ბამპერი' => ['bumper', 'case'],
        'ბამპერის' => ['bumper'],

        // Screen / protection
        'დამცავი' => ['protector', 'glass', 'case'],
        'ეკრანი' => ['screen'],
        'ეკრანის' => ['screen', 'screen protector'],
        'მინა' => ['glass', 'tempered'],
        'მინის' => ['glass'],
        'ფირი' => ['film', 'protector'],

        // Audio
        'ყურსასმენ' => ['headphones', 'earphones', 'earbuds'],
        'ყურსასმენი' => ['headphones', 'earphones', 'earbuds'],
        'ყურსასმენებ' => ['headphones', 'earphones', 'earbuds'],
        'ნაუშნიკი' => ['headphones'],
        'ნაუშნიკები' => ['headphones'],
        'კოლონკა' => ['speaker'],
        'სპიკერი' => ['speaker'],
        'მიკროფონი' => ['microphone'],

        // Cables / charging
        'უსადენო' => ['wireless'],
        'მავთულიანი' => ['wired'],
        'სადენიანი' => ['wired'],
        'სადენი' => ['cable'],
        'კაბელი' => ['cable'],
        'დამტენი' => ['charger', 'charging'],
        'დატენვა' => ['charger', 'charging'],
        'სწრაფი' => ['fast'],
        'სწრაფის' => ['fast'],
        'ბატარეა' => ['battery', 'power bank'],
        'პაუერბანკი' => ['power bank', 'powerbank'],
        'პაუერ' => ['power'],
        'მაგსეიფი' => ['magsafe'],
        'მაგსაფი' => ['magsafe'],
        'ადაპტერი' => ['adapter'],
        'ადაპტერის' => ['adapter'],

        // Devices
        'საათი' => ['watch', 'smartwatch'],
        'საათის' => ['watch'],
        'სმარტი' => ['smart'],
        'სმარტული' => ['smart'],
        'ტელეფონი' => ['phone'],
        'ტელეფონის' => ['phone'],
        'ლეპტოპი' => ['laptop'],
        'ლეპტოპის' => ['laptop'],
        'ნოუთბუქი' => ['notebook', 'laptop'],
        'პლანშეტი' => ['tablet', 'ipad'],
        'პლანშეტის' => ['tablet'],
        'მაუსი' => ['mouse'],
        'კლავიატურა' => ['keyboard'],
        'ჯოისტიკი' => ['joystick', 'gamepad'],
        'გეიმპადი' => ['gamepad'],
        'ჰედსეტი' => ['headset'],

        // Quality / origin (don't add to search — let post-filter handle)
        'ორიგინალი' => ['original'],
        'ორიგინალური' => ['original'],
        'პრემიუმი' => ['premium'],
        'პრემიუმ' => ['premium'],

        // adjective fillers — drop these (no English equivalent needed)
        'კარგი' => [],
        'ხარისხიანი' => [],
        'იაფი' => [],
        'ფასიანი' => [],
        'მინდა' => [],
        'მინდოდა' => [],
        'გვაქვს' => [],
        'გაქვთ' => [],
        'მქონდა' => [],
        'მიჩვენე' => [],
        'მიჩვენეთ' => [],
        'საჭიროა' => [],
        'მჭირდება' => [],
    ];

    /**
     * Drop these tokens entirely from any query.
     */
    private const STOPWORDS = [
        'და', 'რომ', 'ვინ', 'რა', 'როგორ', 'ხო', 'ხო-ხო',
        'ჰო', 'ოო', 'აა', 'მე', 'შენ', 'ის', 'ვართ',
        'არის', 'არ', 'ხართ', 'არიან', 'მე', 'ჩვენი',
        'a', 'an', 'the', 'i', 'do', 'you', 'we', 'have', 'want', 'need', 'is', 'are',
    ];

    /**
     * Expand a customer query into one or more WC search variants. The
     * caller searches each variant and de-dupes by product id.
     *
     * @return string[] non-empty queries to try, in priority order
     */
    public function expand(string $query): array
    {
        $query = trim($query);
        if ($query === '') return [];

        // Lowercase + tokenize on whitespace/punctuation
        $tokens = preg_split('/[\s\p{P}]+/u', mb_strtolower($query)) ?: [];
        $tokens = array_filter(array_map('trim', $tokens));

        $englishTokens = [];

        foreach ($tokens as $tok) {
            if (in_array($tok, self::STOPWORDS, true)) {
                continue;
            }
            if (preg_match('/^[\x{10A0}-\x{10FF}\x{2D00}-\x{2D2F}]+$/u', $tok)) {
                $mapped = self::MAP[$tok] ?? null;
                if ($mapped === null) continue; // unknown Georgian token — drop
                if ($mapped === []) continue;   // filler word
                foreach ($mapped as $m) $englishTokens[] = $m;
            } else {
                // English / numeric / mixed — pass through
                $englishTokens[] = $tok;
            }
        }
        $englishTokens = array_values(array_unique($englishTokens));

        $variants = [];
        // 1. Original query (often best for English-only inputs)
        $variants[] = $query;
        // 2. Normalized english variant (most useful when input was Georgian)
        if ($englishTokens && implode(' ', $englishTokens) !== mb_strtolower($query)) {
            $variants[] = implode(' ', $englishTokens);
        }
        // 3. Just the LAST english token (e.g. "case" alone) — broad fallback
        if (count($englishTokens) > 1) {
            $variants[] = end($englishTokens);
        }

        return array_values(array_unique($variants));
    }

    /**
     * Is the entire input numeric? (likely a SKU lookup)
     */
    public function looksLikeSku(string $query): bool
    {
        return (bool) preg_match('/^\d{3,}$/', trim($query));
    }

    /**
     * Suggest accessory categories for upsell when the primary intent is
     * a phone case. Returns the upsell search keywords to try in WC.
     */
    public function upsellsFor(string $primaryQuery): array
    {
        $q = mb_strtolower($primaryQuery);
        // iPhone case → suggest screen protector + magsafe charger
        if (str_contains($q, 'case') || str_contains($q, 'cover')) {
            return ['screen protector', 'magsafe charger'];
        }
        // Headphones → suggest cable + power bank
        if (str_contains($q, 'headphones') || str_contains($q, 'earbuds') || str_contains($q, 'airpods')) {
            return ['cable', 'power bank'];
        }
        // Phone → case + screen protector
        if (str_contains($q, 'iphone') || str_contains($q, 'samsung') || str_contains($q, 'phone')) {
            return ['case', 'screen protector'];
        }
        return [];
    }
}
