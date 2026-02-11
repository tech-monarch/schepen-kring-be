<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class BoatAnalysisController extends Controller
{
    public function identify(Request $request)
    {
        $request->validate(['query' => 'required|string']);

        // Use venv on VPS, global python on Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $pythonPath = 'python';
        } else {
            $pythonPath = base_path('venv/bin/python');
        }

        $process = new Process([
            $pythonPath,
            app_path('Scripts/boat_analysis.py'),
            env('GEMINI_API_KEY'),
            env('PINECONE_API_KEY'),
            env('PINECONE_INDEX'),
            $request->input('query')
        ]);

        // Analyzing multiple images and generating a response takes longer
        $process->setTimeout(180); 
        $process->run();

        if ($process->isSuccessful()) {
            return response()->json(json_decode($process->getOutput()));
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Analysis failed',
            'error' => $process->getErrorOutput()
        ], 500);
    }
}