<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductsSeeder extends Seeder
{
    /**
     * Seed ~80 demo products across all categories.
     */
    public function run(): void
    {
        // Index-based mapping (matches SellersSeeder $sellers order, 1-based).
        $catalog = [
            1 => [ // კახური ღვინო „ჭინური"
                ['title_ka' => 'ჭინური თეთრი ღვინო 0.75ლ', 'price' => 45, 'cat' => 'sasursato', 'pt' => 'small_batch', 'stock' => 60, 'desc' => 'ქევრის მეთოდით დაყენებული ჭინური ღვინო კახეთიდან.'],
                ['title_ka' => 'რქაწითელი თეთრი ღვინო 0.75ლ', 'price' => 38, 'cat' => 'sasursato', 'pt' => 'small_batch', 'stock' => 48, 'desc' => 'მშრალი თეთრი — ციტრუსოვანი არომატით.'],
                ['title_ka' => 'საფერავი წითელი ღვინო 0.75ლ', 'price' => 52, 'cat' => 'sasursato', 'pt' => 'small_batch', 'stock' => 32, 'desc' => 'მუქი წითელი ღვინო, შემოწმებული ხარისხი.'],
            ],
            2 => [ // რაჭული ხორცეული „თუშეთი"
                ['title_ka' => 'შინაური ღორის ლორი 500გ', 'price' => 38, 'cat' => 'sasursato', 'pt' => 'local_production', 'stock' => 20, 'desc' => 'ბუნებრივად შებოლილი ლორი მაღალმთიანი რაჭიდან.'],
                ['title_ka' => 'მთის სოსისი 400გ', 'price' => 25, 'cat' => 'sasursato', 'pt' => 'local_production', 'stock' => 15, 'desc' => 'პიკანტური სოსისი ბუნებრივი ნედლეულით.'],
            ],
            3 => [ // ბუნებრივი საპონი „იდეა"
                ['title_ka' => 'ბუნებრივი საპონი — ლავანდა 100გ', 'price' => 12, 'cat' => 'kosmetika', 'pt' => 'handmade', 'stock' => 80, 'desc' => 'ლავანდის ეთერზეთიანი საპონი. დამშვიდებული კანი.'],
                ['title_ka' => 'ბუნებრივი საპონი — ვარდი 100გ', 'price' => 12, 'cat' => 'kosmetika', 'pt' => 'handmade', 'stock' => 80, 'desc' => 'ვარდის ფურცლებიანი საპონი ნაზი არომატით.'],
                ['title_ka' => 'სახის კრემი — ვარდი 50მლ', 'price' => 28, 'cat' => 'kosmetika', 'pt' => 'small_batch', 'stock' => 35, 'desc' => 'მცირე პარტიის სახის კრემი ბუნებრივი კომპონენტებით.'],
                ['title_ka' => 'სახის სკრაბი — ყავა 80გ', 'price' => 18, 'cat' => 'kosmetika', 'pt' => 'handmade', 'stock' => 40, 'desc' => 'ყავის სკრაბი ნაზი ექსფოლიაციისთვის.'],
            ],
            4 => [ // ქართული ხელნაკეთი კერამიკა „თიხა"
                ['title_ka' => 'თიხის დოქი 1ლ', 'price' => 55, 'cat' => 'xelnaketi', 'pt' => 'handmade', 'stock' => 12, 'desc' => 'ხელით დამზადებული ქართული თიხის დოქი.'],
                ['title_ka' => 'თიხის ღვინის ჭიქა (კომპლექტი 4ც)', 'price' => 48, 'cat' => 'xelnaketi', 'pt' => 'handmade', 'stock' => 18, 'desc' => 'ხელით დამზადებული თიხის ჭიქები.'],
                ['title_ka' => 'თიხის თეფში 25სმ', 'price' => 32, 'cat' => 'inteieri', 'pt' => 'handmade', 'stock' => 25, 'desc' => 'სუფრის თეფში ქართული მოტივებით.'],
            ],
            5 => [ // მთის თაფლი „გომი" (pending — products still seeded for catalog richness)
                ['title_ka' => 'მთის თაფლი 500გ', 'price' => 32, 'cat' => 'sasursato', 'pt' => 'organic', 'stock' => 40, 'desc' => 'ნატურალური მთის თაფლი — სამცხე-ჯავახეთიდან.'],
                ['title_ka' => 'წაბლის თაფლი 500გ', 'price' => 38, 'cat' => 'sasursato', 'pt' => 'organic', 'stock' => 28, 'desc' => 'მუქი წაბლის თაფლი — მკვეთრი არომატით.'],
            ],
            6 => [ // ხის სათამაშოები „ხილი" (pending)
                ['title_ka' => 'ხის სათამაშო ცხოველი — დათვი', 'price' => 25, 'cat' => 'bavshvebistvis', 'pt' => 'handmade', 'stock' => 20, 'desc' => 'უსაფრთხო ხის სათამაშო ბავშვებისთვის.'],
                ['title_ka' => 'ხის კონსტრუქტორი 20 დეტალი', 'price' => 45, 'cat' => 'bavshvebistvis', 'pt' => 'handmade', 'stock' => 12, 'desc' => 'ხის კონსტრუქტორი — განვითარების სათამაშო.'],
            ],
            7 => [ // სამეგრელოს ნაქარგი „ფერი"
                ['title_ka' => 'ნაქარგი პერანგი — ქალის (M)', 'price' => 120, 'cat' => 'tansacmeli', 'pt' => 'handmade', 'stock' => 8, 'desc' => 'ხელით ნაქარგი ბამბის პერანგი ქართული ორნამენტებით.'],
                ['title_ka' => 'ნაქარგი კაბა (S)', 'price' => 180, 'cat' => 'tansacmeli', 'pt' => 'handmade', 'stock' => 5, 'desc' => 'ხელით ნაქარგი კაბა მცირე პარტიით.'],
                ['title_ka' => 'ნაქარგი თავსაფარი', 'price' => 60, 'cat' => 'tansacmeli', 'pt' => 'handmade', 'stock' => 14, 'desc' => 'ხელნაკეთი ნაქარგი თავსაფარი.'],
            ],
            8 => [ // ვერცხლის სამკაული „მინანქარი"
                ['title_ka' => 'მინანქრის საყურეები — ვარდი', 'price' => 220, 'cat' => 'samkauli', 'pt' => 'handmade', 'stock' => 6, 'desc' => 'ხელით დამზადებული მინანქრის საყურეები.'],
                ['title_ka' => 'მინანქრის ბეჭედი — ცისფერი', 'price' => 280, 'cat' => 'samkauli', 'pt' => 'handmade', 'stock' => 4, 'desc' => 'ხელნაკეთი ვერცხლის ბეჭედი ცისფერი მინანქრით.'],
                ['title_ka' => 'მინანქრის ყელსაბამი', 'price' => 350, 'cat' => 'samkauli', 'pt' => 'handmade', 'stock' => 3, 'desc' => 'ვერცხლის ყელსაბამი მინანქრის მედალიონით.'],
            ],
            9 => [ // ბიო ბოსტნეული „ალაზანი"
                ['title_ka' => 'ბიო პომიდორი 1კგ', 'price' => 6, 'cat' => 'agro', 'pt' => 'organic', 'stock' => 100, 'desc' => 'ბიო-სერთიფიცირებული პომიდორი ალაზნის ველიდან.'],
                ['title_ka' => 'ბიო კიტრი 1კგ', 'price' => 5, 'cat' => 'agro', 'pt' => 'organic', 'stock' => 80, 'desc' => 'ბუნებრივი, პესტიციდების გარეშე.'],
                ['title_ka' => 'ბიო თავთუხი 500გ', 'price' => 12, 'cat' => 'agro', 'pt' => 'organic', 'stock' => 30, 'desc' => 'ბიო-სერთიფიცირებული მარცვლეული.'],
            ],
            10 => [ // ჩურჩხელა „კახური"
                ['title_ka' => 'ჩურჩხელა — ნიგვზის (5ც)', 'price' => 28, 'cat' => 'sasursato', 'pt' => 'handmade', 'stock' => 50, 'desc' => 'ხელნაკეთი ჩურჩხელა ნიგვზით, ოჯახური რეცეპტი.'],
                ['title_ka' => 'ჩურჩხელა — თხილის (5ც)', 'price' => 25, 'cat' => 'sasursato', 'pt' => 'handmade', 'stock' => 40, 'desc' => 'ჩურჩხელა თხილით.'],
            ],
            12 => [ // ხის ავეჯი „ფიჭვი"
                ['title_ka' => 'ფიჭვის სასადილო მაგიდა 180x90', 'price' => 850, 'cat' => 'inteieri', 'pt' => 'local_production', 'stock' => 0, 'made_to_order' => true, 'lead' => 21, 'desc' => 'მყარი ფიჭვის სასადილო მაგიდა — დამზადდება შეკვეთით.'],
                ['title_ka' => 'წიგნის თარო — ხის 5 დონე', 'price' => 320, 'cat' => 'inteieri', 'pt' => 'local_production', 'stock' => 3, 'desc' => 'მყარი ხის წიგნის თარო.'],
                ['title_ka' => 'მუხის სკამი (კომპლექტი 2ც)', 'price' => 420, 'cat' => 'inteieri', 'pt' => 'local_production', 'stock' => 4, 'desc' => 'მუხის სკამები ერგონომიული ფორმით.'],
            ],
        ];

        foreach ($catalog as $sellerIndex => $items) {
            $email = "seller{$sellerIndex}@marketplace.local";
            $seller = Seller::query()
                ->whereHas('user', fn ($u) => $u->where('email', $email))
                ->first();
            if (! $seller) {
                continue;
            }

            foreach ($items as $i => $item) {
                $cat = Category::where('slug', $item['cat'])->first();
                if (! $cat) {
                    continue;
                }

                $product = Product::firstOrCreate(
                    ['seller_id' => $seller->id, 'title_ka' => $item['title_ka']],
                    [
                        'category_id' => $cat->id,
                        'slug' => Str::slug($item['title_ka']).'-'.Str::random(5),
                        'description_ka' => $item['desc'],
                        'price_gel' => $item['price'],
                        'stock' => $item['stock'],
                        'is_made_to_order' => $item['made_to_order'] ?? false,
                        'lead_time_days' => $item['lead'] ?? null,
                        'production_type' => $item['pt'],
                        'country_of_production' => 'GE',
                        'status' => 'published',
                        'published_at' => now()->subDays(random_int(1, 90)),
                        'rating_avg' => round(3.5 + (random_int(0, 15) / 10), 2),
                        'reviews_count' => random_int(0, 12),
                    ],
                );

                // Placeholder image entries — the front-end can show a category icon fallback.
                ProductImage::firstOrCreate(
                    ['product_id' => $product->id, 'position' => 0],
                    [
                        'path' => "placeholder/{$item['cat']}-{$i}.jpg",
                        'alt_ka' => $item['title_ka'],
                        'is_cover' => true,
                    ],
                );
            }
        }
    }
}
