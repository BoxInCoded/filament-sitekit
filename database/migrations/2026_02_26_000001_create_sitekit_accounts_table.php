<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sitekit_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('provider');
            $table->string('email')->nullable()->index();
            $table->string('display_name')->nullable();
            $table->timestamps();

            $table->index(['provider', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitekit_accounts');
    }
};
