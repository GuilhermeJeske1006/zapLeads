<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // etapa 0 is the first message; 1..n are the follow-ups of the cold cadence.
        Schema::table('outreach_drafts', function (Blueprint $table) {
            $table->unsignedTinyInteger('etapa')->default(0);
            $table->text('dor_hipotese')->nullable();
            $table->foreignId('sequence_enrollment_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('outreach_attempts', function (Blueprint $table) {
            $table->unsignedTinyInteger('etapa')->default(0);
        });

        // What an AI-written step is for (valor | novo_angulo | encerramento).
        Schema::table('sequence_steps', function (Blueprint $table) {
            $table->string('objetivo', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sequence_steps', function (Blueprint $table) {
            $table->dropColumn('objetivo');
        });

        Schema::table('outreach_attempts', function (Blueprint $table) {
            $table->dropColumn('etapa');
        });

        Schema::table('outreach_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sequence_enrollment_id');
            $table->dropColumn(['etapa', 'dor_hipotese']);
        });
    }
};
