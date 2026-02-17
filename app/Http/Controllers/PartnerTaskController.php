<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PartnerTaskController extends Controller
{
    /**
     * Get all tasks belonging to users under the authenticated partner.
     */
    public function index(Request $request)
    {
        try {
            $partner = $request->user();

            if (!$partner || $partner->role !== 'Partner') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Get all user IDs that belong to this partner (including the partner themselves)
            $userIds = User::where('partner_id', $partner->id)->pluck('id');
            $userIds->push($partner->id); // include partner's own tasks

            $tasks = Task::with(['assignedTo:id,name,email', 'yacht:id,name', 'creator:id,name'])
                ->where(function ($query) use ($userIds) {
                    $query->whereIn('user_id', $userIds)
                          ->orWhereIn('assigned_to', $userIds);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($tasks);
        } catch (\Exception $e) {
            \Log::error('PartnerTaskController@index error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Create a new task (assigned to a user under this partner).
     */
    public function store(Request $request)
    {
        try {
            $partner = $request->user();

            if (!$partner || $partner->role !== 'Partner') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'title'       => 'required|string|max:255',
                'description' => 'nullable|string',
                'priority'    => 'required|in:Low,Medium,High,Urgent,Critical',
                'status'      => 'required|in:To Do,In Progress,Done',
                'assigned_to' => 'required|integer|exists:users,id',
                'yacht_id'    => 'nullable|integer|exists:yachts,id',
                'due_date'    => 'required|date',
                'type'        => 'required|in:assigned,personal',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Ensure the assigned user belongs to this partner
            $assignedUser = User::find($request->assigned_to);
            if (!$assignedUser || $assignedUser->partner_id !== $partner->id) {
                return response()->json([
                    'error' => 'You can only assign tasks to users under your partner account.'
                ], 403);
            }

            $data = $request->all();
            $data['created_by'] = $partner->id;
            $data['user_id']    = $partner->id; // The partner is the creator

            // Convert empty strings to null
            $data['assigned_to'] = (int) $data['assigned_to'];
            $data['yacht_id']    = $data['yacht_id'] ? (int) $data['yacht_id'] : null;

            $task = Task::create($data);

            return response()->json($task->load(['assignedTo', 'yacht', 'creator']), 201);
        } catch (\Exception $e) {
            \Log::error('PartnerTaskController@store error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Show a single task (only if it belongs to a user under this partner).
     */
    public function show($id)
    {
        try {
            $partner = request()->user();

            if (!$partner || $partner->role !== 'Partner') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userIds = User::where('partner_id', $partner->id)->pluck('id');
            $userIds->push($partner->id);

            $task = Task::with(['assignedTo', 'yacht', 'creator'])
                ->where(function ($query) use ($userIds) {
                    $query->whereIn('user_id', $userIds)
                          ->orWhereIn('assigned_to', $userIds);
                })
                ->find($id);

            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            return response()->json($task);
        } catch (\Exception $e) {
            \Log::error('PartnerTaskController@show error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update a task (only if it belongs to a user under this partner).
     */
    public function update(Request $request, $id)
    {
        try {
            $partner = $request->user();

            if (!$partner || $partner->role !== 'Partner') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userIds = User::where('partner_id', $partner->id)->pluck('id');
            $userIds->push($partner->id);

            $task = Task::where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                      ->orWhereIn('assigned_to', $userIds);
            })->find($id);

            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'title'       => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'priority'    => 'sometimes|in:Low,Medium,High,Urgent,Critical',
                'status'      => 'sometimes|in:To Do,In Progress,Done',
                'assigned_to' => 'sometimes|integer|exists:users,id',
                'yacht_id'    => 'nullable|integer|exists:yachts,id',
                'due_date'    => 'sometimes|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // If changing assigned_to, ensure new user is under this partner
            if ($request->has('assigned_to') && $request->assigned_to != $task->assigned_to) {
                $newAssigned = User::find($request->assigned_to);
                if (!$newAssigned || $newAssigned->partner_id !== $partner->id) {
                    return response()->json([
                        'error' => 'You can only assign tasks to users under your partner account.'
                    ], 403);
                }
            }

            $task->update($request->all());

            return response()->json($task->load(['assignedTo', 'yacht', 'creator']));
        } catch (\Exception $e) {
            \Log::error('PartnerTaskController@update error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Delete a task (only if it belongs to a user under this partner).
     */
    public function destroy($id)
    {
        try {
            $partner = request()->user();

            if (!$partner || $partner->role !== 'Partner') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userIds = User::where('partner_id', $partner->id)->pluck('id');
            $userIds->push($partner->id);

            $task = Task::where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                      ->orWhereIn('assigned_to', $userIds);
            })->find($id);

            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            $task->delete();

            return response()->json(['message' => 'Task deleted successfully']);
        } catch (\Exception $e) {
            \Log::error('PartnerTaskController@destroy error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update only the status of a task.
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $partner = $request->user();

            if (!$partner || $partner->role !== 'Partner') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userIds = User::where('partner_id', $partner->id)->pluck('id');
            $userIds->push($partner->id);

            $task = Task::where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                      ->orWhereIn('assigned_to', $userIds);
            })->find($id);

            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
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
            \Log::error('PartnerTaskController@updateStatus error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get tasks for calendar view (filtered by partner's users).
     */
    public function calendarTasks(Request $request)
    {
        try {
            $partner = $request->user();

            if (!$partner || $partner->role !== 'Partner') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userIds = User::where('partner_id', $partner->id)->pluck('id');
            $userIds->push($partner->id);

            $start = $request->input('start');
            $end   = $request->input('end');

            $query = Task::with(['assignedTo:id,name', 'yacht:id,name'])
                ->where(function ($q) use ($userIds) {
                    $q->whereIn('user_id', $userIds)
                      ->orWhereIn('assigned_to', $userIds);
                });

            if ($start && $end) {
                $query->whereBetween('due_date', [$start, $end]);
            }

            $tasks = $query->get()->map(function ($task) {
                return [
                    'id'          => $task->id,
                    'title'       => $task->title,
                    'start'       => $task->due_date,
                    'end'         => $task->due_date ? (new \DateTime($task->due_date))->modify('+1 day')->format('Y-m-d') : null,
                    'priority'    => $task->priority,
                    'status'      => $task->status,
                    'type'        => $task->type,
                    'assigned_to' => $task->assignedTo ? $task->assignedTo->name : null,
                    'yacht'       => $task->yacht ? $task->yacht->name : null,
                    'color'       => $this->getPriorityColor($task->priority),
                ];
            });

            return response()->json($tasks);
        } catch (\Exception $e) {
            \Log::error('PartnerTaskController@calendarTasks error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Helper: Get color based on priority.
     */
    private function getPriorityColor($priority)
    {
        switch ($priority) {
            case 'Critical': return '#dc2626';
            case 'Urgent':   return '#ea580c';
            case 'High':      return '#d97706';
            case 'Medium':    return '#3b82f6';
            case 'Low':       return '#6b7280';
            default:          return '#6b7280';
        }
    }
}