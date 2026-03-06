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
        if (! Schema::hasTable('wallets') || ! Schema::hasTable('bank_connections')) {
            return;
        }

        Schema::table('bank_connections', function (Blueprint $table) {
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('bank_connections')) {
            return;
        }

        Schema::table('bank_connections', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
        });
    }
};
