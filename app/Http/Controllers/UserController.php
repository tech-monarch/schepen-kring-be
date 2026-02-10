<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Traits\LogsActivity;

use App\Models\ActivityLog;

class UserController extends Controller
{
    
    use LogsActivity;
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
            'role' => 'required|in:Admin,Employee,Customer,Partner', // Added Partner [cite: 6]
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
            'role' => 'sometimes|in:Admin,Employee,Customer,Partner', // Added Partner [cite: 13]
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
    // Security: Prevent self-deletion
    if (Auth::id() === $user->id) {
        return response()->json(['message' => 'Cannot terminate your own session.'], 403);
    }

    $user->delete();
    return response()->json(['message' => 'User deleted successfully']);
}

    /**
     * Toggle status quickly (Active/Suspended).
     */
    public function toggleStatus(User $user)
    {
        $user->status = ($user->status === 'Active') ?
        'Suspended' : 'Active';
        $user->save();

        return response()->json($user);
    }

    public function togglePermission(Request $request, User $user)
    {
        $request->validate([
            'permission' => 'required|string|exists:permissions,name'
        ]);
        $permission = $request->permission;

        if ($user->hasPermissionTo($permission)) {
            $user->revokePermissionTo($permission);
            $status = 'detached';
        } else {
            $user->givePermissionTo($permission);
            $status = 'attached';
        }

        $user->refresh();
        return response()->json([
            'status' => $status,
            'current_permissions' => $user->getPermissionNames(), 
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
        // Log failed login attempt
        ActivityLog::log(
            'auth',
            'login_failed',
            "Failed login attempt for email: {$request->email}",
            null,
            ['email' => $request->email, 'ip' => $request->ip()]
        );
        
        return response()->json([
            'message' => 'Identity could not be verified. Check credentials.'
        ], 401);
    }

    $user = Auth::user();
    
    // Log successful login
    $this->logActivity(
        'login',
        "User {$user->name} logged in successfully",
        ['user_id' => $user->id, 'email' => $user->email],
        true // Notify admins
    );

    $token = $user->createToken('terminal_access_token')->plainTextToken;
    
    return response()->json([
        'token' => $token,
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'userType' => $user->role, 
        'status' => $user->status,
        'access_level' => $user->access_level,
        'permissions' => $user->getPermissionNames(),
    ]);
}

    public function getAllPermissions() {
        return response()->json(\Spatie\Permission\Models\Permission::all());
    }

    public function getAllRoles() {
        return response()->json(\Spatie\Permission\Models\Role::all());
    }

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
            'role' => 'Customer',      
            'status' => 'Active',
            'access_level' => 'None',
            'registration_ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'terms_accepted_at' => now(),
        ]);

        $user->assignRole('Customer');
        $token = $user->createToken('terminal_access_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'userType' => $user->role, 
        ], 201);
    }

    /**
     * Register a new Partner identity.
     */
public function registerPartner(Request $request) 
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8',
        'accept_terms' => 'accepted' 
    ]);

    // SAFETY CHECK: Create the role if it doesn't exist in the DB
    if (!\Spatie\Permission\Models\Role::where('name', 'Partner')->exists()) {
        \Spatie\Permission\Models\Role::create(['name' => 'Partner', 'guard_name' => 'web']);
    }

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'role' => 'Partner', 
        'status' => 'Active',
        'access_level' => 'Limited',
        'registration_ip' => $request->ip(),
        'user_agent' => $request->header('User-Agent'),
        'terms_accepted_at' => now(),
    ]);

    $user->assignRole('Partner');
    $token = $user->createToken('terminal_access_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'userType' => $user->role, 
    ], 201);
}



// app/Http/Controllers/UserController.php

/**
 * Impersonate a specific user.
 */
public function impersonate(User $user)
{
    // Security: Only Admins can impersonate
    if (Auth::user()->role !== 'Admin') {
        return response()->json(['message' => 'Insufficient clearance for identity assumption.'], 403);
    }

    // Log impersonation
    $this->logActivity(
        'user_impersonated',
        "Admin {auth()->user()->name} impersonated user {$user->name}",
        ['admin_id' => Auth::id(), 'target_user_id' => $user->id],
        true
    );

    // Create a new token for the target user
    $token = $user->createToken('impersonation_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'message' => "Logged in as {$user->name}",
        'user' => [
            'id' => $user->id,
            'role' => $user->role,
            'name' => $user->name,
            'email' => $user->email,
        ]
    ]);
}

/**
 * Get staff users for task assignment
 */
public function getStaff()
{
    try {
        $staff = User::whereIn('role', ['Admin', 'Employee', 'Partner'])
            ->where('status', 'Active')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'role']);
        
        return response()->json($staff);
    } catch (\Exception $e) {
        \Log::error('Error fetching staff: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch staff'], 500);
    }
}

}