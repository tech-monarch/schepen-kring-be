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
use App\Http\Controllers\PagePermissionController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ActivityLogController; // Add this
use App\Http\Controllers\NotificationController; // Add this
use App\Http\Controllers\FaqController; // Add this
use App\Http\Controllers\SystemLogController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// AUTH & REGISTRATION
Route::post('/login', [UserController::class, 'login']);
// Route::post('/register', [UserController::class, 'register']);
// Route::post('/register/partner', [UserController::class, 'registerPartner']); // Make sure this is uncommented

// In your routes file (api.php), add this before the protected routes:

// PUBLIC USER ROUTE FOR TASK ASSIGNMENT
Route::get('/public/users/employees', [UserController::class, 'getEmployeesForTasks']);

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
    Route::get('my-yachts', [YachtController::class, 'partnerIndex']);

    // BID FINALIZATION
    Route::middleware('permission:accept bids')->group(function () {
        Route::post('bids/{id}/accept', [BidController::class, 'acceptBid']);
        Route::post('bids/{id}/decline', [BidController::class, 'declineBid']);
    });

    // TASK MANAGEMENT

    // Task routes
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/my', [TaskController::class, 'myTasks']);
    Route::get('/tasks/calendar', [TaskController::class, 'calendarTasks']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{id}', [TaskController::class, 'show']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    
    // Admin only - get tasks by user
    Route::get('/users/{userId}/tasks', [TaskController::class, 'getUserTasks'])
        ->middleware('permission:manage tasks');
    
    // User and Yacht routes (for dropdowns)
    Route::get('/users/staff', [UserController::class, 'getStaff']);

    // USER MANAGEMENT
    Route::middleware('permission:manage users')->group(function () {
        // Route::get('permissions', [UserController::class, 'getAllPermissions']);
        // Route::get('roles', [UserController::class, 'getAllRoles']);
        // Route::apiResource('users', UserController::class);


        // ADD THIS LINE [cite: 148]
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate']);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('users/{user}/toggle-permission', [UserController::class, 'togglePermission']);
    });

    // PROFILE
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);

    
    // New Authorization Endpoints
    Route::get('user/authorizations/{id}', [AuthorizationController::class, 'getUserPermissions']);
Route::post('user/authorizations/{id}/sync', [AuthorizationController::class, 'syncAuthorizations']);    
    // Existing User Management [cite: 71]
    Route::apiResource('users', UserController::class);

// Put this in the Protected Routes section
Route::post('yachts/{id}/book', [BookingController::class, 'storeBooking']);


    // ======================= NOTIFICATION ROUTES =======================
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'delete']);
    });

    // ======================= ACTIVITY LOG ROUTES =======================
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('/stats', [ActivityLogController::class, 'stats']);
        Route::get('/user/{userId}', [ActivityLogController::class, 'userActivity']);
        Route::get('/my-activity', [ActivityLogController::class, 'myActivity']);
        Route::delete('/clear-old', [ActivityLogController::class, 'clearOldLogs']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Page permissions routes
    Route::get('/page-permissions', [PagePermissionController::class, 'index']);
    Route::get('/users/{user}/page-permissions', [PagePermissionController::class, 'getUserPermissions']);
    Route::post('/users/{user}/page-permissions/update', [PagePermissionController::class, 'updatePermission']);
    Route::post('/users/{user}/page-permissions/bulk-update', [PagePermissionController::class, 'bulkUpdate']);
    Route::post('/users/{user}/page-permissions/reset', [PagePermissionController::class, 'resetPermissions']);
});

// Blog routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/blogs', [BlogController::class, 'index']);
    Route::post('/blogs', [BlogController::class, 'store']);
    Route::get('/blogs/{id}', [BlogController::class, 'show']);
    Route::put('/blogs/{id}', [BlogController::class, 'update']);
    Route::delete('/blogs/{id}', [BlogController::class, 'destroy']);
    Route::get('/blogs/slug/{slug}', [BlogController::class, 'showBySlug']);
    Route::get('/blogs/featured', [BlogController::class, 'featured']);
});

// Public blog routes (for reading)
Route::get('/public/blogs', [BlogController::class, 'index']);
Route::get('/public/blogs/{id}', [BlogController::class, 'show']);
Route::get('/public/blogs/slug/{slug}', [BlogController::class, 'showBySlug']);
Route::get('/public/blogs/featured', [BlogController::class, 'featured']);
Route::post('/public/blogs/{id}/view', [BlogController::class, 'incrementViews']); // Add this


// Add to your existing routes
// Public FAQ routes
Route::get('/faqs', [FaqController::class, 'index']);
Route::get('/faqs/{id}', [FaqController::class, 'show']);
Route::post('/faqs/ask-gemini', [FaqController::class, 'askGemini']);
Route::get('/faqs/stats', [FaqController::class, 'stats']);
Route::post('/faqs/{id}/rate-helpful', [FaqController::class, 'rateHelpful']);
Route::post('/faqs/{id}/rate-not-helpful', [FaqController::class, 'rateNotHelpful']);

// Protected FAQ routes (Admin only)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/faqs', [FaqController::class, 'store']);
    Route::put('/faqs/{id}', [FaqController::class, 'update']);
    Route::delete('/faqs/{id}', [FaqController::class, 'destroy']);
    Route::post('/faqs/train-gemini', [FaqController::class, 'trainGemini']);
    Route::get('/faqs/training-status', [FaqController::class, 'getTrainingStatus']);
});


// routes/api.php

// System Log routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('system-logs')->group(function () {
        Route::get('/', [SystemLogController::class, 'index']);
        Route::get('/summary', [SystemLogController::class, 'summary']);
        Route::get('/export', [SystemLogController::class, 'export']);
        Route::get('/user/{userId}', [SystemLogController::class, 'userActivity']);
        Route::get('/{id}', [SystemLogController::class, 'show']);
    });
});