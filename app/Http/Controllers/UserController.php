<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display the Global Directory of users.
     */
    public function index()
    {
        $users = User::orderBy('name', 'asc')->get();
        return response()->json($users);
    }

    /**
     * Register a new Staff Member or Customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|in:Admin,Employee,Customer',
            'status' => 'required|in:Active,Suspended',
            'access_level' => 'required|in:Full,Limited,None',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
            'access_level' => $validated['access_level'],
        ]);

        return response()->json($user, 201);
    }

    /**
     * Display a specific identity's data.
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    /**
     * Update Terminal Commands (Role, Access, Status).
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => 'sometimes|in:Admin,Employee,Customer',
            'status' => 'sometimes|in:Active,Suspended',
            'access_level' => 'sometimes|in:Full,Limited,None',
            'password' => 'sometimes|min:8',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Terminate Account (Delete from system).
     */
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'Account successfully terminated.'], 204);
    }

    /**
     * Toggle status quickly (Active/Suspended).
     */
    public function toggleStatus(User $user)
    {
        $user->status = ($user->status === 'Active') ? 'Suspended' : 'Active';
        $user->save();

        return response()->json($user);
    }

    // In app/Http/Controllers/UserController.php

    public function togglePermission(Request $request, \App\Models\User $user)
    {
        // Ensure the admin is not taking away their own 'manage users' permission!
        $request->validate([
            'permission' => 'required|string|exists:permissions,name'
        ]);

        $permission = $request->permission;

        if ($user->hasPermissionTo($permission)) {
            $user->revokePermissionTo($permission);
            $status = 'revoked';
        } else {
            $user->givePermissionTo($permission);
            $status = 'granted';
        }

        return response()->json([
            'message' => "Permission '$permission' $status for {$user->name}",
            'current_permissions' => $user->getPermissionNames()
        ]);
    }
}