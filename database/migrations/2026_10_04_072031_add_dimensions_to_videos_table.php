<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Largura e altura do vídeo: o player já abre no formato certo (em pé ou deitado). */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedSmallInteger('video_width')->nullable()->after('duration');
            $table->unsignedSmallInteger('video_height')->nullable()->after('video_width');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['video_width', 'video_height']);
        });
    }
};
