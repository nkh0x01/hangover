<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Profile;

use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

final class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'locale' => ['nullable', Rule::in(config('app.supported_locales'))],
        ]);

        $request->user()->fill($data)->save();

        return new UserResource($request->user()->fresh());
    }
}
