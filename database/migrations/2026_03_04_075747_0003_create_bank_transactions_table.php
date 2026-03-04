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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_uid');
            $table->date('booked_at')->nullable();
            $table->decimal('amount', 16, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('description')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'external_uid']);
            $table->index('booked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
