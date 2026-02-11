<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        // 1. Validate the incoming request
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240', 
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            
            // 2. Store the file in storage/app/public/boats
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('boats', $fileName, 'public');
            
            $fullPath = storage_path("app/public/" . $path);
            $publicUrl = asset("storage/" . $path);

            // 3. Determine Python path (Local Windows vs VPS Virtual Env)
            if (PHP_OS_FAMILY === 'Windows') {
                $pythonPath = 'python';
            } else {
                // Point to the venv we created on the VPS
                $pythonPath = base_path('venv/bin/python');
            }

            // 4. Trigger the Python script immediately
            $process = new Process([
                $pythonPath,
                app_path('Scripts/pinecone_sync.py'),
                env('GEMINI_API_KEY'),
                env('PINECONE_API_KEY'),
                env('PINECONE_INDEX'),
                $fullPath,
                $publicUrl
            ]);

            $process->setTimeout(90); // Slightly longer for uploads
            $process->run();

            // 5. Response handling
            if ($process->isSuccessful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Image uploaded and vector sync complete.',
                    'data' => [
                        'file_name' => $fileName,
                        'public_url' => $publicUrl,
                        'pinecone_status' => trim($process->getOutput())
                    ]
                ], 200);
            }

            return response()->json([
                'status' => 'partial_success',
                'message' => 'Image saved locally, but Pinecone sync failed.',
                'error_details' => $process->getErrorOutput(),
                'local_url' => $publicUrl
            ], 500);
        }

        return response()->json(['status' => 'error', 'message' => 'No image file detected.'], 400);
    }
}