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

            $yacht = $isUpdate
                ? Yacht::findOrFail($id)
                : new Yacht(['user_id' => auth()->id()]);

            /*
            |--------------------------------------------------------------------------
            | 1. Validation
            |--------------------------------------------------------------------------
            */
            $validator = Validator::make($request->all(), [
                'name'               => $isUpdate ? 'sometimes|required|string' : 'required|string',
                'price'              => $isUpdate ? 'sometimes|required|numeric' : 'required|numeric',
                'year'               => 'nullable|integer',
                'make'               => 'nullable|string',
                'model'              => 'nullable|string',
                'length'             => 'nullable|numeric',
                'beam'               => 'nullable|numeric',
                'draft'              => 'nullable|numeric',
                'engine_type'        => 'nullable|string',
                'fuel_type'          => 'nullable|string',
                'fuel_capacity'      => 'nullable|string',
                'water_capacity'     => 'nullable|string',
                'cabins'             => 'nullable|integer',
                'heads'              => 'nullable|integer',
                'berths'             => 'nullable|string',
                'description'        => 'nullable|string',
                'location'           => 'nullable|string',
                'vat_status'         => 'nullable|string',
                'reference_code'     => 'nullable|string',
                'construction_material' => 'nullable|string',
                'dimensions'         => 'nullable|string',
                'hull_shape'         => 'nullable|string',
                'hull_color'         => 'nullable|string',
                'deck_color'         => 'nullable|string',
                'clearance'          => 'nullable|string',
                'displacement'       => 'nullable|string',
                'steering'           => 'nullable|string',
                'engine_brand'       => 'nullable|string',
                'engine_model'       => 'nullable|string',
                'engine_power'       => 'nullable|string',
                'engine_hours'       => 'nullable|string',
                'max_speed'          => 'nullable|string',
                'fuel_consumption'   => 'nullable|string',
                'voltage'            => 'nullable|string',
                'interior_type'      => 'nullable|string',
                'water_tank'         => 'nullable|string',
                'water_system'       => 'nullable|string',
                'navigation_electronics' => 'nullable|string',
                'exterior_equipment' => 'nullable|string',
                'safety_equipment'   => 'nullable|string',
                'availability_rules' => 'nullable|string',
                'main_image'         => $isUpdate ? 'nullable|image|max:5120' : 'sometimes|image|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Safe Field Mapping (null-proof)
            |--------------------------------------------------------------------------
            */
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
                    $yacht->{$field} = ($value === '' || $value === 'undefined' || $value === null) ? null : $value;
                } elseif (!$isUpdate) {
                    // Set default values for new yacht
                    $yacht->{$field} = null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Boolean Handling (null-safe, default 0)
            |--------------------------------------------------------------------------
            */
            $booleanFields = [
                'trailer_included',
                'oven', 'microwave', 'fridge', 'freezer', 'air_conditioning',
                'generator', 'inverter', 'television', 'dvd_player', 'cd_player',
                'anchor', 'bimini', 'spray_hood', 'heating', 'central_heating'
            ];

            foreach ($booleanFields as $bool) {
                if ($request->has($bool)) {
                    $yacht->{$bool} = filter_var($request->input($bool), FILTER_VALIDATE_BOOLEAN);
                } elseif (!$isUpdate) {
                    $yacht->{$bool} = 0;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Handle Main Image
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('main_image')) {
                if ($isUpdate && $yacht->main_image) {
                    Storage::disk('public')->delete($yacht->main_image);
                }

                $yacht->main_image = $request->file('main_image')
                    ->store('yachts/main', 'public');
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Generate Vessel ID (new only)
            |--------------------------------------------------------------------------
            */
            if (!$isUpdate && empty($yacht->vessel_id)) {
                $yacht->vessel_id = 'SK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }

            /*
            |--------------------------------------------------------------------------
            | 6. SAVE YACHT FIRST
            |--------------------------------------------------------------------------
            */
            $yacht->save();

            /*
            |--------------------------------------------------------------------------
            | 7. Sync Availability Rules (safe)
            |--------------------------------------------------------------------------
            */
            if ($request->filled('availability_rules')) {
                $rules = json_decode($request->input('availability_rules'), true);

                if (is_array($rules)) {
                    // delete old
                    $yacht->availabilityRules()->delete();

                    foreach ($rules as $rule) {
                        if (isset($rule['day_of_week'], $rule['start_time'], $rule['end_time'])) {
                            $yacht->availabilityRules()->create([
                                'day_of_week' => $rule['day_of_week'],
                                'start_time'  => $rule['start_time'],
                                'end_time'    => $rule['end_time'],
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | 8. Reload Relationships
            |--------------------------------------------------------------------------
            */
            $yacht->load(['images', 'availabilityRules']);

            return response()->json($yacht, $isUpdate ? 200 : 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("Yacht Save Error: " . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'message' => 'Registry Error',
                'debug'   => $e->getMessage(),
                'line'    => $e->getLine()
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