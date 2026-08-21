<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->text('request_text');
            $table->tinyInteger('status')->default(1);
            $table->string('source', 32);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('source');
            $table->index('customer_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_requests');
    }
};
