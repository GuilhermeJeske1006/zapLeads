<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // leads: drop index that includes loja_id before dropping the column.
        if (Schema::hasColumn('leads', 'loja_id')) {
            Schema::table('leads', function (Blueprint $t) {
                $t->dropIndex('leads_external_unique');
                $t->unsignedBigInteger('loja_id')->nullable()->change();
            });

            Schema::table('leads', function (Blueprint $t) {
                $t->dropColumn('loja_id');
            });

            // Re-create unique index without loja_id.
            Schema::table('leads', function (Blueprint $t) {
                $t->unique(['empresa_id', 'external_source', 'external_id'], 'leads_external_unique');
            });
        }

        if (Schema::hasColumn('conversations', 'loja_id')) {
            Schema::table('conversations', function (Blueprint $t) {
                $t->dropIndex('conversations_loja_id_telefone_unique');
                $t->unsignedBigInteger('loja_id')->nullable()->change();
            });

            Schema::table('conversations', function (Blueprint $t) {
                $t->dropColumn('loja_id');
            });

            // Re-create unique index scoped to empresa_id.
            Schema::table('conversations', function (Blueprint $t) {
                $t->unique(['empresa_id', 'telefone'], 'conversations_empresa_id_telefone_unique');
            });
        }
    }

    public function down(): void
    {
        // Not reversible — lojas table is gone.
    }
};
