<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lojas', function (Blueprint $table) {
            $table->text('descricao_empresa')->nullable()->after('slug');
            $table->string('tipo_cliente_alvo')->nullable()->after('descricao_empresa');
        });
    }

    public function down(): void
    {
        Schema::table('lojas', function (Blueprint $table) {
            $table->dropColumn(['descricao_empresa', 'tipo_cliente_alvo']);
        });
    }
};
