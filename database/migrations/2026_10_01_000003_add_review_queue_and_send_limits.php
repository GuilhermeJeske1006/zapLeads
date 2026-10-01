<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_drafts', function (Blueprint $table) {
            $table->json('variantes')->nullable();
            $table->string('variante_escolhida', 40)->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('erro', 40)->nullable();

            $table->index(['whatsapp_channel_id', 'scheduled_for']);
        });

        Schema::create('outreach_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outreach_draft_id')->nullable()->constrained()->nullOnDelete();
            $table->string('canal', 20); // api | assisted
            $table->text('mensagem');
            $table->string('variante', 40)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'lead_id']);
        });

        Schema::table('whatsapp_channels', function (Blueprint $table) {
            $table->unsignedSmallInteger('limite_diario_prospeccao')->default(30);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('prospeccao_envio_automatico')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('prospeccao_envio_automatico');
        });

        Schema::table('whatsapp_channels', function (Blueprint $table) {
            $table->dropColumn('limite_diario_prospeccao');
        });

        Schema::dropIfExists('outreach_attempts');

        Schema::table('outreach_drafts', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_channel_id', 'scheduled_for']);
            $table->dropColumn(['variantes', 'variante_escolhida', 'scheduled_for', 'erro']);
        });
    }
};
