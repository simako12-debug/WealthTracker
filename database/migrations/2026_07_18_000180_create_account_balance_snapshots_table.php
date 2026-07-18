<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balance_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->index()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 20, 10);
            $table->date('snapshot_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balance_snapshots');
    }
};
