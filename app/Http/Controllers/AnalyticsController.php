<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VesselAnalytic;

class AnalyticsController extends Controller
{
    public function track(Request $request)
    {
        // 1. Log the data
        VesselAnalytic::create([
            'external_id' => $request->external_id,
            'name'        => $request->name,
            'model'       => $request->model,
            'price'       => $request->price,
            'ref_code'    => $request->ref_code,
            'url'         => $request->url,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'raw_specs'   => $request->specs,
        ]);

        // 2. Return response with CORS headers manually
        return response()->json(['status' => 'success'], 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');
    }
}   