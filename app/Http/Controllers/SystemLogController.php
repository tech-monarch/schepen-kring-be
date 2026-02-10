<?php
// app/Http/Controllers/SystemLogController.php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SystemLogController extends Controller
{
    /**
     * Get all system logs with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            $query = SystemLog::with(['user:id,name,email,role'])
                ->orderBy('created_at', 'desc');
            
            // Apply filters
            if ($request->has('event_type')) {
                $eventTypes = is_array($request->event_type) ? $request->event_type : [$request->event_type];
                $query->whereIn('event_type', $eventTypes);
            }
            
            if ($request->has('entity_type')) {
                $entityTypes = is_array($request->entity_type) ? $request->entity_type : [$request->entity_type];
                $query->whereIn('entity_type', $entityTypes);
            }
            
            if ($request->has('entity_id')) {
                $query->where('entity_id', $request->entity_id);
            }
            
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }
            
            // Date range filter
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 50);
            $logs = $query->paginate($perPage);
            
            return response()->json([
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching system logs: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Get specific log details
     */
    public function show($id): JsonResponse
    {
        try {
            $log = SystemLog::with(['user:id,name,email,role'])->find($id);
            
            if (!$log) {
                return response()->json(['error' => 'Log not found'], 404);
            }
            
            return response()->json($log);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching log: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Get activity summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            // Get total counts
            $totalLogs = SystemLog::count();
            
            // Get counts by event type
            $eventTypes = SystemLog::select('event_type')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('event_type')
                ->orderBy('count', 'desc')
                ->get();
            
            // Get counts by entity type
            $entityTypes = SystemLog::select('entity_type')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('entity_type')
                ->orderBy('count', 'desc')
                ->get();
            
            // Get recent activity (last 10 logs)
            $recentActivity = SystemLog::with(['user:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            // Get activity by date (last 30 days)
            $startDate = now()->subDays(30);
            $dailyActivity = SystemLog::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();
            
            return response()->json([
                'summary' => [
                    'total_logs' => $totalLogs,
                    'event_types' => $eventTypes,
                    'entity_types' => $entityTypes,
                ],
                'recent_activity' => $recentActivity,
                'daily_activity' => $dailyActivity,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error generating summary: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Get user-specific activity
     */
    public function userActivity($userId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Allow users to view their own activity or admins to view anyone's
            if (!$user || ($user->id != $userId && $user->role !== 'Admin')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            
            $logs = SystemLog::with(['user:id,name,email'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate(50);
            
            return response()->json([
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching user activity: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Export logs (CSV)
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            
            $query = SystemLog::with(['user:id,name,email']);
            
            // Apply filters same as index
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            if ($request->has('event_type')) {
                $eventTypes = is_array($request->event_type) ? $request->event_type : [$request->event_type];
                $query->whereIn('event_type', $eventTypes);
            }
            
            if ($request->has('entity_type')) {
                $entityTypes = is_array($request->entity_type) ? $request->entity_type : [$request->entity_type];
                $query->whereIn('entity_type', $entityTypes);
            }
            
            $logs = $query->orderBy('created_at', 'desc')->get();
            
            // Generate CSV content
            $csvData = "ID,Date,Time,Event Type,Entity Type,Entity ID,User,Description,IP Address\n";
            
            foreach ($logs as $log) {
                $date = \Carbon\Carbon::parse($log->created_at)->format('Y-m-d');
                $time = \Carbon\Carbon::parse($log->created_at)->format('H:i:s');
                $userName = $log->user ? $log->user->name : 'System';
                
                $csvData .= implode(',', [
                    $log->id,
                    $date,
                    $time,
                    '"' . $log->event_type . '"',
                    '"' . $log->entity_type . '"',
                    $log->entity_id ?? 'N/A',
                    '"' . $userName . '"',
                    '"' . str_replace('"', '""', $log->description) . '"',
                    $log->ip_address ?? 'N/A'
                ]) . "\n";
            }
            
            return response()->json([
                'csv_data' => $csvData,
                'filename' => 'system_logs_export_' . date('Y-m-d_H-i-s') . '.csv'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error exporting logs: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}