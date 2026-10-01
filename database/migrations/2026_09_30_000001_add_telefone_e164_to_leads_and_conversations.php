<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('telefone_e164', 32)->nullable()->after('telefone')->index();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('telefone_e164', 32)->nullable()->after('telefone');
        });

        // Fills the new columns and merges conversations split by phone format, so the
        // unique index below can be created.
        Artisan::call('leads:normalize-phones');

        Schema::table('conversations', function (Blueprint $table) {
            $table->unique(['empresa_id', 'telefone_e164']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'telefone_e164']);
            $table->dropColumn('telefone_e164');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['telefone_e164']);
            $table->dropColumn('telefone_e164');
        });
    }
};
