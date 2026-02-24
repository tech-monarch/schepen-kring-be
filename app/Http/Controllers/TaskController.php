<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks based on user role.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $tasks = Task::query()
            ->with(['assignedTo', 'creator', 'user', 'yacht'])
            ->when($user->role !== 'Admin', function ($query) use ($user) {
                // Non-admins see only tasks they are involved in
                $query->forUser($user->id);
            })
            ->when($user->role === 'Partner', function ($query) use ($user) {
                // Partners also see tasks of users under their partner_id
                $query->orWhereHas('assignedTo', fn($q) => $q->where('partner_id', $user->id))
                      ->orWhereHas('user', fn($q) => $q->where('partner_id', $user->id))
                      ->orWhereHas('creator', fn($q) => $q->where('partner_id', $user->id));
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Store a newly created task (personal or assigned).
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'description' => 'required|string',
            'priority'    => 'required|in:Low,Medium,High,Urgent,Critical',
            'due_date'    => 'nullable|date',
            'yacht_id'    => 'nullable|exists:yachts,id',
            'type'        => 'required|in:personal,assigned',
            'assigned_to' => 'required_if:type,assigned|nullable|exists:users,id',
        ]);

        // If type is personal, set user_id to current user
        if ($validated['type'] === 'personal') {
            $task = Task::create([
                'description' => $validated['description'],
                'priority'    => $validated['priority'],
                'due_date'    => $validated['due_date'],
                'yacht_id'    => $validated['yacht_id'],
                'type'        => 'personal',
                'user_id'     => $user->id,
                'created_by'  => $user->id,
                'status'      => 'To Do',
            ]);

            return response()->json($task, 201);
        }

        // --- Assigned task logic ---
        $assignee = User::findOrFail($validated['assigned_to']);

        // 1. Check assignee role (cannot be Customer or Seller)
        if (in_array($assignee->role, ['Customer', 'Seller'])) {
            return response()->json(['error' => 'Tasks cannot be assigned to Customers or Sellers'], 403);
        }

        // 2. Scope restrictions for non-admins
        if ($user->role !== 'Admin') {
            // Partner/Employee must share the same partner_id
            if ($assignee->partner_id !== $user->partner_id && $assignee->id !== $user->id) {
                return response()->json(['error' => 'You can only assign tasks to users under your partner'], 403);
            }
        }

        // 3. Determine assignment status
        $assignmentStatus = 'accepted'; // default for admin/partner/self
        if ($user->role === 'Employee' && $assignee->id !== $user->id) {
            $assignmentStatus = 'pending';
        }

        $task = Task::create([
            'description'        => $validated['description'],
            'priority'           => $validated['priority'],
            'due_date'           => $validated['due_date'],
            'yacht_id'           => $validated['yacht_id'],
            'type'               => 'assigned',
            'assigned_to'        => $assignee->id,
            'created_by'         => $user->id,
            'assignment_status'  => $assignmentStatus,
            'status'             => 'To Do',
        ]);

        return response()->json($task, 201);
    }

    /**
     * Display the specified task.
     */
    public function show($id)
    {
        $task = Task::with(['assignedTo', 'creator', 'user', 'yacht'])->findOrFail($id);

        if (!$this->canViewTask($task)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($task);
    }

    /**
     * Update a task (only creator or admin can update details).
     */
    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $user = Auth::user();

        // Only creator or admin can update core fields
        if ($user->id !== $task->created_by && $user->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'description' => 'sometimes|string',
            'priority'    => 'sometimes|in:Low,Medium,High,Urgent,Critical',
            'due_date'    => 'nullable|date',
            'yacht_id'    => 'nullable|exists:yachts,id',
            'status'      => 'sometimes|in:To Do,In Progress,Done',
        ]);

        $task->update($validated);

        return response()->json($task);
    }

    /**
     * Remove the specified task.
     */
    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        $user = Auth::user();

        // Only creator or admin can delete
        if ($user->id !== $task->created_by && $user->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }

    /**
     * Accept a pending task (assignee only).
     */
    public function accept($id)
    {
        $task = Task::findOrFail($id);
        $user = Auth::user();

        if ($task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Only the assignee can accept this task'], 403);
        }

        if ($task->assignment_status !== 'pending') {
            return response()->json(['error' => 'Task is not pending acceptance'], 400);
        }

        $task->update(['assignment_status' => 'accepted']);

        return response()->json(['message' => 'Task accepted', 'task' => $task]);
    }

    /**
     * Reject a pending task (assignee only).
     */
    public function reject($id)
    {
        $task = Task::findOrFail($id);
        $user = Auth::user();

        if ($task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Only the assignee can reject this task'], 403);
        }

        if ($task->assignment_status !== 'pending') {
            return response()->json(['error' => 'Task is not pending acceptance'], 400);
        }

        // Optionally, you might want to delete or mark as rejected.
        // Here we'll update status to rejected and keep it.
        $task->update(['assignment_status' => 'rejected']);

        return response()->json(['message' => 'Task rejected', 'task' => $task]);
    }

    /**
     * Update the status of a task (assignee or creator can update).
     */
    public function updateStatus(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $user = Auth::user();

        // Allowed if user is assignee, owner (user_id), or creator
        if (!in_array($user->id, [$task->assigned_to, $task->user_id, $task->created_by])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:To Do,In Progress,Done',
        ]);

        $task->update(['status' => $validated['status']]);

        return response()->json($task);
    }

    /**
     * Check if the current user can view the task.
     */
    private function canViewTask(Task $task): bool
    {
        $user = Auth::user();

        if ($user->role === 'Admin') {
            return true;
        }

        // Direct involvement
        if (in_array($user->id, [$task->assigned_to, $task->user_id, $task->created_by])) {
            return true;
        }

        // Partner can see tasks of users under them
        if ($user->role === 'Partner') {
            $inPartnerScope = false;
            if ($task->assignedTo && $task->assignedTo->partner_id === $user->id) {
                $inPartnerScope = true;
            }
            if ($task->user && $task->user->partner_id === $user->id) {
                $inPartnerScope = true;
            }
            if ($task->creator && $task->creator->partner_id === $user->id) {
                $inPartnerScope = true;
            }
            return $inPartnerScope;
        }

        return false;
    }
}