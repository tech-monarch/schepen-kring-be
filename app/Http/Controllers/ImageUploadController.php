<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        // 1. Validate the incoming request
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        if (!$request->hasFile('image')) {
            return response()->json([
                'status' => 'error',
                'message' => 'No image file detected.'
            ], 400);
        }

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
            // Try venv path first, fall back to system python3
            $venvPath = base_path('venv/bin/python');
            $pythonPath = (file_exists($venvPath) && is_executable($venvPath)) ? $venvPath : 'python3';
            if ($pythonPath === 'python3') {
                Log::warning('Venv Python not found, using system python3 for image indexing');
            }
        }

        $scriptPath = app_path('Scripts/pinecone_sync.py');

        // 4. Log the start of Pinecone indexing
        Log::info('Starting Pinecone indexing', [
            'image_path' => $fullPath,
            'public_url' => $publicUrl,
            'script' => $scriptPath,
            'python' => $pythonPath
        ]);

        // 5. Trigger the Python script
        $process = new Process([
            $pythonPath,
            $scriptPath,
            env('GEMINI_API_KEY'),
            env('PINECONE_API_KEY'),
            env('PINECONE_INDEX'),
            $fullPath,
            $publicUrl
        ]);

        $process->setTimeout(90); // Slightly longer for uploads

        try {
            $process->mustRun(); // Throws if process fails
            $output = trim($process->getOutput());

            Log::info('Pinecone sync output', ['output' => $output]);

            // 6. Success response
            return response()->json([
                'status' => 'success',
                'message' => 'Image uploaded and vector sync complete.',
                'data' => [
                    'file_name' => $fileName,
                    'public_url' => $publicUrl,
                    'pinecone_status' => $output
                ]
            ], 200);

        } catch (ProcessFailedException $e) {
            // Process failed (non-zero exit code)
            $errorOutput = $process->getErrorOutput();
            Log::error('Pinecone sync process failed', [
                'error' => $e->getMessage(),
                'output' => $errorOutput
            ]);

            return response()->json([
                'status' => 'partial_success',
                'message' => 'Image saved locally, but Pinecone sync failed.',
                'error_details' => $errorOutput,
                'local_url' => $publicUrl
            ], 500);
        } catch (\Exception $e) {
            // Any other unexpected exception
            Log::error('Unexpected error during image upload', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'local_url' => $publicUrl
            ], 500);
        }
    }
}