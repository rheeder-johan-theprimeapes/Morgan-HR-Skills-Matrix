<?php

declare(strict_types=1);

namespace App\Providers;

use App\Capability;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('manage-review-cycles', fn (User $user): bool => $user->hasCapability(Capability::AdministerReviews));
        Gate::define('manage-benchmarks', fn (User $user): bool => $user->hasCapability(Capability::Benchmarks));
        Gate::define('manage-skills', fn (User $user): bool => $user->hasCapability(Capability::ManageFrameworks));
        Gate::define('manage-directory', fn (User $user): bool => $user->canManageDirectory());
        foreach (['sign-in', 'account-setup'] as $name) {
            RateLimiter::for($name, function (Request $request) use ($name): array {
                $input = $request->input('login_id');
                $identifier = is_string($input) ? Str::upper(trim($input)) : '';

                return [
                    Limit::perMinute(5)->by($name.'|'.hash('sha256', $identifier.'|'.$request->ip())),
                    Limit::perMinute(120)->by($name.'|ip|'.$request->ip()),
                ];
            });
        }
        RateLimiter::for('account-access', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->getAuthIdentifier()));
    }
}
