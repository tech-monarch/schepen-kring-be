<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VesselAnalytic;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get summarized stats for the Dashboard Table
     */
    public function summary()
    {
        try {
            // Grouping by ID to count views per vessel
            $stats = VesselAnalytic::select(
                'external_id',
                DB::raw('MAX(name) as name'),
                DB::raw('MAX(url) as url'),
                DB::raw('MAX(ip_address) as ip_address'),
                DB::raw('count(*) as total_views')
            )
            ->groupBy('external_id')
            ->orderBy('total_views', 'desc')
            ->limit(10)
            ->get();

            return response()->json($stats, 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
                
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Receive tracking data from the external widget
     */
    public function track(Request $request)
    {
        VesselAnalytic::create([
            'external_id' => $request->external_id,
            'name'        => $request->name,
            'model'       => $request->model,
            'price'       => $request->price,
            'ref_code'    => $request->ref_code,
            'url'         => $request->url,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'raw_specs'   => $request->specs, // Ensure this is cast to array in Model
        ]);

        return response()->json(['status' => 'success'], 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');
    }
}