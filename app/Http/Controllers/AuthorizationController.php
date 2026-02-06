<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAuthorization;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\JsonResponse;

class AuthorizationController extends Controller 
{
    // Used by Sidebar to fetch current user rights
    public function getUserPermissions($userId): JsonResponse 
    {
        $permissions = UserAuthorization::where('user_id', $userId)
            ->pluck('operation_name');
            
        return response()->json($permissions);
    }

    // Used by Role Management Page to grant/revoke rights [cite: 28, 45]
    public function toggleAuthorization(Request $request, $userId): JsonResponse 
    {
        $request->validate(['operation' => 'required|string']);
        
        $auth = UserAuthorization::where('user_id', $userId)
            ->where('operation_name', $request->operation)
            ->first();

        if ($auth) {
            $auth->delete();
            return response()->json(['message' => 'Revoked', 'status' => 'detached']);
        }

        UserAuthorization::create([
            'user_id' => $userId,
            'operation_name' => $request->operation
        ]);

        return response()->json(['message' => 'Granted', 'status' => 'attached']);
    }
}