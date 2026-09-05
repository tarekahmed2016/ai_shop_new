<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only HTTP intake idempotency keys. customer_requests.submission_token
 * is overwritten on retry/confirm, so it cannot be the long-lived lookup
 * for a previously accepted classify (or confirm) token. This table keeps
 * every accepted (customer, action, token) permanently resolvable to the
 * original row without restarting the pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_request_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('customer_request_id')->nullable();
            $table->string('action', 16);
            $table->string('token', 40);
            $table->timestamps();

            $table->foreign('customer_id', 'crik_customer_fk')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();

            $table->foreign('customer_request_id', 'crik_request_fk')
                ->references('id')
                ->on('customer_requests')
                ->nullOnDelete();

            $table->unique(['customer_id', 'action', 'token'], 'crik_customer_action_token_uq');
            $table->index('customer_request_id', 'crik_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_request_idempotency_keys');
    }
};
