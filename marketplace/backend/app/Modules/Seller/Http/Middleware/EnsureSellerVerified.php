<?php

declare(strict_types=1);

namespace App\Modules\Seller\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->seller) {
            abort(403, 'საჭიროა გამყიდველის რეგისტრაცია');
        }

        if (! $user->seller->isVerified()) {
            abort(403, 'თქვენი მაღაზია ჯერ არ არის დადასტურებული');
        }

        return $next($request);
    }
}
