<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->string('theme_heading_text_color', 7)->nullable()->after('theme_dark_color');
            $table->string('theme_body_text_color', 7)->nullable()->after('theme_heading_text_color');
            $table->string('theme_muted_text_color', 7)->nullable()->after('theme_body_text_color');
            $table->string('theme_nav_text_color', 7)->nullable()->after('theme_muted_text_color');
            $table->string('theme_nav_hover_text_color', 7)->nullable()->after('theme_nav_text_color');
            $table->string('theme_hero_text_color', 7)->nullable()->after('theme_nav_hover_text_color');
            $table->string('theme_on_dark_text_color', 7)->nullable()->after('theme_hero_text_color');
        });
    }

    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->dropColumn([
                'theme_heading_text_color',
                'theme_body_text_color',
                'theme_muted_text_color',
                'theme_nav_text_color',
                'theme_nav_hover_text_color',
                'theme_hero_text_color',
                'theme_on_dark_text_color',
            ]);
        });
    }
};
