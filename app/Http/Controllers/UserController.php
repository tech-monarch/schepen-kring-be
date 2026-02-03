<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display the Global Directory of users.
     */
public function index()
{
    // Eager load permissions to avoid the 500/Slow loading errors [cite: 11]
    $users = User::with('permissions')->orderBy('name', 'asc')->get();

    // Use a collection map to ensure PHP treats each item as a User model
    $users->transform(function ($user) {
        /** @var \App\Models\User $user */
        // We set 'permissions' to match the frontend 'permissions?: string[]' interface [cite: 6]
        $user->permissions = $user->getPermissionNames(); 
        return $user;
    });

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

public function togglePermission(Request $request, User $user)
{
    $request->validate([
        'permission' => 'required|string|exists:permissions,name'
    ]);

    $permission = $request->permission;

    // Use Spatie's built-in toggle logic
    if ($user->hasPermissionTo($permission)) {
        $user->revokePermissionTo($permission);
        $status = 'detached';
    } else {
        $user->givePermissionTo($permission);
        $status = 'attached';
    }

    // Refresh to get the truth from the database
    $user->refresh();

    return response()->json([
        'status' => $status,
        'current_permissions' => $user->getPermissionNames(), // This is what the frontend UI needs
        'message' => "Permission " . ($status === 'attached' ? 'granted' : 'revoked')
    ]);
}

    public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json([
            'message' => 'Identity could not be verified. Check credentials.'
        ], 401);
    }

    $user = Auth::user();
    
    // Create the Sanctum token
    $token = $user->createToken('terminal_access_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'userType' => $user->role, // Admin, Employee, or Customer
        'status' => $user->status,
        'access_level' => $user->access_level,
        'permissions' => $user->getPermissionNames(), // Returns ['view yachts', 'manage tasks', etc.]
    ]);
}

    // Add this to UserController.php
public function getAllPermissions() {
    // Returns all permissions registered in the system
    return response()->json(\Spatie\Permission\Models\Permission::all());
}

public function getAllRoles() {
    // Returns all roles (Admin, Employee, etc.)
    return response()->json(\Spatie\Permission\Models\Role::all());
}


// app/Http/Controllers/UserController.php

public function register(Request $request) 
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8',
        'accept_terms' => 'accepted' 
    ]);

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'role' => 'Customer',      // Auto-assigning as requested [cite: 167]
        'status' => 'Active',
        'access_level' => 'None',
        'registration_ip' => $request->ip(),
        'user_agent' => $request->header('User-Agent'),
        'terms_accepted_at' => now(),
    ]);

    // Ensure they have the Spatie role assigned if you are using that package [cite: 80]
    $user->assignRole('Customer');

    $token = $user->createToken('terminal_access_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'userType' => $user->role, // Returns "Customer" instead of null 
    ], 201);
}
}