<?php

namespace App\Providers;

use App\Models\Branch;
use App\Policies\BranchPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFive();
        Model::preventLazyLoading(! $this->app->isProduction());
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::define('view-platform', fn ($user) => $user->hasRole('PLATFORM_ADMIN'));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
