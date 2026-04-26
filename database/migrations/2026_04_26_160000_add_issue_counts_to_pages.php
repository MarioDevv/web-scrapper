<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Materialised counts so the page list query can avoid joining
            // (or subquerying) issues per row when rendering the table.
            $table->integer('error_count')->default(0)->after('crawl_depth');
            $table->integer('warning_count')->default(0)->after('error_count');
        });

        // Backfill from existing issues so audits that already ran keep
        // showing their counts after the migration applies.
        DB::statement(<<<'SQL'
            UPDATE pages
            SET error_count = (
                SELECT COUNT(*) FROM issues
                WHERE issues.page_id = pages.id AND issues.severity = 'error'
            ),
            warning_count = (
                SELECT COUNT(*) FROM issues
                WHERE issues.page_id = pages.id AND issues.severity = 'warning'
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['error_count', 'warning_count']);
        });
    }
};
