<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loja_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('status')->default('active'); // active|paused|archived
            $table->string('trigger')->default('manual'); // lead_capture|manual
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
