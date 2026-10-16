<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Endereço do link fixo do filme (…/filme/{slug}). Vídeos antigos ganham o slug
        // na primeira vez que o link é exibido no painel.
        Schema::table('videos', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
