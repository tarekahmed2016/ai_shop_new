<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->string('youtube')->nullable()->after('x_twitter');
            $table->string('tiktok')->nullable()->after('youtube');
            $table->string('snapchat')->nullable()->after('tiktok');
            $table->string('whatsapp', 2048)->nullable()->after('snapchat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->dropColumn([
                'youtube',
                'tiktok',
                'snapchat',
                'whatsapp',
            ]);
        });
    }
};
