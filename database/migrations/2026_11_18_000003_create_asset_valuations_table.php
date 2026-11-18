<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Values entered by hand over time, e.g. a pension checked in the provider's app.
        Schema::create('asset_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('value', 18, 2);
            $table->timestamps();
            $table->unique(['bank_account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_valuations');
    }
};
