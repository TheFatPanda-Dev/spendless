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
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('plaid_account_id')->nullable()->after('external_uid');
            $table->string('official_name')->nullable()->after('name');
            $table->text('mask_encrypted')->nullable()->after('official_name');
            $table->string('type')->nullable()->after('mask_encrypted');
            $table->string('subtype')->nullable()->after('type');
            $table->longText('balances_encrypted')->nullable()->after('subtype');
            $table->string('currency_code', 3)->nullable()->after('currency');
            $table->boolean('is_active')->default(true)->after('currency_code');
            $table->longText('raw_encrypted')->nullable()->after('is_active');

            $table->unique(['bank_connection_id', 'plaid_account_id']);
            $table->index(['bank_connection_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropUnique(['bank_connection_id', 'plaid_account_id']);
            $table->dropIndex(['bank_connection_id', 'is_active']);
            $table->dropColumn([
                'plaid_account_id',
                'official_name',
                'mask_encrypted',
                'type',
                'subtype',
                'balances_encrypted',
                'currency_code',
                'is_active',
                'raw_encrypted',
            ]);
        });
    }
};
