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
        Schema::create('bank_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('aspsp_name');
            $table->string('aspsp_country', 2)->default('SI');
            $table->string('state')->unique();
            $table->string('status')->default('pending');
            $table->string('session_id')->nullable()->unique();
            $table->timestamp('consent_valid_until')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_sync_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_connections');
    }
};
