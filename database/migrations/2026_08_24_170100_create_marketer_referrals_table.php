<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 16);
            $table->string('landing_path')->nullable();
            $table->timestamp('registered_at');
            $table->timestamps();

            $table->unique('referred_user_id');
            $table->index('marketer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_referrals');
    }
};
