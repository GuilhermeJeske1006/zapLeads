<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the empresa sells and to whom: the AI scores leads and writes outreach from it.
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('oferta_principal')->nullable();
            $table->text('problema_que_resolve')->nullable();
            $table->text('diferencial')->nullable();
            $table->json('provas_sociais')->nullable();
            $table->string('oferta_de_entrada')->nullable();
            $table->text('segmentos_excluidos')->nullable();
        });

        // Prospect leads still hold the fixed 50 from the old upsert: score them from what they have.
        Artisan::call('leads:score');
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'oferta_principal', 'problema_que_resolve', 'diferencial',
                'provas_sociais', 'oferta_de_entrada', 'segmentos_excluidos',
            ]);
        });
    }
};
