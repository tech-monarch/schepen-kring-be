<?php

namespace App\Http\Controllers;

use App\Models\BoatInspection;
use App\Models\InspectionAnswer;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    // Get inspection for a boat (or create if not exists)
    public function showForBoat($boatId)
    {
        $inspection = BoatInspection::firstOrCreate(
            ['boat_id' => $boatId],
            ['user_id' => auth()->id()]
        );
        return response()->json($inspection->load('answers.question'));
    }

    // Update an answer (human override, accept, verify)
    public function updateAnswer(Request $request, $inspectionId, $answerId)
    {
        $answer = InspectionAnswer::where('inspection_id', $inspectionId)->findOrFail($answerId);
        $answer->update($request->only(['human_answer', 'review_status']));
        return response()->json($answer);
    }
}