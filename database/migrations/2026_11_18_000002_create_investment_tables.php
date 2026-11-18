<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_connections', function (Blueprint $table): void {
            // Provider secrets such as the IBKR Flex token (encrypted cast on the model).
            $table->text('credentials_encrypted')->nullable()->after('metadata');
        });

        Schema::create('investment_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('conid', 64);
            $table->string('symbol', 64);
            $table->string('description')->nullable();
            $table->string('asset_category', 32)->nullable();
            $table->decimal('quantity', 24, 8);
            $table->decimal('mark_price', 24, 8)->nullable();
            $table->decimal('position_value', 18, 2);
            $table->decimal('cost_basis', 18, 2)->nullable();
            $table->decimal('unrealized_pnl', 18, 2)->nullable();
            $table->string('currency', 3);
            $table->decimal('fx_rate_to_base', 20, 10)->default(1);
            $table->decimal('value_in_base', 18, 2);
            $table->decimal('unrealized_pnl_in_base', 18, 2)->nullable();
            $table->date('report_date')->nullable();
            $table->timestamps();
            $table->unique(['bank_account_id', 'conid']);
        });

        Schema::create('investment_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('kind', 16);
            $table->date('date');
            $table->string('symbol', 64)->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 24, 8)->nullable();
            $table->decimal('price', 24, 8)->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('commission', 18, 2)->nullable();
            $table->string('currency', 3);
            $table->decimal('fx_rate_to_base', 20, 10)->default(1);
            $table->timestamps();
            $table->unique(['bank_account_id', 'external_id']);
            $table->index(['bank_account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_activities');
        Schema::dropIfExists('investment_positions');

        Schema::table('bank_connections', function (Blueprint $table): void {
            $table->dropColumn('credentials_encrypted');
        });
    }
};
