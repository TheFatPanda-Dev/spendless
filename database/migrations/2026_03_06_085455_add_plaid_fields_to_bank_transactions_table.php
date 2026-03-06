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
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('bank_connection_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('plaid_transaction_id')->nullable()->after('external_uid');
            $table->string('plaid_pending_transaction_id')->nullable()->after('plaid_transaction_id');
            $table->string('account_owner')->nullable()->after('plaid_pending_transaction_id');
            $table->string('merchant_name')->nullable()->after('account_owner');
            $table->string('payee')->nullable()->after('merchant_name');
            $table->string('name')->nullable()->after('payee');
            $table->string('iso_currency_code', 3)->nullable()->after('currency');
            $table->string('unofficial_currency_code', 3)->nullable()->after('iso_currency_code');
            $table->date('date')->nullable()->after('unofficial_currency_code');
            $table->date('authorized_date')->nullable()->after('date');
            $table->boolean('pending')->default(false)->after('authorized_date');
            $table->json('category')->nullable()->after('pending');
            $table->json('personal_finance_category')->nullable()->after('category');
            $table->string('payment_channel')->nullable()->after('personal_finance_category');
            $table->longText('location_encrypted')->nullable()->after('payment_channel');
            $table->longText('raw_encrypted')->nullable()->after('location_encrypted');
            $table->timestamp('removed_at')->nullable()->after('raw_encrypted');

            $table->unique(['bank_connection_id', 'plaid_transaction_id']);
            $table->index(['bank_connection_id', 'removed_at']);
            $table->index(['bank_account_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropUnique(['bank_connection_id', 'plaid_transaction_id']);
            $table->dropIndex(['bank_connection_id', 'removed_at']);
            $table->dropIndex(['bank_account_id', 'date']);
            $table->dropConstrainedForeignId('bank_connection_id');
            $table->dropColumn([
                'plaid_transaction_id',
                'plaid_pending_transaction_id',
                'account_owner',
                'merchant_name',
                'payee',
                'name',
                'iso_currency_code',
                'unofficial_currency_code',
                'date',
                'authorized_date',
                'pending',
                'category',
                'personal_finance_category',
                'payment_channel',
                'location_encrypted',
                'raw_encrypted',
                'removed_at',
            ]);
        });
    }
};
