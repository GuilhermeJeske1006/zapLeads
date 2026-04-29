<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('prospecting_search_id')
                ->nullable()
                ->after('loja_id')
                ->constrained('prospecting_searches')
                ->nullOnDelete();

            $table->string('source', 32)->default('internal')->after('telefone');
            $table->string('external_source', 32)->nullable()->after('source');
            $table->string('external_id', 128)->nullable()->after('external_source');
            $table->string('endereco')->nullable()->after('cidade');
            $table->string('website')->nullable()->after('endereco');

            $table->unique(['loja_id', 'external_source', 'external_id'], 'leads_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('leads_external_unique');
            $table->dropConstrainedForeignId('prospecting_search_id');
            $table->dropColumn(['source', 'external_source', 'external_id', 'endereco', 'website']);
        });
    }
};

