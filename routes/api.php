<?php

use App\Http\Controllers\Api\TravelLeadController;
use App\Http\Controllers\Api\SoftwareLeadController;
use App\Http\Controllers\Api\LeadInteractionController;

use Illuminate\Support\Facades\Route;

// Travel Booking Endpoints
Route::post('/leads/travel', [TravelLeadController::class, 'store'])
    ->name('api.leads.travel');

// Software Engineering Endpoints
Route::post('/leads/software', [SoftwareLeadController::class, 'store'])
    ->name('api.leads.software');

// Optional: Lead Interactions (for future use)
Route::post('/leads/{lead}/interactions', [LeadInteractionController::class, 'store'])
    ->name('api.leads.interactions');