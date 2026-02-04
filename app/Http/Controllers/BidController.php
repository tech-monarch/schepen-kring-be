<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

class BidController extends Controller
{
public function placeBid(Request $request)
{
    $request->validate([
        'yacht_id' => 'required|exists:yachts,id',
        'amount' => 'required|numeric|min:1',
    ]);

    $yacht = Yacht::findOrFail($request->yacht_id);

    // 1. Check if the vessel is already sold
    if ($yacht->status === 'Sold') {
        return response()->json(['message' => 'Bidding is closed. Vessel is sold.'], 403);
    }

    // 2. Ensure the yacht is actually set to "For Bid"
    // Note: If you want 'For Sale' yachts to also accept bids, remove this check.
    if ($yacht->status !== 'For Bid') {
        return response()->json(['message' => 'This vessel is not currently open for bidding.'], 403);
    }

    // 3. Ensure the new bid is higher than the current highest bid
    if ($yacht->current_bid !== null && $request->amount <= $yacht->current_bid) {
        return response()->json(['message' => 'Bid must be higher than the current bid €' . number_format($yacht->current_bid)], 422);
    }

    return DB::transaction(function () use ($request, $yacht) {
        // 4. Mark previous active bids as 'outbid'
        Bid::where('yacht_id', $yacht->id)
            ->where('status', 'active')
            ->update(['status' => 'outbid']);

        // 5. Create the new bid using the authenticated user
        $bid = Bid::create([
            'yacht_id' => $yacht->id,
            'user_id' => auth()->id(), // Strictly use authenticated ID
            'amount' => $request->amount,
            'status' => 'active'
        ]);

        // 6. Update the main Yacht record
        $yacht->update(['current_bid' => $request->amount]);

        return response()->json([
            'message' => 'Bid placed successfully.',
            'bid' => $bid->load('user') // Load user so frontend updates immediately
        ], 201);
    });
}

    public function history($yachtId)
    {
        $history = Bid::with('user:id,name')
            ->where('yacht_id', $yachtId)
            ->orderBy('amount', 'desc')
            ->get();

        return response()->json($history);
    }

    /**
     * Accept a bid - Marks the yacht as Sold and closes all other bids.
     */
    public function acceptBid(Request $request, $id)
    {
        $bid = Bid::findOrFail($id);
        $yacht = $bid->yacht;

        return DB::transaction(function () use ($bid, $yacht) {
            // 1. Mark this bid as 'won'
            $bid->update([
                'status' => 'won',
                'finalized_at' => Carbon::now(),
                'finalized_by' => auth()->id() 
            ]);

            // 2. Mark all other active/outbid bids as 'cancelled'
            Bid::where('yacht_id', $yacht->id)
                ->where('id', '!=', $bid->id)
                ->update(['status' => 'cancelled']);

            // 3. Update Yacht status to Sold
            $yacht->update(['status' => 'Sold']);

            return response()->json(['message' => 'Bid accepted. Vessel marked as Sold.']);
        });
    }

    /**
     * Decline a bid - Useful for clearing out low-ball offers.
     */
    public function declineBid($id)
    {
        $bid = Bid::findOrFail($id);
        
        $bid->update([
            'status' => 'cancelled',
            'finalized_at' => Carbon::now(),
            'finalized_by' => auth()->id()
        ]);

        return response()->json(['message' => 'Bid declined.']);
    }
}