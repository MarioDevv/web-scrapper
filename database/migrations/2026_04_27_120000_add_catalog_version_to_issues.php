<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->string('catalog_version', 16)->nullable()->after('code');
        });

        Schema::table('site_issues', function (Blueprint $table) {
            $table->string('catalog_version', 16)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('catalog_version');
        });

        Schema::table('site_issues', function (Blueprint $table) {
            $table->dropColumn('catalog_version');
        });
    }
};
