<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->longText('custom_css')->nullable()->after('theme_on_dark_text_color');
            $table->longText('custom_js')->nullable()->after('custom_css');
        });
    }

    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->dropColumn(['custom_css', 'custom_js']);
        });
    }
};
