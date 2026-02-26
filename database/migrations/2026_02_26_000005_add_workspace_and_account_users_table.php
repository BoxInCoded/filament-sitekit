<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sitekit_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('sitekit_accounts', 'workspace_id')) {
                $table->unsignedBigInteger('workspace_id')->nullable()->after('user_id')->index();
            }
        });

        Schema::create('sitekit_account_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('sitekit_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 16)->default('viewer');
            $table->timestamps();

            $table->unique(['account_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitekit_account_users');

        Schema::table('sitekit_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('sitekit_accounts', 'workspace_id')) {
                $table->dropColumn('workspace_id');
            }
        });
    }
};
