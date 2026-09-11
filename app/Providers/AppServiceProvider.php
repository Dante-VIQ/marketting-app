<?php

namespace App\Providers;

use App\Models\AiAction;
use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\User;
use App\Policies\AiActionPolicy;
use App\Policies\BrandPolicy;
use App\Policies\ContentDraftPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(ContentDraft::class, ContentDraftPolicy::class);
        Gate::policy(AiAction::class, AiActionPolicy::class);

        // Define custom abilities that don't map 1:1 to model methods
        Gate::define('publish-content', function (User $user, $brandId) {
            if ($user->hasRole('super-admin')) return true;
            return $user->belongsToBrand($brandId)
                && $user->hasAnyRole(['admin', 'editor']);
        });

        Gate::define('delete-brand', function (User $user, $brandId) {
            if ($user->hasRole('super-admin')) return true;
            return $user->belongsToBrand($brandId) && $user->hasRole('admin');
        });

    // General agent traffic – generous for reads
    RateLimiter::for('agent', function (Request $request) {
        return [
            // Global: 300 requests per minute per API key
            Limit::perMinute(300)
                ->by($request->header('X-API-Key') ?? $request->ip()),

            // Writes: 60 per minute (enough for multiple opportunities per cycle)
            Limit::perMinute(60)
                ->by($request->header('X-API-Key') . ':write')
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many write requests. Please slow down.',
                        'retry_after' => 60,
                    ], 429);
                }),
        ];
    });

    // Content generation – still tighter, but workable
    RateLimiter::for('agent-content', function (Request $request) {
        return Limit::perMinute(20)
            ->by($request->header('X-API-Key'))
            ->response(function () {
                return response()->json([
                    'error' => 'Content generation rate limit exceeded.',
                    'retry_after' => 60,
                ], 429);
            });
    });
    }
}
