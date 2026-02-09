<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // NO PERMISSION CHECK - Anyone authenticated can view logs
        // But we'll filter by user if not admin
        $user = Auth::user();
        $query = ActivityLog::with('user')
            ->orderBy('created_at', 'desc');

        // If not admin, only show their own logs
        if ($user->role !== 'Admin') {
            $query->where('user_id', $user->id);
        }

        // Apply filters
        if ($request->has('user_id')) {
            // Only admin can filter by other users
            if ($user->role === 'Admin') {
                $query->where('user_id', $request->user_id);
            }
        }

        if ($request->has('log_type')) {
            $query->where('log_type', $request->log_type);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        ]);
    }

    public function stats(Request $request)
    {
        // Still restrict stats to Admin only (more sensitive)
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        // Last 30 days statistics
        $stats = ActivityLog::select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_actions'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            ])
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->get();

        // Top 10 actions
        $topActions = ActivityLog::select([
                'action',
                DB::raw('COUNT(*) as count')
            ])
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Top 10 users by activity
        $topUsers = ActivityLog::select([
                'user_id',
                DB::raw('COUNT(*) as count')
            ])
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->with('user:id,name,email,role')
            ->get();

        return response()->json([
            'daily_stats' => $stats,
            'top_actions' => $topActions,
            'top_users' => $topUsers
        ]);
    }

    public function userActivity($userId)
    {
        $currentUser = Auth::user();
        
        // Users can only see their own activity logs
        // Admin can see anyone's logs
        if ($currentUser->role !== 'Admin' && $currentUser->id != $userId) {
            return response()->json(['message' => 'You can only view your own activity logs'], 403);
        }

        $activities = ActivityLog::where('user_id', $userId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($activities);
    }

    public function myActivity(Request $request)
    {
        // Simple endpoint for users to see only their own logs
        $user = Auth::user();
        
        $query = ActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('log_type')) {
            $query->where('log_type', $request->log_type);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        $perPage = $request->get('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }

    public function clearOldLogs()
    {
        // Only admins can clear logs
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        // Delete logs older than 90 days
        $deleted = ActivityLog::where('created_at', '<', now()->subDays(90))->delete();

        return response()->json([
            'message' => "Deleted {$deleted} old log entries"
        ]);
    }
}