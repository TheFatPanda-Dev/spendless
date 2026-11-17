<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plaid is gone: Enable Banking is the only bank provider. This migration
 *  - deletes Plaid connections (their accounts and transactions cascade) and the wallets left empty,
 *  - drops Plaid-only columns and the webhook table,
 *  - merges duplicated columns (date/booked_at, currency/iso_currency_code, currency/currency_code),
 *  - adds the few columns the Enable Banking flow needs.
 *
 * Amounts now follow one convention everywhere: positive = money in, negative = money out.
 * The data that survives (manual wallets) already uses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->deletePlaidData();

        Schema::dropIfExists('plaid_webhook_events');

        $this->normalizeConnections();
        $this->normalizeAccounts();
        $this->normalizeTransactions();

        Schema::table('bank_sync_runs', function (Blueprint $table): void {
            $table->dropColumn(['cursor_before', 'cursor_after']);
        });
    }

    public function down(): void
    {
        // Deleted Plaid data and dropped columns cannot be restored.
    }

    private function deletePlaidData(): void
    {
        $plaidConnections = DB::table('bank_connections')->where('provider', 'plaid');
        $walletIds = (clone $plaidConnections)->whereNotNull('wallet_id')->pluck('wallet_id')->unique()->all();

        (clone $plaidConnections)->delete();

        // Half-finished Enable Banking authorisations from the old flow.
        DB::table('bank_connections')
            ->where('provider', 'enable_banking')
            ->whereIn('status', ['pending', 'active'])
            ->whereNull('wallet_id')
            ->delete();

        if ($walletIds !== []) {
            DB::table('wallets')
                ->whereIn('id', $walletIds)
                ->whereNotExists(fn ($query) => $query
                    ->select(DB::raw(1))
                    ->from('bank_connections')
                    ->whereColumn('bank_connections.wallet_id', 'wallets.id'))
                ->delete();
        }
    }

    private function normalizeConnections(): void
    {
        Schema::table('bank_connections', function (Blueprint $table): void {
            $table->dropUnique(['plaid_item_id_hash']);
        });

        Schema::table('bank_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'plaid_item_id_hash',
                'plaid_item_id_encrypted',
                'plaid_access_token_encrypted',
                'plaid_cursor',
                'institution_id',
                'available_products',
                'last_webhook_at',
                'error_code',
                'error_message',
            ]);
        });

        Schema::table('bank_connections', function (Blueprint $table): void {
            $table->string('institution_logo')->nullable()->after('institution_name');
            $table->timestamp('consent_reminder_sent_at')->nullable()->after('consent_valid_until');
        });
    }

    private function normalizeAccounts(): void
    {
        DB::table('bank_accounts')
            ->whereNull('currency')
            ->whereNotNull('currency_code')
            ->update(['currency' => DB::raw('currency_code')]);

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropUnique(['bank_connection_id', 'plaid_account_id']);
        });

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropColumn(['plaid_account_id', 'currency_code', 'iban', 'raw_payload']);
        });

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->string('identification_hash')->nullable()->after('external_uid');
            $table->index('identification_hash');
        });
    }

    private function normalizeTransactions(): void
    {
        DB::table('bank_transactions')
            ->whereNull('date')
            ->whereNotNull('booked_at')
            ->update(['date' => DB::raw('booked_at')]);

        DB::table('bank_transactions')
            ->whereNull('currency')
            ->whereNotNull('iso_currency_code')
            ->update(['currency' => DB::raw('iso_currency_code')]);

        // MySQL keeps an index on bank_connection_id for its foreign key, so add the
        // replacement index before dropping the ones that contain Plaid columns.
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->index(['bank_connection_id', 'date']);
        });

        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropUnique(['bank_connection_id', 'plaid_transaction_id']);
            $table->dropIndex(['bank_connection_id', 'removed_at']);
            $table->dropIndex(['booked_at']);
        });

        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropColumn([
                'plaid_transaction_id',
                'plaid_pending_transaction_id',
                'account_owner',
                'booked_at',
                'iso_currency_code',
                'unofficial_currency_code',
                'authorized_date',
                'category',
                'personal_finance_category',
                'payment_channel',
                'location_encrypted',
                'raw_payload',
                'raw_encrypted',
                'removed_at',
            ]);
        });
    }
};
