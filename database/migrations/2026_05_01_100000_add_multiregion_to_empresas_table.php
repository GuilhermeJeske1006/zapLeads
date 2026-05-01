<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('country', 2)->default('BR')->after('cidade');
            $table->string('timezone')->default('America/Sao_Paulo')->after('country');
            $table->string('currency', 3)->default('BRL')->after('timezone');
            $table->string('locale', 10)->default('pt_BR')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['country', 'timezone', 'currency', 'locale']);
        });
    }
};
