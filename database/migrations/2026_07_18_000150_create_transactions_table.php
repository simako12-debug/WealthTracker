<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->index()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 20, 10);
            $table->date('transaction_date');
            $table->text('note')->nullable();
            $table->string('counterparty')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
