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

        $python = (PHP_OS_FAMILY === 'Windows') ? 'python' : 'python3';

        $process = new Process([
            $python,
            app_path('Scripts/boat_analysis.py'),
            env('GEMINI_API_KEY'),
            env('PINECONE_API_KEY'),
            env('PINECONE_INDEX'),
            $request->input('query')
        ]);

        $process->setTimeout(120); // Analyzing images takes time
        $process->run();

        if ($process->isSuccessful()) {
            return response()->json(json_decode($process->getOutput()));
        }

        return response()->json(['error' => $process->getErrorOutput()], 500);
    }
}