<?php

namespace App\Providers;

use App\Models\AiAction;
use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Observers\BrandContextObserver;
use App\Policies\AiActionPolicy;
use App\Policies\BrandPolicy;
use App\Policies\ContentDraftPolicy;
use App\Services\Competitors\Discovery\TavilySearch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected function resolveEnvValue(string $key): ?string
    {
        $value = getenv($key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (! is_readable($path)) {
            return null;
        }

        foreach (preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) as $line) {
            if (! preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)\s*$/', $line, $matches)) {
                continue;
            }

            $value = trim($matches[1]);
            if ($value === '') {
                return null;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            return trim($value);
        }

        return null;
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind TavilySearch so it can be auto-injected anywhere (including AcquisitionIntelligenceService).
        // Reads TAVILY_API_KEY from .env and falls back to a direct .env parse when runtime env loading is empty.
        $this->app->singleton(TavilySearch::class, function ($app) {
            $apiKey = config('services.tavily.key')
                ?? env('TAVILY_API_KEY')
                ?? $this->resolveEnvValue('TAVILY_API_KEY');

            return new TavilySearch(
                new \Illuminate\Http\Client\Factory(),
                $apiKey,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(ContentDraft::class, ContentDraftPolicy::class);
        Gate::policy(AiAction::class, AiActionPolicy::class);

        // Auto-flush BrandContextAssembler cache when brand data changes.
        // This ensures the AI always gets fresh context after edits.
        Brand::observe(BrandContextObserver::class);
        KnowledgeBase::observe(BrandContextObserver::class);

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
