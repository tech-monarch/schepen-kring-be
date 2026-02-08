<?php
// ... rest of your imports and routes ...

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YachtController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\QuickAuthController;
use App\Http\Controllers\AuthorizationController;
use App\Http\Controllers\BookingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// AUTH & REGISTRATION
Route::post('/login', [UserController::class, 'login']);
// Route::post('/register', [UserController::class, 'register']);
// Route::post('/register/partner', [UserController::class, 'registerPartner']); // Make sure this is uncommented

// Direct Database Registration
Route::post('/register/partner', [QuickAuthController::class, 'registerPartner']);
Route::post('/register', [QuickAuthController::class, 'registerUser']);
// ANALYTICS
Route::post('/analytics/track', [AnalyticsController::class, 'track']);
Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);

// PUBLIC YACHT ROUTES
Route::get('yachts', [YachtController::class, 'index']);
Route::get('yachts/{id}', [YachtController::class, 'show']);
Route::get('bids/{id}/history', [BidController::class, 'history']);
Route::post('/ai/chat', [GeminiController::class, 'chat']);

// In api.php (temporarily)
Route::post('/test-yacht-update', function(Request $request) {
    try {
        $yacht = \App\Models\Yacht::find(140);
        
        // Test setting a single field
        $yacht->name = $request->input('name', 'Test Name');
        $yacht->save();
        
        return response()->json(['success' => true, 'yacht' => $yacht]);
    } catch (\Exception $e) {
        \Log::error('Test error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
    // Put this in the Public Routes section
Route::get('yachts/{id}/available-slots', [BookingController::class, 'getAvailableSlots']);
// Add this line
Route::get('yachts/{id}/available-dates', [BookingController::class, 'getAvailableDates']);
// PROTECTED ROUTES (Must be logged in)
Route::middleware('auth:sanctum')->group(function () {
    
    // CUSTOMER ACTIONS
    Route::post('bids/place', [BidController::class, 'placeBid']);

    // YACHT MANAGEMENT (This is where your Account Setup will post to)
    Route::middleware('permission:manage yachts')->group(function () {
        Route::post('yachts', [YachtController::class, 'store']);
        // Route::post('yachts/{id}', [YachtController::class, 'update']);
        Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);
        Route::delete('yachts/{id}', [YachtController::class, 'destroy']);
        Route::delete('/gallery/{id}', [YachtController::class, 'deleteGalleryImage']);
        Route::post('yachts/ai-classify', [YachtController::class, 'classifyImages']);
    });
    Route::post('yachts/{id}', [YachtController::class, 'update']);
    Route::put('yachts/{id}', [YachtController::class, 'update']);

    Route::prefix('partner')->group(function () {
        Route::post('yachts', [YachtController::class, 'store']);
        Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);
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

    // USER MANAGEMENT
    Route::middleware('permission:manage users')->group(function () {
        Route::get('permissions', [UserController::class, 'getAllPermissions']);
        Route::get('roles', [UserController::class, 'getAllRoles']);
        Route::apiResource('users', UserController::class);


        // ADD THIS LINE [cite: 148]
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate']);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('users/{user}/toggle-permission', [UserController::class, 'togglePermission']);
    });

    // PROFILE
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);

    
    // New Authorization Endpoints
    Route::get('user/authorizations/{id}', [AuthorizationController::class, 'getUserPermissions']);
Route::post('user/authorizations/{id}/sync', [AuthorizationController::class, 'syncAuthorizations']);    
    // Existing User Management [cite: 71]
    Route::apiResource('users', UserController::class);

// Put this in the Protected Routes section
Route::post('yachts/{id}/book', [BookingController::class, 'storeBooking']);
});