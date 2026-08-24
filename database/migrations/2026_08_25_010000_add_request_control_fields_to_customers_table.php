<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('daily_request_limit_override')->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('daily_request_limit_override');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
            $table->json('suspension_types')->nullable()->after('suspension_reason');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'daily_request_limit_override',
                'suspended_at',
                'suspension_reason',
                'suspension_types',
            ]);
        });
    }
};
