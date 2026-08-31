<?php

use App\Http\Controllers\AgentController;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')->name('agent.')->middleware(['verify.api.key'])->group(function () {
    // ===== OPPORTUNITIES =====
    Route::get('/opportunities/{brandId}', [AgentController::class, 'getOpportunities']);

    // ===== ANALYTICS =====
    Route::get('/analytics/{brandId}', [AgentController::class, 'getAnalytics']);

    // ===== SEO =====
    Route::get('/seo/issues/{brandId}', [AgentController::class, 'getSeoIssues']);
    Route::get('/seo/issue/{brandId}/{issueId}', [AgentController::class, 'getSeoIssueById']);
    Route::post('/seo/analyze/{brandId}/{issueId}', [AgentController::class, 'analyzeSeoIssue']);
    Route::get('/seo/recommendations/{brandId}/{issueId}', [AgentController::class, 'getSeoRecommendations']);
    Route::get('/seo/rankings/{brandId}', [AgentController::class, 'getKeywordRankings']);

    // ===== LEADS =====
    Route::get('/leads/pending/{brandId}', [AgentController::class, 'getPendingLeads']);
    Route::get('/lead/{brandId}/{leadId}', [AgentController::class, 'getLead']);
    Route::get('/lead/engagement/{brandId}/{leadId}', [AgentController::class, 'getLeadEngagement']);
    Route::get('/lead/context/{brandId}/{leadId}', [AgentController::class, 'getLeadContext']);
    Route::post('/lead/follow-up/{brandId}', [AgentController::class, 'generateFollowUpMessage']);

    // ===== CAMPAIGNS =====
    Route::get('/campaigns/{brandId}', [AgentController::class, 'getCampaigns']);
    Route::post('/campaigns/pause', [AgentController::class, 'pauseCampaign']);

    // ===== CONTENT =====
    Route::post('/content/generate', [AgentController::class, 'triggerContentGeneration']);
    Route::post('/content/gap-analysis/{brandId}', [AgentController::class, 'analyzeContentGap']);
    Route::post('/content/outline', [AgentController::class, 'generateContentOutline']);

    // ===== EXECUTION =====
    Route::post('/scan/{brandId}', [AgentController::class, 'scan']);
    Route::post('/actions/pending', [AgentController::class, 'executeAction']);

    // ===== VERIFICATION =====
    Route::post('/verification/start/{brandId}', [AgentController::class, 'startVerification']);
    Route::get('/verification/{brandId}/{verificationId}', [AgentController::class, 'getVerification']);
    Route::post('/verification/complete/{brandId}/{verificationId}', [AgentController::class, 'completeVerification']);

    // ===== LEARNING =====
    Route::post('/learn/{brandId}', [AgentController::class, 'recordLearning']);
    Route::get('/experiences/similar/{brandId}', [AgentController::class, 'getSimilarExperiences']);

    // ===== HEALTH =====
    Route::get('/ai/ping', [AgentController::class, 'pingAI']);
});

// Public ping
Route::get('/ping', [AgentController::class, 'ping']);