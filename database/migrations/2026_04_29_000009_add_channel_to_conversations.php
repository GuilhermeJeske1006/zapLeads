<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('whatsapp_channel_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('whatsapp_channels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_channel_id']);
            $table->dropColumn('whatsapp_channel_id');
        });
    }
};
