<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vídeos guardados na Noxem Cloud: id do vídeo lá e o estado do preparo.
     * video_path passa a ser opcional (vídeo só na Noxem não tem arquivo local).
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('video_path')->nullable()->change();
            $table->uuid('noxem_video_id')->nullable()->unique()->after('video_path');
            $table->string('noxem_status', 20)->nullable()->after('noxem_video_id')->comment('uploading | processing | ready | failed');
            $table->string('noxem_error', 500)->nullable()->after('noxem_status');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropUnique(['noxem_video_id']);
            $table->dropColumn(['noxem_video_id', 'noxem_status', 'noxem_error']);
        });
    }
};
