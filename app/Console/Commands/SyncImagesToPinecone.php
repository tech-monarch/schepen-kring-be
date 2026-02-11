<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class SyncImagesToPinecone extends Command
{
    protected $signature = 'pinecone:sync';
    protected $description = 'Uploads boat images to Pinecone via Gemini';

    public function handle()
    {
        // 1. Get all images from storage/app/public/boats
        $files = Storage::disk('public')->files('boats');
        $this->info("Found " . count($files) . " images. Starting...");

        foreach ($files as $file) {
            $fullPath = storage_path("app/public/" . $file);
            $publicUrl = asset("storage/" . $file);

            // 2. Call Python
            $process = new Process([
                'python', // Use 'python3' if on Linux/VPS
                app_path('Scripts/pinecone_sync.py'),
                env('GEMINI_API_KEY'),
                env('PINECONE_API_KEY'),
                env('PINECONE_INDEX', 'boat-index'),
                $fullPath,
                $publicUrl
            ]);

            $process->run();

            if ($process->isSuccessful()) {
                $this->info($process->getOutput());
            } else {
                $this->error("Failed $file: " . $process->getErrorOutput());
            }
        }
    }
}