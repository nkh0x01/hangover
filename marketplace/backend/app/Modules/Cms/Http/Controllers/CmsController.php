<?php

declare(strict_types=1);

namespace App\Modules\Cms\Http\Controllers;

use App\Modules\Cms\Models\ContactMessage;
use App\Modules\Cms\Models\HeroSection;
use App\Modules\Cms\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CmsController extends Controller
{
    public function page(Page $page): JsonResponse
    {
        abort_unless($page->is_published, 404);

        return response()->json(['data' => $page]);
    }

    public function hero(string $key): JsonResponse
    {
        $hero = HeroSection::where('key', $key)->where('is_active', true)->firstOrFail();

        return response()->json(['data' => $hero]);
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'subject' => ['nullable', 'string', 'max:160'],
            'body_ka' => ['required', 'string', 'max:5000'],
        ]);

        ContactMessage::create($data);

        return response()->json([
            'ok' => true,
            'message_ka' => 'მადლობა! მალე დაგიკავშირდებით.',
        ]);
    }
}
