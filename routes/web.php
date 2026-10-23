<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PageController;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'landing'])->name('landing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');

Route::get('/dashboard', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Signed in but not yet approved by the platform administrator
Route::middleware('auth:sanctum')->get('/pending-approval', function () {
    if (auth()->user()->isApproved()) {
        return redirect()->route('dashboard');
    }
    return view('auth.pending-approval', ['user' => auth()->user()]);
})->name('approval.pending');

Route::middleware(['auth:sanctum', 'verified', 'approved'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Brand Management
    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::post('/', [BrandController::class, 'store'])->name('store');
        Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
        Route::post('/{brand}/toggle-active', [BrandController::class, 'toggleActive'])->name('toggle-active');
    });

    Route::get('/calibration', function () {
        return view('calibration');
    })->name('calibration');

    // // Analytics
    // Route::prefix('analytics')->name('analytics.')->group(function () {
    //     Route::get('/', function () {
    //         return view('analytics.index');
    //     })->name('index');
    //     Route::get('/revenue-leaks', function () {
    //         return view('analytics.revenue-leaks');
    //     })->name('revenue-leaks');
    //     Route::post('/fetch', [App\Http\Controllers\AnalyticsController::class, 'fetch'])->name('fetch');
    // });

    // Analytics
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::post('/fetch', [AnalyticsController::class, 'fetch'])->name('fetch');

        Route::get('/revenue-leaks', function () {
            $brand = auth()->user()->activeBrand;
            $leaks = $brand
                ? App\Models\RevenueLeak::where('brand_id', $brand->id)->orderByDesc('estimated_loss')->get()
                : collect();
            return view('analytics.revenue-leaks', compact('leaks'));
        })->name('revenue-leaks');
    });

    // AI Briefs
    Route::prefix('briefs')->name('briefs.')->group(function () {
        Route::get('/', [App\Http\Controllers\BriefController::class, 'index'])->name('index');
        Route::get('/{id}', [App\Http\Controllers\BriefController::class, 'show'])->name('show');
        Route::post('/generate', [App\Http\Controllers\BriefController::class, 'generate'])->name('generate');
    });
    // Actions
    Route::prefix('actions')->name('actions.')->group(function () {
        Route::get('/queue', function () {
            return view('actions.queue');
        })->name('queue');

        Route::get('/history', function () {
            return view('actions.history');
        })->name('history');
    });

    // Content
    Route::prefix('content')->name('content.')->group(function () {
        Route::get('/drafts', [App\Http\Controllers\ContentController::class, 'drafts'])->name('drafts');
        Route::get('/published', function () {
            return view('content.published');
        })->name('published');

        Route::post('/generate-all', [App\Http\Controllers\ContentController::class, 'generateAll'])->name('generate-all');
        Route::get('/content/queue-status', [App\Http\Controllers\ContentController::class, 'queueStatus'])->name('content.queue-status');
    });

    // SEO
    Route::prefix('seo')->name('seo.')->group(function () {
        Route::get('/', [App\Http\Controllers\SeoController::class, 'index'])->name('index');
        Route::get('/issue/{id}', [App\Http\Controllers\SeoController::class, 'showIssue'])->name('issue.show');
        Route::post('/issue/{id}/resolve', [App\Http\Controllers\SeoController::class, 'resolveIssue'])->name('issue.resolve');
        Route::post('/run-checks', [App\Http\Controllers\SeoController::class, 'runChecks'])->name('run-checks');
    });
    // Campaigns
    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/', function () {
            return view('campaigns.index');
        })->name('index');
        Route::get('/{id}', function ($id) {
            $campaign = App\Models\Campaign::findOrFail($id);
            return view('campaigns.show', compact('campaign'));
        })->name('show');
    });

    // Social listening (free sources)
    Route::prefix('listening')->name('listening.')->group(function () {
        Route::get('/', [App\Http\Controllers\ListeningController::class, 'index'])->name('index');
        Route::post('/check', [App\Http\Controllers\ListeningController::class, 'check'])->name('check');
        Route::post('/', [App\Http\Controllers\ListeningController::class, 'store'])->name('store');
        Route::delete('/{query}', [App\Http\Controllers\ListeningController::class, 'destroy'])->name('destroy')->whereNumber('query');
        Route::post('/mentions/{mention}/dismiss', [App\Http\Controllers\ListeningController::class, 'dismiss'])->name('dismiss')->whereNumber('mention');
    });

    // Buyer personas (hand-built, evidence-graded)
    Route::prefix('personas')->name('personas.')->group(function () {
        Route::get('/', [App\Http\Controllers\PersonaController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\PersonaController::class, 'store'])->name('store');
        Route::put('/{persona}', [App\Http\Controllers\PersonaController::class, 'update'])->name('update')->whereNumber('persona');
        Route::delete('/{persona}', [App\Http\Controllers\PersonaController::class, 'destroy'])->name('destroy')->whereNumber('persona');
        Route::post('/{persona}/evidence', [App\Http\Controllers\PersonaController::class, 'addEvidence'])->name('evidence.store')->whereNumber('persona');
        Route::delete('/{persona}/evidence/{evidence}', [App\Http\Controllers\PersonaController::class, 'removeEvidence'])->name('evidence.destroy')->whereNumber(['persona', 'evidence']);
    });

    // Content plan (humans post; the system plans and learns)
    Route::prefix('content-plan')->name('content-plan.')->group(function () {
        $c = App\Http\Controllers\ContentPlanController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/', [$c, 'store'])->name('store');
        Route::put('/{idea}', [$c, 'update'])->name('update')->whereNumber('idea');
        Route::post('/{idea}/posted', [$c, 'posted'])->name('posted')->whereNumber('idea');
        Route::post('/{idea}/results', [$c, 'results'])->name('results')->whereNumber('idea');
        Route::post('/{idea}/schedule', [$c, 'schedule'])->name('schedule')->whereNumber('idea');
        Route::post('/{idea}/cancel-schedule', [$c, 'cancelSchedule'])->name('cancel-schedule')->whereNumber('idea');
        Route::delete('/{idea}', [$c, 'destroy'])->name('destroy')->whereNumber('idea');
    });

    // Meta connection: token scopes, capabilities, Pages; results sync
    Route::get('/meta', [App\Http\Controllers\MetaController::class, 'index'])->name('meta.index');
    Route::post('/meta/sync', [App\Http\Controllers\MetaController::class, 'sync'])->name('meta.sync');
    Route::post('/meta/sync-data', [App\Http\Controllers\MetaController::class, 'syncData'])->name('meta.sync-data');

    // Competitors (free scan of public pages)
    Route::prefix('competitors')->name('competitors.')->group(function () {
        Route::get('/', [App\Http\Controllers\CompetitorController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\CompetitorController::class, 'store'])->name('store');
        Route::post('/{competitor}/scan', [App\Http\Controllers\CompetitorController::class, 'scan'])->name('scan')->whereNumber('competitor');
        Route::delete('/{competitor}', [App\Http\Controllers\CompetitorController::class, 'destroy'])->name('destroy')->whereNumber('competitor');
    });

    // Ad platforms (Meta, ...): connection status, link campaigns, live check
    Route::prefix('ad-platforms')->name('ad-platforms.')->group(function () {
        Route::get('/', [App\Http\Controllers\AdPlatformController::class, 'index'])->name('index');
        Route::post('/campaigns/{campaign}/link', [App\Http\Controllers\AdPlatformController::class, 'link'])->name('link')->whereNumber('campaign');
        Route::post('/campaigns/{campaign}/unlink', [App\Http\Controllers\AdPlatformController::class, 'unlink'])->name('unlink')->whereNumber('campaign');
        Route::post('/campaigns/{campaign}/check', [App\Http\Controllers\AdPlatformController::class, 'check'])->name('check')->whereNumber('campaign');
    });

    // Leads
    Route::prefix('leads')->name('leads.')->group(function () {
        Route::get('/', function () {
            return view('leads.index');
        })->name('index');
        Route::get('/{id}', function ($id) {
            $lead = App\Models\Lead::with('interactions')->findOrFail($id);
            return view('leads.show', compact('lead'));
        })->name('show')->whereNumber('id');
    });

    Route::view('/leads/thank-you', 'leads.thank-you')->name('leads.thank-you');
    Route::get('/recurring-issues', fn() => view('recurring-issues'))->name('recurring-issues');

    // Lead update endpoint (for responding to follow-up)
    Route::get('/leads/{lead}/update', function ($leadId, Request $request) {
        $lead = App\Models\Lead::findOrFail($leadId);
        $token = $request->query('token');

        // Verify token
        $expectedToken = hash_hmac('sha256', $lead->id . $lead->email, config('app.key'));

        if ($token !== $expectedToken) {
            abort(403, 'Invalid or expired link.');
        }

        return view('leads.update', ['lead' => $lead]);
    })->name('leads.update');

    Route::post('/leads/{lead}/update', function ($leadId, Request $request) {
        $lead = App\Models\Lead::findOrFail($leadId);

        // Verify the same HMAC token the GET route uses. Without this,
        // anyone can iterate lead IDs and POST arbitrary data — which also
        // triggers a real LLM call inside processResponse().
        $token = $request->input('token');
        $expected = hash_hmac('sha256', $lead->id . $lead->email, config('app.key'));

        if (!$token || !hash_equals($expected, $token)) {
            Log::warning('Lead update rejected: bad token', [
                'lead_id' => $lead->id,
                'ip' => $request->ip(),
            ]);
            abort(403, 'Invalid or expired link.');
        }

        $qualifier = app(App\Services\Lead\LeadQualifierService::class);
        $qualifier->processResponse($lead, $request->except(['_token', 'token']));

        return redirect()->route('leads.thank-you');
    })->name('leads.update.post');

    // Marketing AI
    Route::prefix('marketing')->name('marketing.')->group(function () {
        Route::get('/', function () {
            return view('marketing.index');
        })->name('index');
        Route::get('/settings', function () {
            return view('marketing.settings');
        })->name('settings');
    });

    // System Status (Guardian)
    Route::prefix('system')->name('system.')->group(function () {
        Route::get('/status', function () {
            $guardian = app(\App\Services\Guardian\GuardianService::class);
            $brand = auth()->user()->activeBrand;

            return view('guardian.status', [
                'healthStatus' => $guardian->getSystemStatus($brand),
                'incidents' => $guardian->getOpenIncidents($brand),
            ]);
        })->name('status');
        Route::get('/incidents', function () {
            return view('guardian.incidents');
        })->name('incidents');
        Route::get('/policies', function () {
            return view('guardian.policies');
        })->name('policies');
    });

    // Healthcare AI (Nafasi) - Coming Soon
    Route::prefix('healthcare')->name('healthcare.')->group(function () {
        Route::get('/', function () {
            return view('coming-soon', ['feature' => 'Healthcare AI (Nafasi)']);
        })->name('index');
    });

    // Education AI (School) - Coming Soon
    Route::prefix('education')->name('education.')->group(function () {
        Route::get('/', function () {
            return view('coming-soon', ['feature' => 'Education AI (School)']);
        })->name('index');
    });

    // Youth AI (VumbiDNA) - Coming Soon
    Route::prefix('youth')->name('youth.')->group(function () {
        Route::get('/', function () {
            return view('coming-soon', ['feature' => 'Youth AI (VumbiDNA)']);
        })->name('index');
    });

    Route::prefix('affiliate')->name('affiliate.')->group(function () {
        Route::get('/', function () {
            return view('affiliate.index');
        })->name('index');
    });

    // Platform-wide jobs: super admin only (the page can trigger jobs for every brand).
    Route::prefix('schedule')->name('schedule.')->middleware('superadmin')->group(function () {
        Route::get('/', function () {
            return view('schedule.index');
        })->name('index');
    });

    // // Blog Management
    // Route::prefix('blog')->name('blog.')->group(function () {
    //     Route::get('/', function () {
    //         return view('blog.index');
    //     })->name('index');
    //     Route::get('/import', function () {
    //         return view('blog.import');
    //     })->name('import');
    //     Route::post('/import', [App\Http\Controllers\Blog\BlogImportController::class, 'import'])
    //         ->name('import.post');
    // });

    Route::middleware(['auth:sanctum'])
        ->withoutMiddleware([
            \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            \App\Http\Middleware\EnsureUserApproved::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ])
        ->prefix('blog')->name('blog.')->group(function () {
            Route::get('/', [BlogController::class, 'index'])->name('index');
            Route::get('/{id}', [BlogController::class, 'show'])->name('show')->whereNumber('id'); // numeric only, so /blog/import is reachable
            Route::get('/import', [BlogController::class, 'import'])->name('import'); // Make sure this exists
            Route::post('/generate-all', [BlogController::class, 'generateAll'])->name('generate-all');

            // Import routes
            Route::post('/import/wordpress', [BlogController::class, 'importWordPress'])->name('import.wordpress');
            Route::post('/import/csv', [BlogController::class, 'importCsv'])->name('import.csv');
            Route::post('/import/manual', [BlogController::class, 'importManual'])->name('import.manual');
        });

    // Travel Guides
    Route::prefix('guides')->name('guides.')->group(function () {
        Route::get('/', function () {
            $brand = auth()->user()->activeBrand;
            $guides = $brand
                ? App\Models\TravelGuide::where('brand_id', $brand->id)->orderByDesc('created_at')->get()
                : collect();
            return view('guides.index', compact('guides'));
        })->name('index');

        Route::get('/suggest', function () {
            $service = app(App\Services\AI\TravelGuideSuggesterService::class);
            $brand = auth()->user()->activeBrand;
            $service->suggestGuides($brand);
            return redirect()->route('guides.index')->with('message', 'Guide suggestions generated!');
        })->name('suggest');
    });

    // // Affiliate Suggestions
    // Route::prefix('affiliate')->name('affiliate.')->group(function () {
    //     Route::get('/suggest', function () {
    //         $service = app(App\Services\AI\AffiliateLinkSuggesterService::class);
    //         $brand = auth()->user()->activeBrand;
    //         $results = $service->suggestForAllBlogs($brand);
    //         return redirect()->route('affiliate.index')->with('message', 'Affiliate suggestions generated!');
    //     })->name('suggest');
    // });

    // Affiliate
    Route::prefix('affiliate')->name('affiliate.')->group(function () {
        Route::get('/', [App\Http\Controllers\AffiliateController::class, 'index'])->name('index');
        Route::post('/collect', [App\Http\Controllers\AffiliateController::class, 'collect'])->name('collect');
    });

    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [App\Http\Controllers\AnalyticsController::class, 'index'])->name('index');
    });

    // Ahrefs
    Route::prefix('ahrefs')->name('ahrefs.')->group(function () {
        Route::get('/', [App\Http\Controllers\AhrefsController::class, 'index'])->name('index');
        Route::post('/collect', [App\Http\Controllers\AhrefsController::class, 'collect'])->name('collect');
    });

    // Page Scanner
    Route::prefix('scanner')->name('scanner.')->group(function () {
        Route::get('/', [App\Http\Controllers\PageScannerController::class, 'index'])->name('index');
        Route::get('/scan', function () {
            $brand = auth()->user()->activeBrand;
            $actions = App\Models\AiAction::where('brand_id', $brand->id)
                ->where('status', 'approved')
                ->whereNotNull('target_url')
                ->get();
            return view('scanner.scan', compact('actions'));
        })->name('scan');
        Route::post('/scan', [App\Http\Controllers\PageScannerController::class, 'scan'])->name('scan.post');
        Route::post('/scan-all', [App\Http\Controllers\PageScannerController::class, 'scanAll'])->name('scan-all');
        Route::get('/{id}', [App\Http\Controllers\PageScannerController::class, 'show'])->name('show');
        Route::put('/{id}/url', [App\Http\Controllers\PageScannerController::class, 'updateUrl'])->name('update-url');
        Route::post('/{id}/rescan', [App\Http\Controllers\PageScannerController::class, 'rescan'])->name('rescan');
    });

    // User approval: approvers (super-admin, or someone they appoint) review sign-ups;
    // only the super-admin appoints approvers.
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        $c = App\Http\Controllers\UserApprovalController::class;
        Route::middleware('approver')->group(function () use ($c) {
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/{user}/approve', [$c, 'approve'])->name('approve')->whereNumber('user');
            Route::post('/{user}/reject', [$c, 'reject'])->name('reject')->whereNumber('user');
        });
        Route::middleware('superadmin')->group(function () use ($c) {
            Route::post('/{user}/make-approver', [$c, 'makeApprover'])->name('make-approver')->whereNumber('user');
            Route::post('/{user}/remove-approver', [$c, 'removeApprover'])->name('remove-approver')->whereNumber('user');
        });
    });

    // System documentation: super-admins only (404 for everyone else, so the page's existence is not disclosed)
    Route::get('/admin/docs', fn() => view('admin.docs'))->middleware('superadmin')->name('admin.docs');

    // routes/web.php
    Route::get('/admin/contacts', function () {
        $contacts = \App\Models\Contact::latest()->paginate(20);
        return view('admin.contacts', compact('contacts'));
    })->middleware('superadmin')->name('admin.contacts');

    Route::view('/recurring-issues', 'recurring-issues')->name('recurring-issues');
});
