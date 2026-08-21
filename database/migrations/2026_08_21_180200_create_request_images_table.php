<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_request_id')->constrained('customer_requests')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();

            $table->unique('customer_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_images');
    }
};
