<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Seller\Models\Seller;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_register_and_me(): void
    {
        $resp = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $resp->assertCreated();
        $token = $resp->json('token');
        $this->assertNotEmpty($token);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonPath('roles.0', 'buyer');
    }

    public function test_login_with_wrong_password_fails(): void
    {
        User::create([
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => bcrypt('correct123'),
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_products_list_only_published(): void
    {
        $cat = Category::create(['slug' => 'cat-x', 'name_ka' => 'X', 'position' => 0, 'is_active' => true]);
        $user = User::create(['name' => 'S', 'email' => 's@x.com', 'password' => bcrypt('x')]);
        $seller = Seller::create([
            'user_id' => $user->id, 'slug' => 'shop-x', 'business_name' => 'Shop X',
            'legal_form' => 'individual', 'sector' => 'crafts', 'region' => 'tbilisi',
            'verification_status' => 'approved',
        ]);

        Product::create([
            'seller_id' => $seller->id, 'category_id' => $cat->id, 'slug' => 'p1',
            'title_ka' => 'Visible', 'description_ka' => '-', 'price_gel' => 10,
            'status' => 'published', 'published_at' => now(),
        ]);
        Product::create([
            'seller_id' => $seller->id, 'category_id' => $cat->id, 'slug' => 'p2',
            'title_ka' => 'Hidden', 'description_ka' => '-', 'price_gel' => 10,
            'status' => 'draft',
        ]);

        $resp = $this->getJson('/api/v1/products');
        $resp->assertOk();
        $titles = collect($resp->json('data'))->pluck('title_ka')->all();
        $this->assertContains('Visible', $titles);
        $this->assertNotContains('Hidden', $titles);
    }

    public function test_financing_recommendations_returns_ranked_list(): void
    {
        $this->seed(\Database\Seeders\FundingProgramsSeeder::class);

        $resp = $this->postJson('/api/v1/financing/recommendations', [
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
            'is_woman_owned' => true,
            'is_existing_business' => true,
            'funding_amount_gel' => 30000,
        ]);

        $resp->assertOk()
            ->assertJsonStructure(['recommendations' => [['program', 'match_percentage', 'matched_rules', 'missing_requirements', 'suggested_next_step_ka']], 'note_ka']);

        $this->assertGreaterThan(0, count($resp->json('recommendations')));
        $this->assertStringContainsString('ავტომატურად არ აგზავნის', $resp->json('note_ka'));
    }
}
