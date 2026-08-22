<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_classifications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_request_id')->constrained('customer_requests')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('model', 128)->nullable();
            $table->string('detected_item')->nullable();
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('alternatives')->nullable();
            $table->boolean('needs_more_information')->default(false);
            $table->text('question')->nullable();
            $table->text('reason')->nullable();
            $table->tinyInteger('status');
            $table->foreignId('customer_confirmed_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->boolean('input_has_image')->default(false);
            $table->timestamps();

            $table->index('customer_request_id');
            $table->index('status');
            $table->index('suggested_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_classifications');
    }
};
