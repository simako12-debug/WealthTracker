<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('currency_from_id')->index()->constrained('currencies')->cascadeOnDelete();
            $table->foreignUuid('currency_to_id')->index()->constrained('currencies')->cascadeOnDelete();
            $table->decimal('rate', 20, 10);
            $table->date('rate_date');
            $table->string('source');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['currency_from_id', 'currency_to_id', 'rate_date', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
