<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', Rule::in(['ka', 'en', 'ru'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? 'ka',
        ]);

        $user->assignRole('buyer');

        $token = $user->createToken('default')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'locale']),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'error' => ['code' => 'invalid_credentials', 'message_ka' => 'არასწორი ელფოსტა ან პაროლი'],
            ], 401);
        }

        $token = $user->createToken('default')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'locale']),
            'roles' => $user->getRoleNames(),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile', 'seller');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
            ],
            'roles' => $user->getRoleNames(),
            'profile' => $user->profile,
            'seller' => $user->seller,
        ]);
    }
}
