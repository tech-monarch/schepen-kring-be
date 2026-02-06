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
        
        if ($isUpdate) {
            $yacht = Yacht::findOrFail($id);
        } else {
            $yacht = new Yacht();
            $yacht->user_id = auth()->id(); 
        }

        // 1. Validation - Explicitly allow availability_rules [cite: 20]
        $request->validate([
            'name'               => $isUpdate ? 'sometimes|required' : 'required',
            'price'              => $isUpdate ? 'sometimes|required|numeric' : 'required|numeric',
            'year'               => 'nullable|integer', 
            'availability_rules' => 'nullable|string', 
        ]);

        // 2. Map Fields [cite: 21, 22]
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
                $yacht->{$field} = ($value === "" || $value === "undefined") ? null : $value; 
            }
        }

        // 3. Handle Booleans [cite: 26]
        if ($request->has('trailer_included')) {
            $yacht->trailer_included = filter_var($request->input('trailer_included'), FILTER_VALIDATE_BOOLEAN); 
        }
        
        // 4. Handle Main Image [cite: 27]
        if ($request->hasFile('main_image')) {
            if ($isUpdate && $yacht->main_image) {
                Storage::disk('public')->delete($yacht->main_image);
            }
            $yacht->main_image = $request->file('main_image')->store('yachts/main', 'public'); 
        }

        // 5. Save Availability Rules - Sync relationship [cite: 31]
        if ($request->has('availability_rules')) {
            $rules = json_decode($request->input('availability_rules'), true);
            
            if ($isUpdate) {
                // Ensure the relationship exists in the Yacht model
                $yacht->availabilityRules()->delete(); 
            }
            
            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    $yacht->availabilityRules()->create([
                        'day_of_week' => $rule['day_of_week'],
                        'start_time'  => $rule['start_time'],
                        'end_time'    => $rule['end_time'],
                    ]);
                }
            }
        }

        // 6. Generate Identity (If new) [cite: 28, 29]
        if (!$isUpdate && empty($yacht->vessel_id)) {
            $yacht->vessel_id = 'SK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }

        $yacht->save(); 
        
        // Reload relationships for frontend [cite: 30, 31]
        $yacht->load(['images', 'availabilityRules']);

        return response()->json($yacht, $isUpdate ? 200 : 201); 

    } catch (\Exception $e) {
        \Log::error("Yacht Save Error: " . $e->getMessage()); 
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