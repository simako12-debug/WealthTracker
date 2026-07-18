<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_pairs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('base_currency_id')->index()->constrained('currencies')->cascadeOnDelete();
            $table->foreignUuid('quote_currency_id')->index()->constrained('currencies')->cascadeOnDelete();
            $table->string('source');
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['base_currency_id', 'quote_currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_pairs');
    }
};
