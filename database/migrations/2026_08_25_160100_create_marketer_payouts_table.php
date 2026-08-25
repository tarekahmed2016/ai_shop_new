<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->decimal('amount', 12, 3);
            $table->tinyInteger('payment_method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('marketer_id', 'mk_payout_marketer_idx');
            $table->index('paid_at', 'mk_payout_paid_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_payouts');
    }
};
