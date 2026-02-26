<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sitekit_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('sitekit_accounts')->cascadeOnDelete();
            $table->longText('access_token');
            $table->longText('refresh_token')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->longText('scopes')->nullable();
            $table->timestamps();

            $table->unique('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitekit_tokens');
    }
};
