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
            $table->string('hero_title_ar')->nullable()->after('email');
            $table->string('hero_title_en')->nullable()->after('hero_title_ar');
            $table->text('hero_description_ar')->nullable()->after('hero_title_en');
            $table->text('hero_description_en')->nullable()->after('hero_description_ar');
            $table->text('about_ar')->nullable()->after('hero_description_en');
            $table->text('about_en')->nullable()->after('about_ar');
            $table->text('vision_ar')->nullable()->after('about_en');
            $table->text('vision_en')->nullable()->after('vision_ar');
            $table->text('mission_ar')->nullable()->after('vision_en');
            $table->text('mission_en')->nullable()->after('mission_ar');
            $table->text('address_ar')->nullable()->after('mission_en');
            $table->text('address_en')->nullable()->after('address_ar');
            $table->string('website')->nullable()->after('address_en');
            $table->string('facebook')->nullable()->after('website');
            $table->string('instagram')->nullable()->after('facebook');
            $table->string('linkedin')->nullable()->after('instagram');
            $table->string('x_twitter')->nullable()->after('linkedin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->dropColumn([
                'hero_title_ar',
                'hero_title_en',
                'hero_description_ar',
                'hero_description_en',
                'about_ar',
                'about_en',
                'vision_ar',
                'vision_en',
                'mission_ar',
                'mission_en',
                'address_ar',
                'address_en',
                'website',
                'facebook',
                'instagram',
                'linkedin',
                'x_twitter',
            ]);
        });
    }
};
