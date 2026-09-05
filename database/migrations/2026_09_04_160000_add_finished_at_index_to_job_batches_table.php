<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * queue:prune-batches filters on finished_at. job_batches grows by one
 * row per matched customer request and was unindexed on that column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_batches', function (Blueprint $table) {
            $table->index('finished_at', 'job_batches_finished_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('job_batches', function (Blueprint $table) {
            $table->dropIndex('job_batches_finished_at_index');
        });
    }
};
