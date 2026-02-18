<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    /**
     * Get tasks based on user role
     */
public function index(Request $request)
{
    $user = $request->user();

    if ($user->role === 'Admin') {
        $tasks = Task::with(['assignedTo', 'creator', 'yacht'])->latest()->get();
    } elseif ($user->role === 'Partner') {
        $employeeIds = User::where('partner_id', $user->id)->pluck('id');
        $tasks = Task::with(['assignedTo', 'creator', 'yacht'])
            ->where(function ($q) use ($user, $employeeIds) {
                $q->where('created_by', $user->id)
                  ->orWhereIn('assigned_to', $employeeIds);
            })
            ->latest()
            ->get();
    } else {
        $tasks = Task::with(['assignedTo', 'creator', 'yacht'])
            ->forUser($user->id)
            ->latest()
            ->get();
    }

    return response()->json($tasks);
}

    /**
     * Create a new task
     */
// In TaskController.php, update the store method validation:

public function store(Request $request)
{
    try {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'required|in:Low,Medium,High,Urgent,Critical',
            'status'      => 'required|in:To Do,In Progress,Done',
            'due_date'    => 'required|date',
            'type'        => 'required|in:personal,assigned',
            'assigned_to' => 'required_if:type,assigned|integer|exists:users,id',
            'yacht_id'    => 'nullable|integer|exists:yachts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['created_by'] = $user->id;

        if ($data['type'] === 'personal') {
            $data['user_id'] = $user->id;
            $data['assigned_to'] = $user->id;
            $data['assignment_status'] = null;
        } else {
            $data['assignment_status'] = 'pending';
            $data['user_id'] = null;
        }

        // Safely cast assigned_to if present
        if (!empty($data['assigned_to'])) {
            $data['assigned_to'] = (int)$data['assigned_to'];
        } else {
            $data['assigned_to'] = null;
        }

        // Safely handle yacht_id – only set if present
        if (array_key_exists('yacht_id', $data) && !empty($data['yacht_id'])) {
            $data['yacht_id'] = (int)$data['yacht_id'];
        } else {
            $data['yacht_id'] = null; // ensure the key exists with null value
        }

        $task = Task::create($data);
        return response()->json($task->load(['assignedTo', 'creator', 'yacht']), 201);
    } catch (\Exception $e) {
        \Log::error('Task store error: ' . $e->getMessage());
        return response()->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
    }
}
    /**
     * Get a specific task
     */
    public function show($id)
    {
        try {
            $task = Task::with(['assignedTo', 'yacht', 'creator'])->find($id);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            $user = request()->user();
            
            // Check permissions
            if ($user->role !== 'Admin' && 
                $task->assigned_to !== $user->id && 
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json($task);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching task: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update a task
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $task = Task::find($id);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            // Check permissions
            if ($user->role !== 'Admin' && 
                $task->assigned_to !== $user->id && 
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'priority' => 'sometimes|in:Low,Medium,High,Urgent,Critical',
                'status' => 'sometimes|in:To Do,In Progress,Done',
                'assigned_to' => 'sometimes|exists:users,id',
                'yacht_id' => 'nullable|exists:yachts,id',
                'due_date' => 'sometimes|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Employees can only update status and their personal tasks
            if ($user->role !== 'Admin' && $task->type === 'assigned') {
                $request->merge(['status' => $request->input('status', $task->status)]);
                // Only allow status update for assigned tasks
                $task->update(['status' => $request->status]);
            } else {
                $task->update($request->all());
            }

            return response()->json($task->load(['assignedTo', 'yacht', 'creator']));
            
        } catch (\Exception $e) {
            \Log::error('Error updating task: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Delete a task
     */
    public function destroy($id)
    {
        try {
            $user = request()->user();
            $task = Task::find($id);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            // Check permissions
            if ($user->role !== 'Admin' && 
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $task->delete();

            return response()->json(['message' => 'Task deleted successfully']);
            
        } catch (\Exception $e) {
            \Log::error('Error deleting task: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = $request->user();
            $task = Task::find($id);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            // Check permissions
            if ($user->role !== 'Admin' && 
                $task->assigned_to !== $user->id && 
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:To Do,In Progress,Done'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $task->update(['status' => $request->status]);

            return response()->json($task);
            
        } catch (\Exception $e) {
            \Log::error('Error updating task status: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get current user's tasks
     */
    public function myTasks(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $tasks = Task::with(['assignedTo:id,name,email', 'yacht:id,name', 'creator:id,name'])
                ->where(function($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                          ->orWhere('user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($tasks);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching user tasks: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get tasks by user (for admin)
     */
    public function getUserTasks($userId)
    {
        try {
            $user = request()->user();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $tasks = Task::with(['assignedTo', 'yacht', 'creator'])
                ->where('assigned_to', $userId)
                ->orWhere('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($tasks);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching user tasks: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get tasks for calendar view
     */
    public function calendarTasks(Request $request)
    {
        try {
            $user = $request->user();
            $start = $request->input('start');
            $end = $request->input('end');
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $query = Task::with(['assignedTo:id,name', 'yacht:id,name']);
            
            if ($user->role !== 'Admin') {
                $query->where(function($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('user_id', $user->id);
                });
            }

            if ($start && $end) {
                $query->whereBetween('due_date', [$start, $end]);
            }

            $tasks = $query->get()->map(function($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'start' => $task->due_date,
                    'end' => $task->due_date ? (new \DateTime($task->due_date))->modify('+1 day')->format('Y-m-d') : null,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'type' => $task->type,
                    'assigned_to' => $task->assignedTo ? $task->assignedTo->name : null,
                    'yacht' => $task->yacht ? $task->yacht->name : null,
                    'color' => $this->getPriorityColor($task->priority),
                ];
            });

            return response()->json($tasks);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching calendar tasks: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function getPriorityColor($priority)
    {
        switch ($priority) {
            case 'Critical': return '#dc2626';
            case 'Urgent': return '#ea580c';
            case 'High': return '#d97706';
            case 'Medium': return '#3b82f6';
            case 'Low': return '#6b7280';
            default: return '#6b7280';
        }
    }
    public function scopeForUser($query, $userId)
{
    return $query->where(function ($q) use ($userId) {
        $q->where('assigned_to', $userId)
          ->orWhere('user_id', $userId)
          ->orWhere('created_by', $userId);
    });
}

public function getPartnerEmployees(Request $request)
{
    $user = $request->user();

    if ($user->role === 'Partner') {
        $employees = User::where('partner_id', $user->id)
            ->where('role', 'Employee')   // <-- filter only employees
            ->where('status', 'Active')
            ->select('id', 'name', 'email', 'role')
            ->get();
    } elseif ($user->role === 'Employee' && $user->partner_id) {
        $employees = User::where('partner_id', $user->partner_id)
            ->where('role', 'Employee')
            ->where('id', '!=', $user->id)
            ->where('status', 'Active')
            ->select('id', 'name', 'email', 'role')
            ->get();
    } else {
        return response()->json([]);
    }

    return response()->json($employees);
}
}