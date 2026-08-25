<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('payer_user_id')->constrained('users')->restrictOnDelete();
            $table->tinyInteger('type');
            $table->decimal('amount', 12, 3);
            $table->tinyInteger('status');
            $table->tinyInteger('payment_method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('related_merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->timestamps();

            $table->index('payer_user_id');
            $table->index('type');
            $table->index('status');
            $table->index('payment_method');
            $table->index('paid_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
