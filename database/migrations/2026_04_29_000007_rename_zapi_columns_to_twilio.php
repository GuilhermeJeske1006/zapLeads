<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->renameColumn('zapi_message_id', 'twilio_message_sid');
        });

        Schema::table('message_logs', function (Blueprint $table) {
            $table->renameColumn('zapi_response', 'provider_response');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->renameColumn('twilio_message_sid', 'zapi_message_id');
        });

        Schema::table('message_logs', function (Blueprint $table) {
            $table->renameColumn('provider_response', 'zapi_response');
        });
    }
};
