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
            // Materialised aggregates derived from the links JSON column,
            // so the page-list read model can SELECT them flat instead of
            // decoding the whole JSON blob per row.
            $table->integer('internal_link_count')->default(0)->after('warning_count');
            $table->integer('external_link_count')->default(0)->after('internal_link_count');
            $table->integer('image_count')->default(0)->after('external_link_count');
            // missing | self | other
            $table->string('canonical_status', 16)->default('missing')->after('image_count');
        });

        // One-shot backfill from existing JSON columns. SQLite ships with
        // the json1 extension — json_each unpacks the array so we can
        // count by predicate.
        DB::statement(<<<'SQL'
            UPDATE pages
            SET internal_link_count = COALESCE((
                    SELECT COUNT(*)
                    FROM json_each(pages.links) j
                    WHERE json_extract(j.value, '$.type') = 'anchor'
                      AND json_extract(j.value, '$.internal') IN (1, true)
                ), 0),
                external_link_count = COALESCE((
                    SELECT COUNT(*)
                    FROM json_each(pages.links) j
                    WHERE json_extract(j.value, '$.type') = 'anchor'
                      AND json_extract(j.value, '$.internal') IN (0, false)
                ), 0),
                image_count = COALESCE((
                    SELECT COUNT(*)
                    FROM json_each(pages.links) j
                    WHERE json_extract(j.value, '$.type') = 'image'
                ), 0),
                canonical_status = CASE
                    WHEN canonical IS NULL THEN 'missing'
                    WHEN canonical = url THEN 'self'
                    WHEN canonical = COALESCE(final_url, url) THEN 'self'
                    ELSE 'other'
                END
        SQL);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['internal_link_count', 'external_link_count', 'image_count', 'canonical_status']);
        });
    }
};
