<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class BoatSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $pythonCommand = (PHP_OS_FAMILY === 'Windows') ? 'python' : 'python3';

        $process = new Process([
            $pythonCommand,
            app_path('Scripts/pinecone_search.py'),
            env('GEMINI_API_KEY'),
            env('PINECONE_API_KEY'),
            env('PINECONE_INDEX'),
            $request->query('query')
        ]);

        $process->run();

        if ($process->isSuccessful()) {
            $results = json_decode($process->getOutput(), true);
            return response()->json([
                'status' => 'success',
                'results' => $results
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Search failed',
            'details' => $process->getErrorOutput()
        ], 500);
    }
}