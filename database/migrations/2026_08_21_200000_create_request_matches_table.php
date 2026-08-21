<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_request_id')->constrained('customer_requests')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_request_id', 'merchant_id']);
            $table->index('customer_request_id');
            $table->index('merchant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_matches');
    }
};
