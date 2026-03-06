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
        Schema::create('plaid_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('plaid_item_id_hash')->nullable();
            $table->foreignId('bank_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('webhook_type')->nullable();
            $table->string('webhook_code')->nullable();
            $table->longText('payload_encrypted')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['plaid_item_id_hash', 'received_at']);
            $table->index(['webhook_type', 'webhook_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plaid_webhook_events');
    }
};
