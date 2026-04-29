<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('sender', ['user', 'lead']);
            $table->text('message');
            $table->enum('type', ['text', 'image', 'audio', 'document'])->default('text');
            $table->string('media_url')->nullable();
            $table->enum('status', ['sending', 'sent', 'delivered', 'read', 'failed'])->default('sending');
            $table->string('zapi_message_id')->nullable()->index();
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
