<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Leads found through Google Places were saved as "mapbox". Relabelled before the search starts
     * writing the real provider, otherwise the next search would not find them and duplicate them.
     * Mapbox ids always carry a type prefix ("poi.123"); Google place ids never contain a dot.
     */
    public function up(): void
    {
        DB::table('leads')
            ->where('external_source', 'mapbox')
            ->where('external_id', 'not like', '%.%')
            ->update(['external_source' => 'google_places']);
    }

    public function down(): void
    {
        DB::table('leads')
            ->where('external_source', 'google_places')
            ->update(['external_source' => 'mapbox']);
    }
};
