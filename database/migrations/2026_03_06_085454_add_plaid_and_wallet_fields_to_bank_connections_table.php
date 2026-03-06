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
        if (! Schema::hasColumn('bank_connections', 'wallet_id')) {
            Schema::table('bank_connections', function (Blueprint $table) {
                $table->unsignedBigInteger('wallet_id')->nullable()->after('id');
            });
        }

        Schema::table('bank_connections', function (Blueprint $table) {
            $table->string('provider')->default('enable_banking')->after('user_id');
            $table->string('plaid_item_id_hash')->nullable()->unique()->after('provider');
            $table->text('plaid_item_id_encrypted')->nullable()->after('plaid_item_id_hash');
            $table->text('plaid_access_token_encrypted')->nullable()->after('plaid_item_id_encrypted');
            $table->string('institution_id')->nullable()->after('plaid_access_token_encrypted');
            $table->string('institution_name')->nullable()->after('institution_id');
            $table->text('plaid_cursor')->nullable()->after('institution_name');
            $table->timestamp('last_webhook_at')->nullable()->after('last_synced_at');
            $table->json('available_products')->nullable()->after('last_webhook_at');
            $table->string('error_code')->nullable()->after('last_sync_error');
            $table->text('error_message')->nullable()->after('error_code');
            $table->json('metadata')->nullable()->after('error_message');
            $table->unsignedSmallInteger('sync_failures')->default(0)->after('metadata');

            $table->index(['user_id', 'wallet_id']);
            $table->index(['provider', 'status']);
            $table->index(['provider', 'next_sync_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_connections', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'wallet_id']);
            $table->dropIndex(['provider', 'status']);
            $table->dropIndex(['provider', 'next_sync_at']);
            $table->dropColumn([
                'wallet_id',
                'provider',
                'plaid_item_id_hash',
                'plaid_item_id_encrypted',
                'plaid_access_token_encrypted',
                'institution_id',
                'institution_name',
                'plaid_cursor',
                'last_webhook_at',
                'available_products',
                'error_code',
                'error_message',
                'metadata',
                'sync_failures',
            ]);
        });
    }
};
