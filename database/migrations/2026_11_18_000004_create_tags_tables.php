<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags say WHICH thing a transaction was for (a car, a flat), next to the category that
 * says WHAT it was. Tag rules tag new transactions automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 20)->default('slate');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('bank_transaction_tag', function (Blueprint $table) {
            $table->foreignId('bank_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['bank_transaction_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('tag_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            // Either or both: only this account, and/or the description contains this text.
            $table->foreignId('bank_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('contains', 120)->nullable();
            $table->timestamps();
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            // Set once tag rules have looked at a transaction, so a tag removed by hand stays removed.
            $table->boolean('tag_rules_applied')->default(false);
            $table->index(['bank_account_id', 'tag_rules_applied']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex(['bank_account_id', 'tag_rules_applied']);
            $table->dropColumn('tag_rules_applied');
        });

        Schema::dropIfExists('tag_rules');
        Schema::dropIfExists('bank_transaction_tag');
        Schema::dropIfExists('tags');
    }
};
