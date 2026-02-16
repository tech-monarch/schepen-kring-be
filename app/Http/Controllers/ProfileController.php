<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show()
    {
        return response()->json(Auth::user());
    }

public function update(Request $request)
{
    $user = Auth::user();

    $request->validate([
        // existing rules
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $user->id,
        'phone_number' => 'nullable|string|max:20',
        'address' => 'nullable|string|max:500',
        'city' => 'nullable|string|max:100',
        'state' => 'nullable|string|max:100',
        'postcode' => 'nullable|string|max:20',
        'country' => 'nullable|string|max:100',
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',

        // new rules (all optional)
        'relationNumber' => 'nullable|string|max:50',
        'firstName'     => 'nullable|string|max:100',
        'lastName'      => 'nullable|string|max:100',
        'prefix'        => 'nullable|string|max:20',
        'initials'      => 'nullable|string|max:20',
        'title'         => 'nullable|string|max:50',
        'salutation'    => 'nullable|string|max:50',
        'attentionOf'   => 'nullable|string|max:255',
        'identification'=> 'nullable|string|max:100',
        'dateOfBirth'   => 'nullable|date',
        'website'       => 'nullable|url|max:255',
        'mobile'        => 'nullable|string|max:20',
        'street'        => 'nullable|string|max:255',
        'houseNumber'   => 'nullable|string|max:20',
        'note'          => 'nullable|string',
        'claimHistoryCount' => 'nullable|integer|min:0',
    ]);

    // Handle profile image upload (unchanged)
    if ($request->hasFile('profile_image')) {
        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }
        $path = $request->file('profile_image')->store('profiles', 'public');
        $user->profile_image = $path;
    }

    // Update all fields – existing and new
    $user->fill($request->only([
        'name', 'email', 'phone_number', 'address', 'city', 'state',
        'postcode', 'country', 'relationNumber', 'firstName', 'lastName',
        'prefix', 'initials', 'title', 'salutation', 'attentionOf',
        'identification', 'dateOfBirth', 'website', 'mobile', 'street',
        'houseNumber', 'note', 'claimHistoryCount',
    ]));

    $user->save();

    return response()->json([
        'message' => 'Profile updated successfully',
        'user' => $user->fresh()
    ]);
}

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
}