<?php

namespace App\Http\Controllers;

use App\Models\Yacht;
use App\Models\YachtImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class YachtController extends Controller {
    public function index() {
        return response()->json(Yacht::with('images')->get());
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'status' => 'required|string',
            'year' => 'required|string',
            'length' => 'required|string',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $validated['vessel_id'] = 'Y-' . strtoupper(uniqid());

        if ($request->hasFile('main_image')) {
            $path = $request->file('main_image')->store('yachts/main', 'public');
            $validated['main_image'] = $path;
        }

        $yacht = Yacht::create($validated);
        return response()->json($yacht, 201);
    }

    // New method specifically for the 1000+ images bulk upload
    public function uploadGallery(Request $request, $id) {
        $request->validate([
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'category' => 'nullable|string',
            'part_name' => 'nullable|string'
        ]);

        $yacht = Yacht::findOrFail($id);
        $uploadedImages = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store("yachts/gallery/{$yacht->vessel_id}", 'public');
                $uploadedImages[] = $yacht->images()->create([
                    'url' => $path,
                    'category' => $request->category,
                    'part_name' => $request->part_name
                ]);
            }
        }

        return response()->json($uploadedImages, 200);
    }
}