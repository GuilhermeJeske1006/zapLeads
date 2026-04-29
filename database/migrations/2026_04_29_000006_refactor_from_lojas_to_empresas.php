<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('whatsapp', 20)->nullable()->after('nome');
            $table->string('endereco')->nullable()->after('whatsapp');
            $table->string('cidade')->nullable()->after('endereco');
            $table->decimal('latitude', 10, 7)->nullable()->after('cidade');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('raio_atendimento')->default(10)->after('longitude');
            $table->string('slug')->nullable()->unique()->after('raio_atendimento');
            $table->string('logo')->nullable()->after('slug');
            $table->boolean('ativo')->default(true)->after('logo');
            $table->text('descricao_empresa')->nullable()->after('ativo');
            $table->string('tipo_cliente_alvo')->nullable()->after('descricao_empresa');
            $table->boolean('bot_ativo')->default(false)->after('tipo_cliente_alvo');
            $table->time('bot_horario_inicio')->nullable()->after('bot_ativo');
            $table->time('bot_horario_fim')->nullable()->after('bot_horario_inicio');
        });

        // Copy profile fields from the first loja of each empresa (best-effort).
        $empresas = DB::table('empresas')->select('id')->get();
        foreach ($empresas as $empresa) {
            $loja = DB::table('lojas')
                ->where('empresa_id', $empresa->id)
                ->orderBy('id')
                ->first();

            if (!$loja) {
                continue;
            }

            DB::table('empresas')
                ->where('id', $empresa->id)
                ->update([
                    'nome' => $loja->nome ?? null,
                    'whatsapp' => $loja->whatsapp ?? null,
                    'endereco' => $loja->endereco ?? null,
                    'cidade' => $loja->cidade ?? null,
                    'latitude' => $loja->latitude ?? null,
                    'longitude' => $loja->longitude ?? null,
                    'raio_atendimento' => $loja->raio_atendimento ?? 10,
                    'slug' => $loja->slug ?? null,
                    'logo' => $loja->logo ?? null,
                    'ativo' => $loja->ativo ?? true,
                    'descricao_empresa' => $loja->descricao_empresa ?? null,
                    'tipo_cliente_alvo' => $loja->tipo_cliente_alvo ?? null,
                    'bot_ativo' => $loja->bot_ativo ?? false,
                    'bot_horario_inicio' => $loja->bot_horario_inicio ?? null,
                    'bot_horario_fim' => $loja->bot_horario_fim ?? null,
                ]);
        }

        $this->migrateLojaIdToEmpresaId('produtos');
        $this->migrateLojaIdToEmpresaId('leads');
        $this->migrateLojaIdToEmpresaId('conversations');
        $this->migrateLojaIdToEmpresaId('campaigns');
        $this->migrateLojaIdToEmpresaId('message_logs');
        $this->migrateLojaIdToEmpresaId('prospecting_searches');
        $this->migrateLojaIdToEmpresaId('sequences');

        // Loja is no longer part of the domain model.
        Schema::dropIfExists('lojas');
    }

    public function down(): void
    {
        // Not reversible safely (would require rebuilding lojas and backfilling).
    }

    private function migrateLojaIdToEmpresaId(string $table): void
    {
        Schema::table($table, function (Blueprint $tableBlueprint) {
            $tableBlueprint->foreignId('empresa_id')
                ->nullable()
                ->after('id')
                ->constrained('empresas')
                ->cascadeOnDelete();
        });

        // Backfill via lojas.empresa_id
        DB::statement("
            UPDATE {$table}
            SET empresa_id = (
                SELECT empresa_id FROM lojas WHERE lojas.id = {$table}.loja_id
            )
            WHERE empresa_id IS NULL
        ");

        // Drop loja_id FK/column (best-effort on drivers that support it).
        try {
            Schema::table($table, function (Blueprint $tableBlueprint) {
                $tableBlueprint->dropConstrainedForeignId('loja_id');
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $tableBlueprint) {
                    $tableBlueprint->dropColumn('loja_id');
                });
            } catch (\Throwable) {
                // If the driver doesn't support dropping columns, keep loja_id as legacy.
            }
        }

        // Enforce non-null empresa_id (best-effort).
        try {
            Schema::table($table, function (Blueprint $tableBlueprint) {
                $tableBlueprint->foreignId('empresa_id')->nullable(false)->change();
            });
        } catch (\Throwable) {
            // Ignore if driver can't alter columns.
        }
    }
};

