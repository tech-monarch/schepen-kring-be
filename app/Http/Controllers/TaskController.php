<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    /**
     * Display tasks based on user role and assignment
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // If user is admin, return all tasks with assignments
        if ($user->role === 'Admin') {
            $tasks = Task::with(['assignedTo:id,name,email,role', 'yacht:id,name,vessel_id'])
                ->orderBy('created_at', 'desc')
                ->get();
        } 
        // If user is employee, return only their assigned tasks
        else if ($user->role === 'Employee') {
            $tasks = Task::with(['assignedTo:id,name,email,role', 'yacht:id,name,vessel_id'])
                ->where('assigned_to', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        // For customers or other roles, return empty or specific tasks
        else {
            $tasks = Task::with(['assignedTo:id,name,email,role', 'yacht:id,name,vessel_id'])
                ->where('assigned_to', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json($tasks);
    }

    /**
     * Store a newly created task
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:Low,Medium,High,Urgent',
            'status' => 'required|in:To Do,In Progress,Done',
            'assigned_to' => 'required|exists:users,id',
            'yacht_id' => 'nullable|exists:yachts,id',
            'due_date' => 'nullable|date',
        ]);

        // Verify assignment permission
        $assignee = User::find($validated['assigned_to']);
        if (!$assignee) {
            return response()->json(['error' => 'Assignee not found'], 404);
        }

        // Ensure user can't assign to themselves if they're not admin?
        // Or ensure employee can only create tasks for themselves?
        $user = $request->user();
        
        // Log the task creation for audit
        Log::info('Task created', [
            'created_by' => $user->id,
            'assigned_to' => $validated['assigned_to'],
            'title' => $validated['title']
        ]);

        $task = Task::create($validated);

        return response()->json($task->load(['assignedTo', 'yacht']), 201);
    }

    /**
     * Display specific task with permission check
     */
    public function show(Request $request, Task $task)
    {
        $user = $request->user();
        
        // Check if user can view this task
        if ($user->role !== 'Admin' && $task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized to view this task'], 403);
        }

        return response()->json($task->load(['assignedTo', 'yacht']));
    }

    /**
     * Update task with permission check
     */
    public function update(Request $request, Task $task)
    {
        $user = $request->user();
        
        // Check permission: Admin or assigned user can update
        if ($user->role !== 'Admin' && $task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized to update this task'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:Low,Medium,High,Urgent',
            'status' => 'sometimes|in:To Do,In Progress,Done',
            'assigned_to' => 'sometimes|exists:users,id',
            'yacht_id' => 'nullable|exists:yachts,id',
            'due_date' => 'nullable|date',
        ]);

        // If changing assignment, verify new assignee exists
        if (isset($validated['assigned_to']) && $validated['assigned_to'] != $task->assigned_to) {
            $assignee = User::find($validated['assigned_to']);
            if (!$assignee) {
                return response()->json(['error' => 'New assignee not found'], 404);
            }
        }

        $task->update($validated);

        return response()->json($task->load(['assignedTo', 'yacht']));
    }

    /**
     * Delete task with permission check
     */
    public function destroy(Request $request, Task $task)
    {
        $user = $request->user();
        
        // Only admins can delete tasks
        if ($user->role !== 'Admin') {
            return response()->json(['error' => 'Only admins can delete tasks'], 403);
        }

        $task->delete();

        return response()->json([
            'message' => 'Task successfully deleted.'
        ], 200);
    }

    /**
     * Fast status update with permission check
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:To Do,In Progress,Done'
        ]);

        $task = Task::findOrFail($id);
        $user = $request->user();
        
        // Check if user can update this task's status
        if ($user->role !== 'Admin' && $task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized to update this task'], 403);
        }

        $task->update(['status' => $request->status]);

        // Log status change
        Log::info('Task status updated', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'new_status' => $request->status
        ]);

        return response()->json($task);
    }

    /**
     * Get tasks assigned to specific user (for admins)
     */
    public function getUserTasks($userId)
    {
        $tasks = Task::with(['assignedTo:id,name,email', 'yacht:id,name,vessel_id'])
            ->where('assigned_to', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Get current user's tasks (for employees)
     */
    public function myTasks(Request $request)
    {
        $user = $request->user();
        
        $tasks = Task::with(['assignedTo:id,name,email', 'yacht:id,name,vessel_id'])
            ->where('assigned_to', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }
}