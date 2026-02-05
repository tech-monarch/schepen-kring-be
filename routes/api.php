<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YachtController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AnalyticsController;



// Global CORS fix for local development
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');


// FIX: Remove the closure from here and handle headers in the Controller
Route::post('/analytics/track', [AnalyticsController::class, 'track']);

// Handle Preflight (Browser check)
Route::options('/analytics/track', function() {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');
});
Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
// AUTH
Route::post('/login', [UserController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);
// Place this near your other public auth routes [cite: 47]
// Route::post('/register/partner', [UserController::class, 'registerPartner']);

// 1. PUBLIC ROUTES (Anyone can view)
Route::get('yachts', [YachtController::class, 'index']);
Route::get('yachts/{id}', [YachtController::class, 'show']);
Route::get('bids/{id}/history', [BidController::class, 'history']);

// Put this inside your sanctum group if you want to track users, 
// or outside if you want public guests to chat.
Route::post('/ai/chat', [GeminiController::class, 'chat']);
// 2. PROTECTED ROUTES (Must be logged in)
Route::middleware('auth:sanctum')->group(function () {
    
    // CUSTOMER ACTIONS
    // Route::post('bids/place', [BidController::class, 'placeBid'])
    //     ->middleware('permission:place bids');
    Route::post('bids/place', [BidController::class, 'placeBid']);
    
// Handle Preflight requests for the yacht setup
Route::options('yachts/partner-setup', function() {
    return response('', 200);
});


    // EMPLOYEE / ADMIN ACTIONS (Fleet Management)
    Route::middleware('permission:manage yachts')->group(function () {
        // Create Yacht
        Route::post('yachts', [YachtController::class, 'store']);
        
        // Update Yacht (Handles files via POST + _method=PUT)
        Route::post('yachts/{id}', [YachtController::class, 'update']);
        
        // Bulk Gallery Upload
        Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);
        // Remove Yacht
        Route::delete('yachts/{id}', [YachtController::class, 'destroy']);

        Route::delete('/gallery/{id}', [YachtController::class, 'deleteGalleryImage']);

        Route::post('yachts/ai-classify', [YachtController::class, 'classifyImages']);
    });

    // BID FINALIZATION
    Route::middleware('permission:accept bids')->group(function () {
        Route::post('bids/{id}/accept', [BidController::class, 'acceptBid']);
        Route::post('bids/{id}/decline', [BidController::class, 'declineBid']);
    });

    // TASK MANAGEMENT
    Route::middleware('permission:manage tasks')->group(function () {
        Route::apiResource('tasks', TaskController::class);
        Route::patch('tasks/{id}/status', [TaskController::class, 'updateStatus']);
    });

    // USER & PERMISSION MANAGEMENT (SuperAdmin)
    Route::middleware('permission:manage users')->group(function () {
        Route::get('permissions', [UserController::class, 'getAllPermissions']);
        Route::get('roles', [UserController::class, 'getAllRoles']);
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('users/{user}/toggle-permission', [UserController::class, 'togglePermission']);
    });


    // USER PROFILE ROUTES
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']); // Using POST for multipart/form-data support
});