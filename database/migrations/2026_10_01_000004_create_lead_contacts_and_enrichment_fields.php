<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_id')->index()->constrained()->cascadeOnDelete();
            $table->string('tipo', 20);          // whatsapp | telefone | email | instagram | facebook | site
            $table->string('valor');
            $table->string('valor_e164', 20)->nullable();
            $table->string('line_type', 20)->nullable();
            $table->string('origem', 30);        // where it was found, kept for LGPD
            $table->unsignedTinyInteger('confianca')->default(0);
            $table->boolean('provavel_decisor')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->string('evidencia')->nullable();
            $table->timestamp('verificado_em')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'tipo', 'valor']);
            $table->index(['lead_id', 'valor_e164']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable()->index();
            $table->string('razao_social')->nullable();
            $table->string('decisor_nome')->nullable();
            $table->string('decisor_cargo')->nullable();
            $table->string('porte', 10)->nullable();             // MEI | ME | EPP | DEMAIS
            $table->date('data_abertura')->nullable();
            $table->string('situacao_cadastral', 30)->nullable();
            $table->string('instagram')->nullable();
            $table->string('email')->nullable();
            $table->string('business_status', 30)->nullable();
            $table->json('horario_funcionamento')->nullable();
            $table->string('enrichment_status', 10)->nullable(); // pending | running | done | failed
            $table->timestamp('enriched_at')->nullable();
            $table->unsignedTinyInteger('contact_confidence')->nullable();
            $table->json('dossie')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['cnpj']);
            $table->dropColumn([
                'cnpj', 'razao_social', 'decisor_nome', 'decisor_cargo', 'porte', 'data_abertura',
                'situacao_cadastral', 'instagram', 'email', 'business_status', 'horario_funcionamento',
                'enrichment_status', 'enriched_at', 'contact_confidence', 'dossie',
            ]);
        });

        Schema::dropIfExists('lead_contacts');
    }
};
