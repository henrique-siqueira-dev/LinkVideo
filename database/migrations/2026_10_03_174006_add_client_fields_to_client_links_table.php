<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_links', function (Blueprint $table) {
            $table->string('client_name', 120)->nullable()->after('user_id');
            $table->string('client_phone', 25)->nullable()->after('client_name');
            $table->string('notes', 500)->nullable()->after('client_phone');
        });
    }

    public function down(): void
    {
        Schema::table('client_links', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'client_phone', 'notes']);
        });
    }
};
