<?php

use App\Models\Empresa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lojas', function (Blueprint $table) {
            $table->foreignId('empresa_id')
                ->nullable()
                ->after('id')
                ->constrained('empresas')
                ->cascadeOnDelete();
        });

        $userIds = DB::table('lojas')->distinct()->pluck('user_id')->filter()->values();
        foreach ($userIds as $userId) {
            $empresaId = DB::table('empresas')->where('user_id', $userId)->value('id');
            if (!$empresaId) {
                $empresaId = Empresa::create(['user_id' => $userId])->id;
            }

            DB::table('lojas')
                ->where('user_id', $userId)
                ->whereNull('empresa_id')
                ->update(['empresa_id' => $empresaId]);
        }

        // Enforce "loja pertence a uma empresa" (se o driver suportar alteração de coluna).
        try {
            Schema::table('lojas', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable(false)->change();
            });
        } catch (\Throwable) {
            // Some drivers require extra dependencies for column alterations.
        }

        Schema::table('lojas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('lojas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('lojas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
        });
    }
};
