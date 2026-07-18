<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('principal_amount', 20, 10);
            $table->foreignUuid('currency_id')->index()->constrained()->cascadeOnDelete();
            $table->decimal('interest_rate', 8, 4);
            $table->decimal('monthly_payment', 20, 10)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liabilities');
    }
};
