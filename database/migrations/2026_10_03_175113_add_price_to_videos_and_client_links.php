<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Valores guardados em centavos (inteiro) para não haver erro de arredondamento.
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedInteger('price_cents')->nullable()->after('age_rating')->comment('Valor de venda do vídeo, em centavos');
        });

        Schema::table('client_links', function (Blueprint $table) {
            $table->unsignedInteger('price_cents')->nullable()->after('notes')->comment('Valor pago pelo cliente, em centavos');
        });
    }

    public function down(): void
    {
        Schema::table('client_links', fn (Blueprint $table) => $table->dropColumn('price_cents'));
        Schema::table('videos', fn (Blueprint $table) => $table->dropColumn('price_cents'));
    }
};
