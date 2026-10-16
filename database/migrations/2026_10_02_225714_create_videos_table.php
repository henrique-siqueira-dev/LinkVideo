<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('genre', 40)->index();
            $table->string('age_rating', 4)->index();
            $table->string('cover_path');
            $table->string('video_path');
            $table->string('video_original_name')->nullable();
            $table->string('video_mime', 100);
            $table->unsignedBigInteger('video_size')->default(0);
            $table->unsignedInteger('duration')->nullable()->comment('Duração em segundos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
