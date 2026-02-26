<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sitekit_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('sitekit_accounts')->nullOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitekit_settings');
    }
};
