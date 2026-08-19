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
        Schema::create('certificates_awards', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title_ar');
            $table->string('title_en');
            $table->string('issuer_ar')->nullable();
            $table->string('issuer_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->date('issued_date')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->unsignedInteger('ordering')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates_awards');
    }
};
