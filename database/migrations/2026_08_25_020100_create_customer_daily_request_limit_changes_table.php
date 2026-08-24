<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_daily_request_limit_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->unsignedInteger('old_override')->nullable();
            $table->unsignedInteger('new_override')->nullable();
            $table->unsignedInteger('effective_global_limit');
            $table->unsignedInteger('old_effective_limit');
            $table->unsignedInteger('new_effective_limit');
            $table->string('change_type');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at'], 'cdr_limit_customer_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_daily_request_limit_changes');
    }
};
