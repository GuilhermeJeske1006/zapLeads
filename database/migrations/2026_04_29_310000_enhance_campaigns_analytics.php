<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedInteger('total_leads')->default(0)->after('status');
            $table->unsignedInteger('total_respondidos')->default(0)->after('total_erros');
        });

        Schema::table('campaign_leads', function (Blueprint $table) {
            $table->timestamp('replied_at')->nullable()->after('sent_at');
            $table->string('lead_status_snapshot', 30)->nullable()->after('replied_at');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('lead_id')
                ->constrained('campaigns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });

        Schema::table('campaign_leads', function (Blueprint $table) {
            $table->dropColumn(['replied_at', 'lead_status_snapshot']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['total_leads', 'total_respondidos']);
        });
    }
};
