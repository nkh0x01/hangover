<?php

declare(strict_types=1);

namespace App\Modules\Seller\Http\Controllers;

use App\Modules\Seller\Models\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class SellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Seller::query()->verified();
        if ($request->filled('region')) {
            $q->where('region', $request->string('region'));
        }
        if ($request->filled('sector')) {
            $q->where('sector', $request->string('sector'));
        }

        return response()->json($q->paginate(24));
    }

    public function show(Seller $seller): JsonResponse
    {
        abort_unless($seller->isVerified(), 404);
        $seller->loadCount('products');

        return response()->json(['data' => $seller]);
    }

    public function products(Seller $seller): JsonResponse
    {
        abort_unless($seller->isVerified(), 404);
        $products = $seller->products()->where('status', 'published')->with('images')->paginate(24);

        return response()->json($products);
    }

    public function register(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->seller, 409, 'უკვე გაქვთ მაღაზია რეგისტრირებული');

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'legal_form' => ['required', 'string', 'max:32'],
            'sector' => ['required', 'string', 'max:32'],
            'region' => ['required', 'string', 'max:64'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'business_age_months' => ['nullable', 'integer', 'min:0'],
            'annual_revenue_gel' => ['nullable', 'numeric', 'min:0'],
            'employees_count' => ['nullable', 'integer', 'min:0'],
            'is_woman_owned' => ['nullable', 'boolean'],
            'is_youth_owned' => ['nullable', 'boolean'],
            'is_mountainous_region' => ['nullable', 'boolean'],
            'is_startup' => ['nullable', 'boolean'],
            'is_agriculture' => ['nullable', 'boolean'],
            'story' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['user_id'] = $user->id;
        $data['slug'] = Str::slug($data['business_name']).'-'.Str::random(6);
        $data['verification_status'] = 'pending';

        $seller = Seller::create($data);

        return response()->json([
            'data' => $seller,
            'message_ka' => 'თქვენი მაღაზია ელოდება დამტკიცებას',
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $seller = $request->user()->seller()->with('documents')->firstOrFail();

        return response()->json(['data' => $seller]);
    }

    public function update(Request $request): JsonResponse
    {
        $seller = $request->user()->seller()->firstOrFail();

        $data = $request->validate([
            'business_name' => ['sometimes', 'string', 'max:160'],
            'story' => ['nullable', 'string', 'max:5000'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'cover_path' => ['nullable', 'string', 'max:255'],
        ]);

        $seller->update($data);

        return response()->json(['data' => $seller->fresh()]);
    }
}
