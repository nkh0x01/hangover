<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SellersSeeder extends Seeder
{
    /**
     * 12 demo sellers — small entrepreneurs across Georgia, varied legal forms,
     * sectors and verification statuses.
     */
    public function run(): void
    {
        $sellers = [
            ['business_name' => 'კახური ღვინო „ჭინური"', 'sector' => 'food_and_drink', 'legal_form' => 'solo_entrepreneur', 'region' => 'kakheti', 'municipality' => 'თელავი', 'business_age_months' => 36, 'annual_revenue_gel' => 85000, 'employees_count' => 3, 'is_woman_owned' => false, 'verification_status' => 'approved', 'story' => 'ოჯახური მევენახეობა თელავთან, რომელიც ქევრის ღვინოს აწარმოებს ტრადიციული მეთოდით. სამი თაობაა საქმეში.'],
            ['business_name' => 'რაჭული ხორცეული „თუშეთი"', 'sector' => 'food_and_drink', 'legal_form' => 'solo_entrepreneur', 'region' => 'racha_lechkhumi', 'municipality' => 'ონი', 'business_age_months' => 18, 'annual_revenue_gel' => 32000, 'employees_count' => 2, 'is_mountainous_region' => true, 'verification_status' => 'approved', 'story' => 'შინაური ღორის ხორცი და ლორი მაღალმთიანი რაჭიდან.'],
            ['business_name' => 'ბუნებრივი საპონი „იდეა"', 'sector' => 'cosmetics', 'legal_form' => 'individual', 'region' => 'tbilisi', 'business_age_months' => 12, 'annual_revenue_gel' => 18000, 'employees_count' => 1, 'is_woman_owned' => true, 'is_youth_owned' => true, 'is_startup' => true, 'verification_status' => 'approved', 'story' => 'მცირე ლაბორატორია თბილისში — ბუნებრივი საპონი ქართული ბალახეულით.'],
            ['business_name' => 'ქართული ხელნაკეთი კერამიკა „თიხა"', 'sector' => 'crafts', 'legal_form' => 'solo_entrepreneur', 'region' => 'imereti', 'municipality' => 'ქუთაისი', 'business_age_months' => 48, 'annual_revenue_gel' => 45000, 'employees_count' => 2, 'is_woman_owned' => true, 'verification_status' => 'approved', 'story' => 'ქართული ტრადიციული კერამიკა — ქოთნები, თეფშები, დოქები.'],
            ['business_name' => 'მთის თაფლი „გომი"', 'sector' => 'food_and_drink', 'legal_form' => 'individual', 'region' => 'samtskhe_javakheti', 'business_age_months' => 24, 'annual_revenue_gel' => 22000, 'employees_count' => 1, 'is_mountainous_region' => true, 'is_agriculture' => true, 'verification_status' => 'pending', 'story' => 'ნატურალური მთის თაფლი ბორჯომი-ბაკურიანის ხეობიდან.'],
            ['business_name' => 'ხის სათამაშოები „ხილი"', 'sector' => 'kids', 'legal_form' => 'individual', 'region' => 'guria', 'business_age_months' => 8, 'annual_revenue_gel' => 9000, 'employees_count' => 1, 'is_youth_owned' => true, 'is_startup' => true, 'verification_status' => 'pending', 'story' => 'უსაფრთხო ხის სათამაშოები ბავშვებისთვის — ბუნებრივი ფერებით.'],
            ['business_name' => 'სამეგრელოს ნაქარგი „ფერი"', 'sector' => 'fashion', 'legal_form' => 'solo_entrepreneur', 'region' => 'samegrelo_zemo_svaneti', 'municipality' => 'ზუგდიდი', 'business_age_months' => 30, 'annual_revenue_gel' => 28000, 'employees_count' => 4, 'is_woman_owned' => true, 'verification_status' => 'approved', 'story' => 'ქართული ნაქარგი პერანგები და კაბები ტრადიციული ორნამენტებით.'],
            ['business_name' => 'ვერცხლის სამკაული „მინანქარი"', 'sector' => 'jewelry', 'legal_form' => 'solo_entrepreneur', 'region' => 'tbilisi', 'business_age_months' => 60, 'annual_revenue_gel' => 95000, 'employees_count' => 5, 'verification_status' => 'approved', 'story' => 'ქართული მინანქრის სამკაული — ხელით დამზადებული თბილისში.'],
            ['business_name' => 'ბიო ბოსტნეული „ალაზანი"', 'sector' => 'agriculture', 'legal_form' => 'small_business', 'region' => 'kakheti', 'municipality' => 'ყვარელი', 'business_age_months' => 16, 'annual_revenue_gel' => 38000, 'employees_count' => 6, 'is_agriculture' => true, 'verification_status' => 'submitted', 'story' => 'ბიო-სერთიფიცირებული ბოსტნეული ალაზნის ველიდან.'],
            ['business_name' => 'ჩურჩხელა „კახური"', 'sector' => 'food_and_drink', 'legal_form' => 'individual', 'region' => 'kakheti', 'business_age_months' => 5, 'annual_revenue_gel' => 4000, 'employees_count' => 1, 'is_woman_owned' => true, 'is_startup' => true, 'verification_status' => 'submitted', 'story' => 'ხელნაკეთი ჩურჩხელა ოჯახური რეცეპტით.'],
            ['business_name' => 'ხელნაკეთი ქუდები „ნაბადი"', 'sector' => 'fashion', 'legal_form' => 'individual', 'region' => 'mtskheta_mtianeti', 'business_age_months' => 14, 'annual_revenue_gel' => 11000, 'employees_count' => 2, 'is_mountainous_region' => true, 'verification_status' => 'rejected', 'rejection_reason' => 'საბუთები არასრულია — მოგვაწოდეთ რეგისტრაციის ამონაწერი', 'story' => 'ქართული ნაბდის ქუდები მთიულეთიდან.'],
            ['business_name' => 'ხის ავეჯი „ფიჭვი"', 'sector' => 'home_and_interior', 'legal_form' => 'llc', 'region' => 'shida_kartli', 'municipality' => 'გორი', 'business_age_months' => 72, 'annual_revenue_gel' => 180000, 'employees_count' => 12, 'verification_status' => 'approved', 'story' => 'ხის ხელნაკეთი ავეჯი — მაგიდები, სკამები, თაროები.'],
        ];

        foreach ($sellers as $i => $s) {
            $user = User::firstOrCreate(
                ['email' => 'seller'.($i + 1).'@marketplace.local'],
                ['name' => $s['business_name'], 'password' => Hash::make('password'), 'locale' => 'ka'],
            );
            $user->syncRoles(['buyer', 'seller']);

            Seller::firstOrCreate(
                ['user_id' => $user->id],
                array_merge([
                    'slug' => Str::slug($s['business_name']).'-'.($i + 1),
                    'is_made_in_georgia_verified' => ($s['verification_status'] ?? 'pending') === 'approved',
                    'verified_at' => ($s['verification_status'] ?? 'pending') === 'approved' ? now() : null,
                ], $s),
            );
        }
    }
}
