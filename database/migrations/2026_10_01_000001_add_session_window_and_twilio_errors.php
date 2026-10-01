<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('last_inbound_at')->nullable();
        });

        // The 24h session counts from the contact's last message.
        DB::table('conversations')->update([
            'last_inbound_at' => DB::table('messages')
                ->selectRaw('max(created_at)')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('sender', 'lead'),
        ]);

        Schema::table('messages', function (Blueprint $table) {
            $table->string('error_code', 10)->nullable();
            // Set when the message is an approved WhatsApp template; `message` keeps the rendered text.
            $table->string('content_sid', 40)->nullable();
            $table->json('content_variables')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['error_code', 'content_sid', 'content_variables']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('last_inbound_at');
        });
    }
};
