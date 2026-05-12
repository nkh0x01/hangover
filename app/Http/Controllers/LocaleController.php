<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supported = config('app.supported_locales', ['ka', 'en']);

        if (! in_array($locale, $supported, true)) {
            return redirect()->back();
        }

        session(['locale' => $locale]);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return redirect()->back();
    }
}
