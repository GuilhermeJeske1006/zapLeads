<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Old lead status => funnel status. */
    private const RENAMED = ['contatado' => 'abordado', 'interessado' => 'respondeu'];

    public function up(): void
    {
        Schema::table('prospecting_searches', function (Blueprint $table) {
            // keywords|searching|ranking|enriching|done, shown live while the search runs.
            $table->string('stage')->nullable()->after('status');
            // Counts per stage: keywords, empresas, enriquecer (+ the enrichment batch id).
            $table->json('progress')->nullable()->after('stage');
            // Label of a search centered somewhere else than the empresa's address; null = the address.
            $table->string('local_label', 150)->nullable()->after('radius_km');
            // Result filters chosen in step 1 (so_whatsapp, sem_site, nota_min), kept to run it again.
            $table->json('filtros')->nullable()->after('local_label');
        });

        DB::table('prospecting_searches')->where('status', 'done')->update(['stage' => 'done']);

        foreach (self::RENAMED as $old => $new) {
            DB::table('leads')->where('status', $old)->update(['status' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('leads')->where('status', $new)->update(['status' => $old]);
        }
        DB::table('leads')->whereIn('status', ['reuniao', 'proposta'])->update(['status' => 'interessado']);

        Schema::table('prospecting_searches', function (Blueprint $table) {
            $table->dropColumn(['stage', 'progress', 'local_label', 'filtros']);
        });
    }
};
