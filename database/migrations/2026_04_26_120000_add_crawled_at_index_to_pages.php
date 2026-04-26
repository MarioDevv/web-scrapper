<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Used by the polling UI's delta-fetch path:
            //   WHERE audit_id = ? AND crawled_at > ? ORDER BY crawled_at ASC
            $table->index(['audit_id', 'crawled_at'], 'pages_audit_crawled_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_audit_crawled_at_idx');
        });
    }
};
