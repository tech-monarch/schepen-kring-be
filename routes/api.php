<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YachtController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\QuickAuthController;

// 🌍 PUBLIC ROUTES (No middleware wrappers)
Route::post('/login', [UserController::class, 'login']);
Route::post('/register/partner', [QuickAuthController::class, 'registerPartner']);
Route::post('/register', [QuickAuthController::class, 'registerUser']);
Route::get('yachts', [YachtController::class, 'index']);
Route::get('yachts/{id}', [YachtController::class, 'show']);

// 🔐 PROTECTED ROUTES (Keep only auth:sanctum for now)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('users', UserController::class);
    // Add other protected routes here if they worked before
});