<?php

namespace App\Http\Controllers;

use App\Models\Yacht;
use App\Models\YachtImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class YachtController extends Controller {

    public function index(): JsonResponse {
        // Use 'images' to match your relationship, but we will ensure 
        // the frontend can read it.
        return response()->json(Yacht::with('images')->latest()->get());
    }

    public function store(Request $request): JsonResponse {
        return $this->saveYacht($request);
    }

    public function update(Request $request, $id): JsonResponse {
        return $this->saveYacht($request, $id);
    }

    protected function saveYacht(Request $request, $id = null): JsonResponse 
    {
        try {
            $isUpdate = $id !== null;
            $yacht = $isUpdate ? Yacht::findOrFail($id) : new Yacht();

            // 1. Validation
            $request->validate([
                'name'  => $isUpdate ? 'sometimes|required' : 'required',
                'price' => $isUpdate ? 'sometimes|required|numeric' : 'required|numeric',
            ]);

            // 2. Map Core Fields
            $fields = [
                'name', 'price', 'status', 'year', 'length', 'make', 'model', 
                'beam', 'draft', 'engine_type', 'fuel_type', 'fuel_capacity', 
                'water_capacity', 'cabins', 'heads', 'description', 'location'
            ];

            foreach ($fields as $field) {
                if ($request->has($field)) {
                    if (in_array($field, ['cabins', 'heads', 'price']) && $request->input($field) === "") {
                        $yacht->{$field} = null;
                    } else {
                        $yacht->{$field} = $request->input($field);
                    }
                }
            }

            // 3. Handle Boolean Bidding
            if ($request->has('allow_bidding')) {
                $yacht->allow_bidding = filter_var($request->allow_bidding, FILTER_VALIDATE_BOOLEAN);
            }

            // 4. Handle Main Image
            if ($request->hasFile('main_image')) {
                $request->validate(['main_image' => 'image|max:10240']);
                if ($isUpdate && $yacht->main_image) {
                    Storage::disk('public')->delete($yacht->main_image);
                }
                $yacht->main_image = $request->file('main_image')->store('yachts/main', 'public');
            }

            // 5. Identity Generation
            if (!$isUpdate && !isset($yacht->vessel_id)) {
                $yacht->vessel_id = 'SK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }

            $yacht->save();

            // Load images before returning so the UI updates immediately
            $yacht->load('images');

            return response()->json($yacht, $isUpdate ? 200 : 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Registry Error',
                'debug'   => $e->getMessage()
            ], 500);
        }
    }

    public function uploadGallery(Request $request, $id): JsonResponse {
        $yacht = Yacht::findOrFail($id);
        
        $files = $request->file('images') ?? $request->file('images[]');

        if (empty($files)) {
            return response()->json(['message' => 'No images detected'], 422);
        }

        // Ensure we are working with an array
        $files = is_array($files) ? $files : [$files];
        $uploaded = [];

        foreach ($files as $image) {
            if ($image instanceof \Illuminate\Http\UploadedFile) {
                $folderName = $yacht->vessel_id ?? $yacht->id;
                $path = $image->store("yachts/gallery/{$folderName}", 'public');
                
                // CRITICAL: Ensure field names match what the Frontend expects (image_path)
                $uploaded[] = $yacht->images()->create([
                    'url'       => $path, // ✅ FIXED
                    'category'   => $request->input('category', 'General'),
                    'part_name'  => $request->input('category', 'General'),
                ]);
            }
        }

        return response()->json(['status' => 'success', 'data' => $uploaded], 200);
    }

    public function deleteGalleryImage($id): JsonResponse {
        $image = YachtImage::findOrFail($id);
        Storage::disk('public')->delete($image->url); // ✅ FIXED
        $image->delete();
        return response()->json(['message' => 'Image removed']);
    }

    public function show($id): JsonResponse {
        $yacht = Yacht::with('images')->find($id);
        return $yacht ? response()->json($yacht) : response()->json(['message' => 'Vessel not found'], 404);
    }

    public function destroy($id): JsonResponse {
        $yacht = Yacht::findOrFail($id);
        if ($yacht->main_image) Storage::disk('public')->delete($yacht->main_image);
        
        // Delete gallery images from storage too
        foreach($yacht->images as $img) {
            Storage::disk('public')->delete($img->image_path);
        }
        
        $yacht->delete();
        return response()->json(['message' => 'Vessel removed from fleet.']);
    }


}