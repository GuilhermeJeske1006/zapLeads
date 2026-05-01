<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sender_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('numero');
            $table->string('waba_id');
            $table->string('twilio_sid')->nullable();
            $table->string('verification_method')->default('SMS');
            $table->string('status')->default('pending_otp');
            $table->json('profile_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sender_registrations');
    }
};
