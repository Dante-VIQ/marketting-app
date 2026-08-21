<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BlogController;
use App\Models\Lead;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Brand Management
    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::post('/', [BrandController::class, 'store'])->name('store');
        Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
        Route::post('/{brand}/toggle-active', [BrandController::class, 'toggleActive'])->name('toggle-active');
    });

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
            return view('analytics.revenue-leaks');
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
            return view('campaigns.show', ['id' => $id]);
        })->name('show');
    });

    // Leads
    Route::prefix('leads')->name('leads.')->group(function () {
        Route::get('/', function () {
            return view('leads.index');
        })->name('index');
        Route::get('/{id}', function ($id) {
            return view('leads.show', ['id' => $id]);
        })->name('show');
    });

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

        // Process the update
        $qualifier = app(App\Services\Lead\LeadQualifierService::class);
        $qualifier->processResponse($lead, $request->except('_token'));

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

    Route::prefix('schedule')->name('schedule.')->group(function () {
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

    Route::prefix('blog')->name('blog.')->group(function () {
        Route::get('/', [BlogController::class, 'index'])->name('index');
        Route::get('/{id}', [BlogController::class, 'show'])->name('show');
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
            return view('guides.index');
        })->name('index');
        Route::get('/suggest', function () {
            $service = app(App\Services\AI\TravelGuideSuggesterService::class);
            $brand = auth()->user()->activeBrand;
            $suggestions = $service->suggestGuides($brand);
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
});
