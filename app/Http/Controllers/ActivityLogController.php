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
        // Check permission
        if (Auth::user()->role !== 'Admin' && !Auth::user()->hasPermissionTo('view activity logs')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = ActivityLog::with('user')
            ->orderBy('created_at', 'desc');

        // Apply filters
        $query = $query->filter($request->all());

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
        // Check permission
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
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
        // Check permission - only admin or the user themselves
        if (Auth::user()->role !== 'Admin' && Auth::id() != $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $activities = ActivityLog::where('user_id', $userId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($activities);
    }

    public function clearOldLogs()
    {
        // Only admins can clear logs
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete logs older than 90 days
        $deleted = ActivityLog::where('created_at', '<', now()->subDays(90))->delete();

        return response()->json([
            'message' => "Deleted {$deleted} old log entries"
        ]);
    }
}