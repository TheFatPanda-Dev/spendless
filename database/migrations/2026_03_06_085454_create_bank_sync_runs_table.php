<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->cascadeOnDelete();
            $table->string('sync_type');
            $table->string('status')->default('pending');
            $table->text('cursor_before')->nullable();
            $table->text('cursor_after')->nullable();
            $table->unsignedInteger('added_count')->default(0);
            $table->unsignedInteger('modified_count')->default(0);
            $table->unsignedInteger('removed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['bank_connection_id', 'status']);
            $table->index(['sync_type', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_sync_runs');
    }
};
