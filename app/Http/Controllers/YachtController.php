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
        
        // SECURITY: If updating, ensure the user owns this yacht (unless they are admin)
        if ($isUpdate) {
            $yacht = Yacht::findOrFail($id);
            // Optional: Add check here if($yacht->user_id !== auth()->id()) abort(403);
        } else {
            $yacht = new Yacht();
            // ✅ ASSIGN TO PARTNER
            $yacht->user_id = auth()->id(); 
        }

        // 1. Validation
        $request->validate([
            'name'  => $isUpdate ? 'sometimes|required' : 'required',
            'price' => $isUpdate ? 'sometimes|required|numeric' : 'required|numeric',
            // Allow nullable/integer for year
            'year'  => 'nullable|integer', 
        ]);

        // 2. Map Fields
        $fields = [
            'name', 'price', 'status', 'year', 'length', 'make', 'model', 
            'beam', 'draft', 'engine_type', 'fuel_type', 'fuel_capacity', 
            'water_capacity', 'cabins', 'heads', 'description', 'location',
            'vat_status', 'reference_code', 'construction_material', 'dimensions',
            'berths', 'hull_shape', 'hull_color', 'deck_color', 'clearance',
            'displacement', 'steering', 'engine_brand', 'engine_model',
            'engine_power', 'engine_hours', 'max_speed', 'fuel_consumption',
            'voltage', 'interior_type', 'water_tank', 'water_system',
            'navigation_electronics', 'exterior_equipment', 'safety_equipment'
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                // Convert "undefined" or empty strings to null
                $yacht->{$field} = ($value === "" || $value === "undefined") ? null : $value;
            }
        }

        // 3. Handle Booleans
        if ($request->has('trailer_included')) {
            $yacht->trailer_included = filter_var($request->input('trailer_included'), FILTER_VALIDATE_BOOLEAN);
        }
        
        // 4. Handle Main Image
        if ($request->hasFile('main_image')) {
            if ($isUpdate && $yacht->main_image) {
                Storage::disk('public')->delete($yacht->main_image);
            }
            $yacht->main_image = $request->file('main_image')->store('yachts/main', 'public');
        }

        // 5. Generate Identity (If new)
        if (!$isUpdate && empty($yacht->vessel_id)) {
            $yacht->vessel_id = 'SK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }

        $yacht->save();
        
        // Reload relationships for frontend
        $yacht->load('images');

        return response()->json($yacht, $isUpdate ? 200 : 201);

    } catch (\Exception $e) {
        \Log::error("Yacht Save Error: " . $e->getMessage()); // Add logging
        return response()->json(['message' => 'Registry Error', 'debug' => $e->getMessage()], 500);
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
public function classifyImages(Request $request): JsonResponse
{
    $request->validate([
        'images.*' => 'required|image|max:5120', // Limit to 5MB to avoid server crashes
    ]);

    // Gemini 2.0 Flash is the "Fast/Lite" version
    $apiKey = "AIzaSyDwuu7UILyKXZNyB2KclKyGpEYiBNUNhc0";
    $model = "gemini-2.0-flash"; 
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $results = [];

    foreach ($request->file('images') as $image) {
        try {
            // OPTIMIZATION: Read the file directly instead of storing it in a large variable
            $imageData = base64_encode(file_get_contents($image->getRealPath()));
            
            $response = Http::timeout(15)->post($endpoint, [
                'contents' => [['parts' => [
                    ['text' => "Return only one word: Exterior, Interior, Engine Room, or Bridge."],
                    ['inline_data' => ['mime_type' => $image->getMimeType(), 'data' => $imageData]]
                ]]]
            ]);

            if ($response->successful()) {
                $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Exterior';
                $category = trim(preg_replace('/[^A-Za-z]/', '', $text));
            } else {
                $category = 'Exterior'; // Fallback
            }

            $results[] = [
                'category' => $category,
                'preview' => 'data:' . $image->getMimeType() . ';base64,' . $imageData,
                'originalName' => $image->getClientOriginalName()
            ];

        } catch (\Exception $e) {
            $results[] = [
                'category' => 'Exterior',
                'preview' => '', 
                'originalName' => $image->getClientOriginalName(),
                'error' => true
            ];
        }
    }

    return response()->json($results);
}

}