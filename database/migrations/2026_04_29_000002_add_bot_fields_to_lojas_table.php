<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lojas', function (Blueprint $table) {
            $table->boolean('bot_ativo')->default(false)->after('whatsapp');
            $table->time('bot_horario_inicio')->nullable()->after('bot_ativo');
            $table->time('bot_horario_fim')->nullable()->after('bot_horario_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('lojas', function (Blueprint $table) {
            $table->dropColumn(['bot_ativo', 'bot_horario_inicio', 'bot_horario_fim']);
        });
    }
};
