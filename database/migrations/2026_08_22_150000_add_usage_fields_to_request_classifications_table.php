<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_classifications', function (Blueprint $table) {
            $table->string('provider_response_id')->nullable()->after('input_has_image');
            $table->unsignedBigInteger('input_tokens')->nullable()->after('provider_response_id');
            $table->unsignedBigInteger('cached_input_tokens')->nullable()->after('input_tokens');
            $table->unsignedBigInteger('output_tokens')->nullable()->after('cached_input_tokens');
            $table->unsignedBigInteger('reasoning_tokens')->nullable()->after('output_tokens');
            $table->unsignedBigInteger('total_tokens')->nullable()->after('reasoning_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('request_classifications', function (Blueprint $table) {
            $table->dropColumn([
                'provider_response_id',
                'input_tokens',
                'cached_input_tokens',
                'output_tokens',
                'reasoning_tokens',
                'total_tokens',
            ]);
        });
    }
};
