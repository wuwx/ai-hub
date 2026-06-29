<?php

use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Models\User;
use Illuminate\Http\Request;

function makeJsonRequestForUser(User $user): Request
{
    $request = Request::create('/login', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    $request->setUserResolver(fn () => $user);

    return $request;
}

function makeHtmlRequestForUser(User $user): Request
{
    $request = Request::create('/login', 'POST', server: ['HTTP_ACCEPT' => 'text/html']);
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('returns a json payload when login response is requested via api', function () {
    $user = User::factory()->create();

    $response = (new LoginResponse)->toResponse(makeJsonRequestForUser($user));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['two_factor' => false]);
});

it('redirects html login response to the current team dashboard', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $response = (new LoginResponse)->toResponse(makeHtmlRequestForUser($user));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getTargetUrl())->toEndWith("/{$team->slug}/dashboard");
});

it('returns a json payload when two factor login response is requested via api', function () {
    $user = User::factory()->create();

    $response = (new TwoFactorLoginResponse)->toResponse(makeJsonRequestForUser($user));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['two_factor' => false]);
});

it('redirects html two factor login response to the current team dashboard', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $response = (new TwoFactorLoginResponse)->toResponse(makeHtmlRequestForUser($user));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getTargetUrl())->toEndWith("/{$team->slug}/dashboard");
});

it('returns a 201 json payload when register response is requested via api', function () {
    $user = User::factory()->create();

    $response = (new RegisterResponse)->toResponse(makeJsonRequestForUser($user));

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true))->toBe(['two_factor' => false]);
});

it('redirects html register response to the current team dashboard', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $response = (new RegisterResponse)->toResponse(makeHtmlRequestForUser($user));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getTargetUrl())->toEndWith("/{$team->slug}/dashboard");
});

it('returns a 204 json payload when verify email response is requested via api', function () {
    $user = User::factory()->create();

    $response = (new VerifyEmailResponse)->toResponse(makeJsonRequestForUser($user));

    expect($response->getStatusCode())->toBe(204);
});

it('redirects html verify email response to the current team dashboard with verified flag', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $response = (new VerifyEmailResponse)->toResponse(makeHtmlRequestForUser($user));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getTargetUrl())->toContain("/{$team->slug}/dashboard")
        ->and($response->getTargetUrl())->toContain('?verified=1');
});
