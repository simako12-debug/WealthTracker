<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liability_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('liability_id')->index()->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('total_amount', 20, 10);
            $table->decimal('principal_portion', 20, 10)->nullable();
            $table->decimal('interest_portion', 20, 10)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['liability_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_payments');
    }
};
