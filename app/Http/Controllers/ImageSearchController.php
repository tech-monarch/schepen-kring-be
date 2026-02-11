<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class ImageSearchController extends Controller
{
    public function searchByImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        // 1. Save the search image temporarily
        $path = $request->file('image')->store('temp_search', 'public');
        $fullPath = storage_path("app/public/" . $path);

        $python = (PHP_OS_FAMILY === 'Windows') ? 'python' : 'python3';

        // 2. Run the search script
        $process = new Process([
            $python,
            app_path('Scripts/image_to_image.py'),
            env('GEMINI_API_KEY'),
            env('PINECONE_API_KEY'),
            env('PINECONE_INDEX'),
            $fullPath
        ]);

        $process->run();

        // 3. Delete the temporary search image
        unlink($fullPath);

        if ($process->isSuccessful()) {
            return response()->json([
                'status' => 'success',
                'similar_boats' => json_decode($process->getOutput())
            ]);
        }

        return response()->json(['error' => $process->getErrorOutput()], 500);
    }
}