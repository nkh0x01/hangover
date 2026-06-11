<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Seller\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellersWebController extends Controller
{
    public function index(): View
    {
        return view('pages.sellers-index', [
            'sellers' => Seller::verified()->latest('verified_at')->paginate(24),
        ]);
    }

    public function show(Seller $seller): View
    {
        abort_unless($seller->isVerified(), 404);

        return view('pages.seller-show', [
            'seller' => $seller,
            'products' => $seller->products()->where('status', 'published')->latest('published_at')->paginate(12),
        ]);
    }

    public function onboarding(): View
    {
        return view('seller.onboarding');
    }

    public function submitOnboarding(Request $request): \Illuminate\Http\RedirectResponse
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
            'employees_count' => ['nullable', 'integer', 'min:0'],
            'is_woman_owned' => ['nullable', 'boolean'],
            'is_youth_owned' => ['nullable', 'boolean'],
            'is_mountainous_region' => ['nullable', 'boolean'],
            'is_startup' => ['nullable', 'boolean'],
            'is_agriculture' => ['nullable', 'boolean'],
            'story' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['user_id'] = $user->id;
        $data['slug'] = \Illuminate\Support\Str::slug($data['business_name']).'-'.\Illuminate\Support\Str::random(6);
        $data['verification_status'] = 'pending';

        Seller::create($data);

        return redirect()->route('home')->with('status', 'თქვენი მაღაზია ელოდება დამტკიცებას');
    }
}
