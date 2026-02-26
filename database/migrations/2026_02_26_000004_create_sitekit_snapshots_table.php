<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sitekit_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('sitekit_accounts')->cascadeOnDelete();
            $table->string('connector');
            $table->string('period');
            $table->json('data')->nullable();
            $table->dateTime('fetched_at');
            $table->date('fetched_on');
            $table->timestamps();

            $table->unique(['account_id', 'connector', 'period', 'fetched_on'], 'sitekit_snapshots_unique_daily');
            $table->index(['account_id', 'connector', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitekit_snapshots');
    }
};
