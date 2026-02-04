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