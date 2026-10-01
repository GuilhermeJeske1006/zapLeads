<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('content_sid', 40);
            $table->string('categoria', 20)->nullable();
            $table->string('idioma', 10)->nullable();
            $table->text('corpo_preview')->nullable();
            $table->json('variaveis')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('ativo')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'content_sid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
