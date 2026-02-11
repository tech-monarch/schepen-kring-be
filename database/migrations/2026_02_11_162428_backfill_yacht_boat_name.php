<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copy `name` into `boat_name` where boat_name is null
        DB::table('yachts')
            ->whereNull('boat_name')
            ->whereNotNull('name')
            ->update([
                'boat_name' => DB::raw('`name`')
            ]);
    }

    public function down(): void
    {
        // No rollback for data backfill
    }
};
