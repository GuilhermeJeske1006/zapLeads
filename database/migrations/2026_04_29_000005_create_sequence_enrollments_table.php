<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->string('status')->default('active'); // active|paused|completed|opted_out
            $table->dateTime('next_send_at');
            $table->timestamps();
            $table->unique(['sequence_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_enrollments');
    }
};
