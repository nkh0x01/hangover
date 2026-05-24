<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'sasursato', 'name_ka' => 'საკვები და სასმელი', 'icon' => 'utensils'],
            ['slug' => 'agro', 'name_ka' => 'სოფლის მეურნეობის პროდუქცია', 'icon' => 'sprout'],
            ['slug' => 'kosmetika', 'name_ka' => 'ბუნებრივი კოსმეტიკა', 'icon' => 'spray-can'],
            ['slug' => 'tansacmeli', 'name_ka' => 'ტანსაცმელი და აქსესუარები', 'icon' => 'shirt'],
            ['slug' => 'samkauli', 'name_ka' => 'სამკაული', 'icon' => 'gem'],
            ['slug' => 'inteieri', 'name_ka' => 'სახლი და ინტერიერი', 'icon' => 'home'],
            ['slug' => 'bavshvebistvis', 'name_ka' => 'ბავშვებისთვის', 'icon' => 'baby'],
            ['slug' => 'xelnaketi', 'name_ka' => 'ხელნაკეთობა', 'icon' => 'hand'],
            ['slug' => 'print-design', 'name_ka' => 'ბეჭდვა და დიზაინი', 'icon' => 'paint-roller'],
            ['slug' => 'eko', 'name_ka' => 'ეკო პროდუქცია', 'icon' => 'leaf'],
        ];

        foreach ($categories as $i => $cat) {
            Category::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat + ['position' => $i, 'is_active' => true],
            );
        }
    }
}
