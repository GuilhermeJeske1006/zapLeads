<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loja_id')->constrained('lojas')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('telefone', 20);
            $table->string('nome_contato')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->enum('status', ['active', 'archived', 'blocked'])->default('active');
            $table->timestamps();

            $table->unique(['loja_id', 'telefone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
