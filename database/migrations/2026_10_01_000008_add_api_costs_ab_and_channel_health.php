<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per paid call (Claude, Google Places, Twilio Lookup), priced when it happened.
        Schema::create('api_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prospecting_search_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origem', 30)->nullable(); // busca | enriquecimento | score | abordagem | conversa | insights
            $table->string('servico', 30);            // anthropic | google_places | twilio_lookup
            $table->string('sku', 60);                // model id, Places SKU, lookup package
            $table->unsignedInteger('quantidade')->default(1);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedSmallInteger('web_searches')->default(0);
            $table->decimal('custo_usd', 12, 6)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['empresa_id', 'created_at']);
            $table->index('created_at');
        });

        // Summary of the search's api_usages, written when it finishes.
        Schema::table('prospecting_searches', function (Blueprint $table) {
            $table->json('custos')->nullable()->after('progress');
        });

        // A/B by template and channel health need to know how each attempt went out.
        Schema::table('outreach_attempts', function (Blueprint $table) {
            $table->foreignId('whatsapp_channel_id')->nullable()->after('canal')->constrained('whatsapp_channels')->nullOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->after('whatsapp_channel_id')->constrained('whatsapp_templates')->nullOnDelete();

            $table->index(['empresa_id', 'etapa', 'variante']);
        });

        DB::table('outreach_attempts')
            ->where('canal', 'api')
            ->whereNotNull('outreach_draft_id')
            ->update(['whatsapp_channel_id' => DB::raw('(select whatsapp_channel_id from outreach_drafts where outreach_drafts.id = outreach_attempts.outreach_draft_id)')]);

        Schema::table('whatsapp_channels', function (Blueprint $table) {
            // From Twilio's Senders API (Meta): HIGH|MEDIUM|LOW|UNKNOWN, "1K Customers/24hr", ONLINE|OFFLINE...
            $table->string('qualidade', 10)->nullable();
            $table->string('limite_mensagens', 40)->nullable();
            $table->string('sender_status', 30)->nullable();
            $table->timestamp('saude_verificada_em')->nullable();
            // Prospecting stops on this number until the user resumes it; replies and chat go on.
            $table->timestamp('prospeccao_pausada_em')->nullable();
            $table->string('pausa_motivo', 30)->nullable(); // quality_low | sender_offline | opt_out_rate
            $table->timestamp('prospeccao_retomada_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_channels', function (Blueprint $table) {
            $table->dropColumn([
                'qualidade', 'limite_mensagens', 'sender_status', 'saude_verificada_em',
                'prospeccao_pausada_em', 'pausa_motivo', 'prospeccao_retomada_em',
            ]);
        });

        Schema::table('outreach_attempts', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'etapa', 'variante']);
            $table->dropConstrainedForeignId('whatsapp_template_id');
            $table->dropConstrainedForeignId('whatsapp_channel_id');
        });

        Schema::table('prospecting_searches', function (Blueprint $table) {
            $table->dropColumn('custos');
        });

        Schema::dropIfExists('api_usages');
    }
};
