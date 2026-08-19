<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->string('theme_primary_color', 7)->nullable()->after('whatsapp');
            $table->string('theme_dark_color', 7)->nullable()->after('theme_primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->dropColumn(['theme_primary_color', 'theme_dark_color']);
        });
    }
};
