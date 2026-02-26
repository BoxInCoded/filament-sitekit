<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sitekit_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('sitekit_accounts', 'name')) {
                $table->string('name')->nullable()->after('display_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sitekit_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('sitekit_accounts', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
