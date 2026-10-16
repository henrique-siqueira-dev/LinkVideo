<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Quem (IP) já abriu o link fixo de cada filme: cada IP conta uma vez por filme. */
    public function up(): void
    {
        Schema::create('video_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->timestamp('created_at')->nullable();
            $table->unique(['video_id', 'ip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_viewers');
    }
};
