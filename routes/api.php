<?php

use App\Http\Controllers\AgentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Ping
|--------------------------------------------------------------------------
*/

Route::get('/ping', [AgentController::class, 'ping']);

/*
|--------------------------------------------------------------------------
| Agent API — All routes require API key + brand check + rate limit
|--------------------------------------------------------------------------
*/
Route::prefix('agent')
    ->name('agent.')
    ->middleware(['verify.api.key', 'agent.brand', 'throttle:agent'])
    ->group(function () {

        /*
        |-----------------------------------------
        | Health
        |-----------------------------------------
        */
        Route::get('/ai/ping', [AgentController::class, 'pingAI'])->name('ai.ping');

        /*
        |-----------------------------------------
        | Data Freshness
        |-----------------------------------------
        */
        Route::get('/data-status/{brandId}', [AgentController::class, 'dataStatus'])->name('data.status');
        Route::post('/refresh-data/{brandId}', [AgentController::class, 'refreshData'])->name('data.refresh');

        /*
        |-----------------------------------------
        | Brief
        |-----------------------------------------
        */
        Route::get('/brief/{brandId}', [AgentController::class, 'getBrief'])->name('brief');

        /*
        |-----------------------------------------
        | Tours
        |-----------------------------------------
        */
        Route::get('/tours/{brandId}', [AgentController::class, 'getTourPackages'])->name('tours.index');

        /*
        |-----------------------------------------
        | Opportunities
        |-----------------------------------------
        */
        Route::get('/opportunities/{brandId}', [AgentController::class, 'getOpportunities'])->name('opportunities.index');
        Route::post('/opportunities/check', [AgentController::class, 'checkOpportunities'])->name('opportunities.check');
        Route::post('/opportunities/mark', [AgentController::class, 'markOpportunity'])->name('opportunities.mark');
        Route::get('/opportunities/history/{brandId}/{stableKey}', [AgentController::class, 'getOpportunityHistory'])->name('opportunities.history');
        Route::post('/opportunities/resolve/{brandId}/{stableKey}', [AgentController::class, 'resolveOpportunity'])->name('opportunities.resolve');

        /*
        |-----------------------------------------
        | Analytics
        |-----------------------------------------
        */
        Route::get('/analytics/{brandId}', [AgentController::class, 'getAnalytics'])->name('analytics');
        Route::get('/analytics/analyze/{brandId}', [AgentController::class, 'analyzeAnalytics'])->name('analytics.analyze');

        /*
        |-----------------------------------------
        | SEO
        |-----------------------------------------
        */
        Route::get('/seo/issues/{brandId}', [AgentController::class, 'getSeoIssues'])->name('seo.issues');
        Route::get('/seo/rankings/{brandId}', [AgentController::class, 'getKeywordRankings'])->name('seo.rankings');
        Route::get('/seo/recommendations/{brandId}/{issueId}', [AgentController::class, 'getSeoRecommendations'])->name('seo.recommendations');
        Route::get('/seo/issue/{brandId}/{issueId}', [AgentController::class, 'getSeoIssueById'])->name('seo.issue');
        Route::post('/seo/analyze/{brandId}/{issueId}', [AgentController::class, 'analyzeSeoIssue'])->name('seo.analyze');

        /*
        |-----------------------------------------
        | Leads
        |-----------------------------------------
        */
        Route::get('/leads/pending/{brandId}', [AgentController::class, 'getPendingLeads'])->name('leads.pending');
        Route::get('/lead/engagement/{brandId}/{leadId}', [AgentController::class, 'getLeadEngagement'])->name('leads.engagement');
        Route::get('/lead/context/{brandId}/{leadId}', [AgentController::class, 'getLeadContext'])->name('leads.context');
        Route::get('/lead/{brandId}/{leadId}', [AgentController::class, 'getLead'])->name('leads.show');
        Route::post('/lead/follow-up/{brandId}', [AgentController::class, 'generateFollowUpMessage'])->name('leads.follow-up');

        /*
        |-----------------------------------------
        | Campaigns
        |-----------------------------------------
        */
        Route::get('/campaigns/{brandId}', [AgentController::class, 'getCampaigns'])->name('campaigns.index');
        Route::post('/campaigns/pause', [AgentController::class, 'pauseCampaign'])->name('campaigns.pause');

        /*
        |-----------------------------------------
        | Content
        |-----------------------------------------
        */
        Route::post('/content/generate', [AgentController::class, 'triggerContentGeneration'])->name('content.generate');
        Route::post('/content/outline', [AgentController::class, 'generateContentOutline'])->name('content.outline');
        Route::post('/content/gap-analysis/{brandId}', [AgentController::class, 'analyzeContentGap'])->name('content.gap-analysis');

        /*
        |-----------------------------------------
        | Execution
        |-----------------------------------------
        */
        Route::post('/scan/{brandId}', [AgentController::class, 'scan'])->name('scan');
        Route::post('/actions/pending', [AgentController::class, 'executeAction'])->name('actions.pending');
        Route::post('/actions/{actionId}/execute', [AgentController::class, 'executeApprovedAction'])->name('actions.execute');
        Route::post('/actions/{actionId}/request-rollback', [AgentController::class, 'requestRollback'])->name('actions.request-rollback');

        Route::get('/actions/count/{brandId}', [AgentController::class, 'getActionCount'])->name('actions.count');

        /*
        |-----------------------------------------
        | Actions / Outcomes
        |-----------------------------------------
        */
        Route::get('/actions/outcomes/{brandId}', [AgentController::class, 'getPendingOutcomes'])->name('actions.outcomes');
        Route::post('/actions/acknowledge', [AgentController::class, 'acknowledgeOutcomes'])->name('actions.acknowledge');
        Route::post('/actions/{actionId}/authorize-retry', [AgentController::class, 'authorizeRetry'])->name('actions.authorize-retry');

        /*
        |-----------------------------------------
        | Metrics — per-action snapshot for verification
        |-----------------------------------------
        */
        Route::get('/metrics/{brandId}/{actionId}', [AgentController::class, 'getActionMetrics'])->name('metrics');

        /*
        |-----------------------------------------
        | Escalations
        |-----------------------------------------
        */
        Route::get('/escalations/{brandId}', [AgentController::class, 'getPendingEscalations'])->name('escalations.index');
        Route::post('/escalations/{actionId}/respond', [AgentController::class, 'respondToEscalation'])->name('escalations.respond');

        /*
        |-----------------------------------------
        | Calibration
        |-----------------------------------------
        */
        Route::get('/calibration/{brandId}', [AgentController::class, 'getCalibration'])->name('calibration.index');
        Route::get('/calibration/summary/{brandId}', [AgentController::class, 'getCalibrationSummary'])->name('calibration.summary');
        Route::post('/calibration/record', [AgentController::class, 'recordCalibration'])->name('calibration.record');

        /*
        |-----------------------------------------
        | Verification — SPECIFIC routes FIRST (due, register, record)
        |-----------------------------------------
        */
        Route::get('/verification/due/{brandId}', [AgentController::class, 'getDueVerifications'])->name('verification.due');
        Route::post('/verification/register', [AgentController::class, 'registerVerification'])->name('verification.register');
        Route::post('/verification/record', [AgentController::class, 'recordVerification'])->name('verification.record');
        Route::post('/verification/start/{brandId}', [AgentController::class, 'startVerification'])->name('verification.start');
        Route::post('/verification/complete/{brandId}/{verificationId}', [AgentController::class, 'completeVerification'])->name('verification.complete');

        /*
        |-----------------------------------------
        | Learning
        |-----------------------------------------
        */
        Route::post('/learn/{brandId}', [AgentController::class, 'recordLearning'])->name('learn');
        Route::get('/experiences/similar/{brandId}', [AgentController::class, 'getSimilarExperiences'])->name('experiences.similar');
    });
