<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class QuickAuthController extends Controller
{
    // Every registration through this endpoint becomes a PARTNER
    public function registerPartner(Request $request)
    {
        try {
            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'password'     => Hash::make($request->password),
                'role'         => 'Partner',
                'status'       => 'Active',
                'access_level' => 'Limited',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'userType' => 'Partner',
                'id' => $user->id
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Direct Insert Failed: ' . $e->getMessage()], 500);
        }
    }

    // Every registration here is a standard USER
    public function registerUser(Request $request)
    {
        try {
            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'password'     => Hash::make($request->password),
                'role'         => 'Customer',
                'status'       => 'Active',
                'access_level' => 'None',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'userType' => 'Customer',
                'id' => $user->id
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Direct Insert Failed: ' . $e->getMessage()], 500);
        }
    }
}