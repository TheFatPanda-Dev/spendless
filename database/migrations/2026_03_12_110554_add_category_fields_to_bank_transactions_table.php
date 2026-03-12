<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('categories')
                ->nullOnDelete();
            $table->boolean('category_manually_set')
                ->default(false)
                ->after('category_id');

            $table->index(['bank_account_id', 'category_id']);
        });

        $timestamp = now();

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($timestamp): void {
                $rows = [];

                foreach ($users as $user) {
                    $hasOtherExpense = DB::table('categories')
                        ->where('user_id', $user->id)
                        ->where('type', 'expense')
                        ->where('name', 'Other')
                        ->exists();

                    if (! $hasOtherExpense) {
                        $rows[] = [
                            'user_id' => $user->id,
                            'parent_id' => null,
                            'name' => 'Other',
                            'type' => 'expense',
                            'icon' => 'package',
                            'color' => 'slate',
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('categories')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex(['bank_account_id', 'category_id']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('category_manually_set');
        });
    }
};
