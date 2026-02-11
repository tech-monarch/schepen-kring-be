<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class BackfillYachtBoatName extends Migration
{
    public function up()
    {
        // Copy name to boat_name where boat_name is null and name is not null
        DB::table('yachts')
            ->whereNull('boat_name')
            ->whereNotNull('name')
            ->update(['boat_name' => DB::raw('`name`')]);

        // If name column still exists and you want to keep it, fine.
        // If you plan to drop name later, do it in a separate migration.
    }

    public function down()
    {
        // No rollback – data copy is irreversible
    }
}