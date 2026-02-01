<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the manifest (To Do, In Progress, Done).
     */
    public function index()
    {
        // Loading relationships so the frontend sees who is assigned and which boat it's for
        $tasks = Task::with(['assignedTo:id,name,email', 'yacht:id,name,vessel_id'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Store a newly created task in the terminal.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:Low,Medium,High,Urgent',
            'status' => 'required|in:To Do,In Progress,Done',
            'assigned_to' => 'nullable|exists:users,id',
            'yacht_id' => 'nullable|exists:yachts,id',
            'due_date' => 'nullable|date',
        ]);

        $task = Task::create($validated);

        return response()->json($task->load(['assignedTo', 'yacht']), 201);
    }

    /**
     * Display the specified task.
     */
    public function show(Task $task)
    {
        return response()->json($task->load(['assignedTo', 'yacht']));
    }

    /**
     * Update the task (Used for dragging between columns or editing details).
     */
    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:Low,Medium,High,Urgent',
            'status' => 'sometimes|in:To Do,In Progress,Done',
            'assigned_to' => 'nullable|exists:users,id',
            'yacht_id' => 'nullable|exists:yachts,id',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);

        return response()->json($task->load(['assignedTo', 'yacht']));
    }

    /**
     * Terminate the task from the manifest.
     */
    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json([
            'message' => 'Task successfully purged from system.'
        ], 204);
    }

    /**
     * Fast status update (Specifically for Kanban drag-and-drop).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:To Do,In Progress,Done'
        ]);

        $task = Task::findOrFail($id);
        $task->update(['status' => $request->status]);

        return response()->json($task);
    }
}