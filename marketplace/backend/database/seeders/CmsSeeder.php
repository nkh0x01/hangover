<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Cms\Models\HeroSection;
use App\Modules\Cms\Models\Page;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        HeroSection::updateOrCreate(['key' => 'home_hero'], [
            'title_ka' => 'აღმოაჩინე ქართული წარმოების პროდუქტები ერთ სივრცეში',
            'subtitle_ka' => 'შეიძინე ქართველი მცირე მეწარმეებისა და ადგილობრივი მწარმოებლების პროდუქცია',
            'cta_label_ka' => 'პროდუქტების დათვალიერება',
            'cta_url' => '/catalogue',
            'is_active' => true,
        ]);

        HeroSection::updateOrCreate(['key' => 'financing_hero'], [
            'title_ka' => 'იპოვე დაფინანსება შენი საქმისთვის',
            'subtitle_ka' => 'შევსე მოკლე ანკეტა და მიიღე შენი ბიზნესისთვის შესაფერისი დაფინანსების პროგრამები',
            'cta_label_ka' => 'ანკეტის შევსება',
            'cta_url' => '/financing/questionnaire',
            'is_active' => true,
        ]);

        Page::updateOrCreate(['slug' => 'about'], [
            'title_ka' => 'ჩვენ შესახებ',
            'body_ka' => 'ქართული წარმოება არის პლატფორმა, რომელიც აერთიანებს ქართველი მცირე მეწარმეების და ადგილობრივი მწარმოებლების პროდუქციას. ჩვენი მისიაა მხარდაჭერა გავუწიოთ ადგილობრივ ბიზნესს და დაგვეხმაროს მათ მოძებნონ მომხმარებლები საქართველოს მასშტაბით.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Page::updateOrCreate(['slug' => 'contact'], [
            'title_ka' => 'კონტაქტი',
            'body_ka' => 'შემოგვიერთდი! ჩვენი გუნდი ემზადება შენი ბიზნესის მხარდაჭერისთვის. info@marketplace.ge',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Page::updateOrCreate(['slug' => 'how-to-sell'], [
            'title_ka' => 'როგორ ვიყიდო',
            'body_ka' => '1. დარეგისტრირდი მყიდველად. 2. დაათვალიერე კატალოგი. 3. დაამატე კალათაში. 4. გადაიხადე მიწოდებისას. 5. მიიღე ნივთი მისამართზე.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Page::updateOrCreate(['slug' => 'how-to-become-seller'], [
            'title_ka' => 'როგორ გავხდე გამყიდველი',
            'body_ka' => '1. დარეგისტრირდი. 2. გახდი მეწარმე — შეავსე ბიზნეს პროფილი. 3. ატვირთე საბუთები. 4. დაელოდე დადასტურებას. 5. დაიწყე პროდუქტის გაყიდვა.',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
