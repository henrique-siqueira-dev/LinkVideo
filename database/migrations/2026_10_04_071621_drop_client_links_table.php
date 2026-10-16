<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Os links individuais por cliente deixaram de existir: cada filme tem um link fixo.
     * (A versão com clientes fica salva no Git: ramo "versao-com-clientes".)
     */
    public function up(): void
    {
        Schema::dropIfExists('client_links');
    }

    public function down(): void
    {
        Schema::create('client_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name', 120)->nullable();
            $table->string('client_phone', 25)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('token', 64)->unique();
            $table->string('device_hash', 64)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('claimed_ip', 45)->nullable();
            $table->string('claimed_user_agent')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }
};
