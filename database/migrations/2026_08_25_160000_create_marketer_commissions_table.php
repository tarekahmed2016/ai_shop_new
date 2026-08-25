<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_commissions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->foreignId('marketer_referral_id')->constrained('marketer_referrals')->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->restrictOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->restrictOnDelete();
            $table->tinyInteger('payment_type');
            $table->decimal('payment_amount', 12, 3);
            $table->tinyInteger('commission_type');
            $table->decimal('commission_rate', 6, 3);
            $table->decimal('commission_amount', 12, 3);
            $table->tinyInteger('status');
            $table->timestamp('earned_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('payment_transaction_id', 'mk_comm_pay_uid');
            $table->index('marketer_id', 'mk_comm_marketer_idx');
            $table->index('status', 'mk_comm_status_idx');
            $table->index('earned_at', 'mk_comm_earned_idx');
            $table->index(['marketer_id', 'status'], 'mk_comm_mk_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_commissions');
    }
};
