<?php

namespace App\Http\Controllers;

use App\Models\Yacht;
use App\Models\YachtImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Models\YachtAvailabilityRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class YachtController extends Controller {

    public function index(): JsonResponse {
        return response()->json(Yacht::with(['images', 'availabilityRules'])->latest()->get());
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
            DB::beginTransaction();

            $isUpdate = $id !== null;
            $yacht = $isUpdate ? Yacht::findOrFail($id) : new Yacht();

            // Basic validation
            $rules = [
                'name' => $isUpdate ? 'sometimes|required|string' : 'required|string',
                'price' => $isUpdate ? 'sometimes|required|numeric' : 'required|numeric',
                'year' => 'nullable|string', // Changed to string since your DB has varchar
                'main_image' => $isUpdate ? 'nullable|image|max:5120' : 'sometimes|image|max:5120',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Fill basic fields - MATCHING YOUR DATABASE COLUMNS
            $basicFields = [
                'name', 'price', 'status', 'year', 'length', 'make', 'model',
                'beam', 'draft', 'engine_type', 'fuel_type', 'fuel_capacity',
                'water_capacity', 'location', 'vat_status', 'reference_code',
                'construction_material', 'dimensions', 'berths', 'hull_shape',
                'hull_color', 'deck_color', 'clearance', 'displacement', 'steering',
                'engine_brand', 'engine_model', 'engine_power', 'engine_hours',
                'max_speed', 'fuel_consumption', 'voltage', 'interior_type',
                'water_tank', 'water_system', 'safety_equipment',
                // Additional fields from your DB
                'loa', 'lwl', 'air_draft', 'designer', 'builder', 'brand_model',
                'external_url', 'print_url', 'last_serviced', 'hull_construction',
                'super_structure_colour', 'super_structure_construction',
                'stern_thruster', 'horse_power', 'fenders', 'hours_counter',
                'cruising_speed', 'max_draft', 'min_draft', 'fuel', 'ballast',
                'bow_thruster', 'engine_quantity'
            ];

            foreach ($basicFields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    $yacht->{$field} = ($value === '' || $value === 'undefined' || $value === null) ? null : $value;
                }
            }

            // Handle TEXT fields separately
            $textFields = [
                'cabins', 'heads', 'description', 'owners_comment', 'reg_details',
                'known_defects', 'passenger_capacity', 'navigation_electronics',
                'exterior_equipment', 'tankage', 'litres_per_hour', 'gearbox',
                'cylinders', 'propeller_type', 'engine_location', 'cooling_system',
                'navigation_lights', 'compass', 'depth_instrument', 'wind_instrument',
                'autopilot', 'gps', 'vhf', 'plotter', 'speed_instrument', 'radar',
                'toilet', 'shower', 'bath', 'life_raft', 'epirb', 'bilge_pump',
                'mob_system', 'genoa', 'spinnaker', 'tri_sail', 'storm_jib',
                'main_sail', 'winches', 'battery', 'battery_charger'
            ];

            foreach ($textFields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    $yacht->{$field} = ($value === '' || $value === 'undefined' || $value === null) ? null : $value;
                }
            }

            // Handle boolean fields - MATCHING YOUR DATABASE
            $nonNullableBooleans = [
                'trailer_included', 'generator', 'inverter', 'television',
                'dvd_player', 'cd_player', 'anchor', 'spray_hood', 'bimini',
                'central_heating', 'heating'
            ];

            $nullableBooleans = [
                'oven', 'microwave', 'fridge', 'freezer', 'air_conditioning',
                'flybridge'
            ];

            // Non-nullable booleans (default to 0)
            foreach ($nonNullableBooleans as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    $yacht->{$field} = ($value === '1' || $value === 'true' || $value === 1 || $value === true);
                } elseif (!$isUpdate) {
                    $yacht->{$field} = 0;
                }
            }

            // Nullable booleans
            foreach ($nullableBooleans as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    $yacht->{$field} = ($value === '1' || $value === 'true' || $value === 1 || $value === true);
                } elseif (!$isUpdate) {
                    $yacht->{$field} = null;
                }
            }

            // Handle main image
            if ($request->hasFile('main_image')) {
                if ($isUpdate && $yacht->main_image) {
                    Storage::disk('public')->delete($yacht->main_image);
                }
                $yacht->main_image = $request->file('main_image')->store('yachts/main', 'public');
            }

            // Set user_id for new yachts
            if (!$isUpdate) {
                $yacht->user_id = auth()->id();
                // Generate vessel ID
                $yacht->vessel_id = 'SK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }

            // Save the yacht
            $yacht->save();

            // Handle availability rules
            if ($request->filled('availability_rules')) {
                try {
                    $rules = json_decode($request->input('availability_rules'), true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && is_array($rules)) {
                        // Delete old rules
                        $yacht->availabilityRules()->delete();
                        
                        foreach ($rules as $rule) {
                            if (!empty($rule['day_of_week']) && !empty($rule['start_time']) && !empty($rule['end_time'])) {
                                $yacht->availabilityRules()->create([
                                    'day_of_week' => (int) $rule['day_of_week'],
                                    'start_time' => $rule['start_time'],
                                    'end_time' => $rule['end_time'],
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to save availability rules: ' . $e->getMessage());
                }
            }

            DB::commit();

            // Reload with relationships
            $yacht->load(['images', 'availabilityRules']);

            return response()->json($yacht, $isUpdate ? 200 : 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("Yacht Save Error: " . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['main_image', 'images']),
                'yacht_id' => $id ?? 'new'
            ]);

            return response()->json([
                'message' => 'Failed to save yacht',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function uploadGallery(Request $request, $id): JsonResponse {
        $request->validate([
            'images.*' => 'required|image|max:5120',
            'category' => 'required|string|in:Exterior,Interior,Engine Room,Bridge,General',
        ]);

        $yacht = Yacht::findOrFail($id);
        
        $files = $request->file('images') ?? $request->file('images[]');

        if (empty($files)) {
            return response()->json(['message' => 'No images detected'], 422);
        }

        $files = is_array($files) ? $files : [$files];
        $uploaded = [];

        foreach ($files as $image) {
            if ($image instanceof \Illuminate\Http\UploadedFile) {
                $folderName = $yacht->vessel_id ?? $yacht->id;
                $path = $image->store("yachts/gallery/{$folderName}", 'public');
                
                $uploaded[] = $yacht->images()->create([
                    'url'        => $path,
                    'category'   => $request->input('category', 'General'),
                    'part_name'  => $request->input('category', 'General'),
                ]);
            }
        }

        return response()->json(['status' => 'success', 'data' => $uploaded], 200);
    }

    public function deleteGalleryImage($id): JsonResponse {
        $image = YachtImage::findOrFail($id);
        Storage::disk('public')->delete($image->url);
        $image->delete();
        return response()->json(['message' => 'Image removed']);
    }

    public function show($id): JsonResponse {
        $yacht = Yacht::with(['images', 'availabilityRules'])->find($id);
        return $yacht ? response()->json($yacht) : response()->json(['message' => 'Vessel not found'], 404);
    }

    public function destroy($id): JsonResponse {
        $yacht = Yacht::findOrFail($id);
        if ($yacht->main_image) {
            Storage::disk('public')->delete($yacht->main_image);
        }
        
        // Delete gallery images from storage too
        foreach($yacht->images as $img) {
            Storage::disk('public')->delete($img->url);
        }
        
        $yacht->delete();
        return response()->json(['message' => 'Vessel removed from fleet.']);
    }

    public function classifyImages(Request $request): JsonResponse
    {
        $request->validate([
            'images.*' => 'required|image|max:5120',
        ]);

        $apiKey = "AIzaSyDwuu7UILyKXZNyB2KclKyGpEYiBNUNhc0";
        $model = "gemini-2.0-flash"; 
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $results = [];

        foreach ($request->file('images') as $image) {
            try {
                $imageData = base64_encode(file_get_contents($image->getRealPath()));
                
                $response = Http::timeout(15)->post($endpoint, [
                    'contents' => [['parts' => [
                        ['text' => "Return only one word: Exterior, Interior, Engine Room, Bridge, or General."],
                        ['inline_data' => ['mime_type' => $image->getMimeType(), 'data' => $imageData]]
                    ]]]
                ]);

                if ($response->successful()) {
                    $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'General';
                    $category = trim(preg_replace('/[^A-Za-z\s]/', '', $text));
                    // Validate category
                    $validCategories = ['Exterior', 'Interior', 'Engine Room', 'Bridge', 'General'];
                    if (!in_array($category, $validCategories)) {
                        $category = 'General';
                    }
                } else {
                    $category = 'General';
                }

                $results[] = [
                    'category' => $category,
                    'preview' => 'data:' . $image->getMimeType() . ';base64,' . $imageData,
                    'originalName' => $image->getClientOriginalName()
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'category' => 'General',
                    'preview' => '', 
                    'originalName' => $image->getClientOriginalName(),
                    'error' => true
                ];
            }
        }

        return response()->json($results);
    }
}