<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Master que gerou');
            $table->string('token', 64)->unique();
            $table->string('device_hash', 64)->nullable()->comment('SHA-256 do segredo gravado no navegador de quem abriu primeiro');
            $table->timestamp('claimed_at')->nullable();
            $table->string('claimed_ip', 45)->nullable();
            $table->string('claimed_user_agent')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_links');
    }
};
