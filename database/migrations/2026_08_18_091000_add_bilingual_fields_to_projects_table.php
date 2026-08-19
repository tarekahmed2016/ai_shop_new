<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('id');
            $table->string('name_en')->nullable()->after('name_ar');
            $table->string('client_name_ar')->nullable()->after('name_en');
            $table->string('client_name_en')->nullable()->after('client_name_ar');
            $table->text('description_ar')->nullable()->after('client_name_en');
            $table->text('description_en')->nullable()->after('description_ar');
        });

        DB::table('projects')->update([
            'name_ar' => DB::raw('name'),
            'client_name_ar' => DB::raw('client_name'),
            'description_ar' => DB::raw('description'),
        ]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['name', 'client_name', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('client_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('client_name');
        });

        DB::table('projects')->update([
            'name' => DB::raw('name_ar'),
            'client_name' => DB::raw('client_name_ar'),
            'description' => DB::raw('description_ar'),
        ]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'name_ar',
                'name_en',
                'client_name_ar',
                'client_name_en',
                'description_ar',
                'description_en',
            ]);
        });
    }
};
