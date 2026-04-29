<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loja_id')->constrained('lojas')->cascadeOnDelete();
            $table->string('telefone', 20);
            $table->text('mensagem');
            $table->enum('tipo', ['text', 'image'])->default('text');
            $table->enum('direcao', ['outbound', 'inbound'])->default('outbound');
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->json('zapi_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
