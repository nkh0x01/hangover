<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureNotBlocked;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('lets active users through', function (): void {
    $user = User::factory()->create(['status' => 'active']);
    $request = Request::create('/api/v1/anything');
    $request->setUserResolver(fn () => $user);

    $response = (new EnsureNotBlocked)->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('blocks suspended users with a 403 envelope', function (): void {
    $user = User::factory()->create(['status' => 'suspended']);
    $request = Request::create('/api/v1/anything');
    $request->setUserResolver(fn () => $user);

    expect(fn () => (new EnsureNotBlocked)->handle($request, fn () => new Response('ok')))
        ->toThrow(HttpException::class);
});

it('blocks banned users', function (): void {
    $user = User::factory()->create(['status' => 'banned']);
    $request = Request::create('/api/v1/anything');
    $request->setUserResolver(fn () => $user);

    expect(fn () => (new EnsureNotBlocked)->handle($request, fn () => new Response('ok')))
        ->toThrow(HttpException::class);
});
