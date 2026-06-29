<?php

use App\Enums\TeamRole;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

function attachUserToTeamForMiddleware(User $user, Team $team, TeamRole $role): void
{
    $user->teams()->attach($team, ['role' => $role->value]);
}

function runEnsureTeamMembership(Request $request, ?string $minimumRole = null): int
{
    $middleware = new EnsureTeamMembership;

    try {
        $middleware->handle($request, fn () => response('OK'), $minimumRole);

        return 200;
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }
}

it('aborts with 403 when no authenticated user', function () {
    $team = Team::factory()->create();
    $request = Request::create("/teams/{$team->slug}", 'GET');

    expect(runEnsureTeamMembership($request))->toBe(403);
});

it('aborts with 403 when user is not a team member', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $request = Request::create("/teams/{$team->slug}", 'GET');
    $request->setUserResolver(fn () => $user);

    expect(runEnsureTeamMembership($request))->toBe(403);
});

it('allows team members to pass through', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $request = Request::create("/teams/{$team->slug}", 'GET');
    $request->setUserResolver(fn () => $owner);

    Route::bind('current_team', fn () => $team);
    $request->setRouteResolver(fn () => tap(Route::get('teams/{current_team}', fn () => 'ok')->bind($request), function ($route) use ($team) {
        $route->setParameter('current_team', $team);
    }));

    expect(runEnsureTeamMembership($request))->toBe(200);
});

it('enforces minimum role requirement', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    attachUserToTeamForMiddleware($member, $team, TeamRole::Member);
    $admin = User::factory()->create();
    attachUserToTeamForMiddleware($admin, $team, TeamRole::Admin);

    $request = Request::create("/teams/{$team->slug}", 'GET');
    $request->setUserResolver(fn () => $member);
    $request->setRouteResolver(fn () => tap(Route::get('teams/{current_team}', fn () => 'ok')->bind($request), function ($route) use ($team) {
        $route->setParameter('current_team', $team);
    }));

    expect(runEnsureTeamMembership($request, 'admin'))->toBe(403);

    $request2 = Request::create("/teams/{$team->slug}", 'GET');
    $request2->setUserResolver(fn () => $admin);
    $request2->setRouteResolver(fn () => tap(Route::get('teams/{current_team}', fn () => 'ok')->bind($request2), function ($route) use ($team) {
        $route->setParameter('current_team', $team);
    }));

    expect(runEnsureTeamMembership($request2, 'admin'))->toBe(200);
});

it('aborts when an invalid minimum role is provided', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $request = Request::create("/teams/{$team->slug}", 'GET');
    $request->setUserResolver(fn () => $owner);
    $request->setRouteResolver(fn () => tap(Route::get('teams/{current_team}', fn () => 'ok')->bind($request), function ($route) use ($team) {
        $route->setParameter('current_team', $team);
    }));

    expect(runEnsureTeamMembership($request, 'superadmin'))->toBe(403);
});

it('switches current team when current_team route parameter differs from user current team', function () {
    $owner = User::factory()->create();
    $personal = $owner->currentTeam;
    $otherTeam = Team::factory()->create();
    $owner->teams()->attach($otherTeam, ['role' => TeamRole::Owner->value]);

    $request = Request::create("/teams/{$otherTeam->slug}", 'GET');
    $request->setUserResolver(fn () => $owner);
    $request->setRouteResolver(fn () => tap(Route::get('teams/{current_team}', fn () => 'ok')->bind($request), function ($route) use ($otherTeam) {
        $route->setParameter('current_team', $otherTeam);
    }));

    expect(runEnsureTeamMembership($request))->toBe(200)
        ->and($owner->fresh()->current_team_id)->toBe($otherTeam->id);
});

it('resolves team from team route parameter when current_team is absent', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $request = Request::create("/teams/{$team->slug}/members", 'GET');
    $request->setUserResolver(fn () => $owner);
    $request->setRouteResolver(fn () => tap(Route::get('teams/{team}/members', fn () => 'ok')->bind($request), function ($route) use ($team) {
        $route->setParameter('team', $team->slug);
    }));

    expect(runEnsureTeamMembership($request))->toBe(200);
});
