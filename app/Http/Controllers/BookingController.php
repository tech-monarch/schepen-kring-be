<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function getAvailableSlots(Request $request, $id) 
{
    $date = \Carbon\Carbon::parse($request->query('date'));
    $yacht = \App\Models\Yacht::findOrFail($id);
    
    // 1. Get availability for this day of week
    $rule = \DB::table('yacht_availability_rules')
        ->where('yacht_id', $id)
        ->where('day_of_week', $date->dayOfWeek)
        ->first();

    if (!$rule) return response()->json([]);

    $startTime = \Carbon\Carbon::parse($date->toDateString() . ' ' . $rule->start_time);
    $endTime = \Carbon\Carbon::parse($date->toDateString() . ' ' . $rule->end_time);

    // 2. Fetch existing bookings to check for overlaps
    $bookings = \DB::table('bookings')
        ->where('yacht_id', $id)
        ->whereDate('start_at', $date->toDateString())
        ->get();

    $slots = [];
    $current = $startTime->copy();

    // 3. Loop in 15-min increments
    while ($current->copy()->addMinutes(75) <= $endTime) { // 60m viewing + 15m buffer
        $slotStart = $current->copy();
        $slotEnd = $current->copy()->addMinutes(75);

        $isOverlap = $bookings->contains(function ($b) use ($slotStart, $slotEnd) {
            return ($slotStart < $b->end_at && $slotEnd > $b->start_at);
        });

        if (!$isOverlap) {
            $slots[] = $current->format('H:i');
        }
        $current->addMinutes(15);
    }

    return response()->json($slots);
}



public function storeBooking(Request $request, $id)
{
    $request->validate([
        'start_at' => 'required|date|after:now',
    ]);

    $start = \Carbon\Carbon::parse($request->start_at);
    
    // Add 60 mins (viewing) + 15 mins (buffer) as per requirements
    $end = $start->copy()->addMinutes(75); 

    // Final check for overlap before saving
    $overlap = \DB::table('bookings')
        ->where('yacht_id', $id)
        ->where(function ($query) use ($start, $end) {
            $query->whereBetween('start_at', [$start, $end])
                  ->orWhereBetween('end_at', [$start, $end]);
        })->exists();

    if ($overlap) {
        return response()->json(['error' => 'Slot no longer available'], 422);
    }

    $booking = \App\Models\Booking::create([
        'yacht_id' => $id,
        'start_at' => $start,
        'end_at' => $end,
        'status' => 'confirmed'
    ]);

    return response()->json($booking, 201);
}
}
