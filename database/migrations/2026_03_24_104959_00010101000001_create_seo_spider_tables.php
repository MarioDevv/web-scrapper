<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('color')->default('#5b8af5');
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('folder_id')->nullable();
            $table->string('seed_url');
            $table->string('status')->default('pending');
            $table->integer('max_pages')->default(500);
            $table->integer('max_depth')->default(10);
            $table->integer('concurrency')->default(5);
            $table->float('request_delay')->default(0.25);
            $table->boolean('respect_robots_txt')->default(true);
            $table->string('custom_user_agent')->nullable();
            $table->text('exclude_patterns')->default('[]');
            $table->text('include_patterns')->default('[]');
            $table->boolean('follow_external_links')->default(false);
            $table->boolean('crawl_subdomains')->default(false);
            $table->integer('pages_discovered')->default(0);
            $table->integer('pages_crawled')->default(0);
            $table->integer('pages_failed')->default(0);
            $table->integer('issues_found')->default(0);
            $table->integer('errors_found')->default(0);
            $table->integer('warnings_found')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at');

            $table->foreign('folder_id')->references('id')->on('folders')->nullOnDelete();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('audit_id');
            $table->text('url');
            $table->integer('status_code');
            $table->string('content_type')->nullable();
            $table->integer('body_size')->default(0);
            $table->float('response_time')->default(0);
            $table->text('final_url')->nullable();
            $table->text('headers')->default('{}');
            $table->integer('crawl_depth')->default(0);
            $table->boolean('is_html')->default(false);
            // Metadata
            $table->text('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('h1s')->default('[]');
            $table->text('h2s')->default('[]');
            $table->text('heading_hierarchy')->default('[]');
            $table->string('charset')->nullable();
            $table->string('viewport')->nullable();
            $table->text('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->text('og_image')->nullable();
            $table->integer('word_count')->default(0);
            $table->string('lang')->nullable();
            // Directives
            $table->boolean('noindex')->default(false);
            $table->boolean('nofollow')->default(false);
            $table->boolean('noarchive')->default(false);
            $table->boolean('nosnippet')->default(false);
            $table->boolean('noimageindex')->default(false);
            $table->integer('max_snippet')->nullable();
            $table->string('max_image_preview')->nullable();
            $table->integer('max_video_preview')->nullable();
            $table->text('canonical')->nullable();
            $table->string('directive_source')->nullable();
            // Fingerprint
            $table->string('exact_hash')->nullable();
            $table->integer('sim_hash')->nullable();
            // Serialized data
            $table->text('redirect_chain')->default('[]');
            $table->text('links')->default('[]');
            $table->text('hreflangs')->default('[]');
            $table->timestamp('crawled_at');

            $table->foreign('audit_id')->references('id')->on('audits')->cascadeOnDelete();
            $table->index('audit_id');
            $table->unique(['audit_id', 'url']);
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('page_id');
            $table->string('category');
            $table->string('severity');
            $table->string('code');
            $table->text('message');
            $table->text('context')->nullable();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index('page_id');
            $table->index('category');
            $table->index('severity');
        });

        Schema::create('frontier', function (Blueprint $table) {
            $table->id();
            $table->string('audit_id');
            $table->text('url');
            $table->integer('depth')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('audit_id')->references('id')->on('audits')->cascadeOnDelete();
            $table->index(['audit_id', 'status']);
            $table->unique(['audit_id', 'url']);
        });

        Schema::create('external_url_checks', function (Blueprint $table) {
            $table->id();
            $table->string('audit_id');
            $table->text('url');
            $table->integer('status_code')->nullable();
            $table->float('response_time')->default(0);
            $table->text('error')->nullable();
            $table->string('source_page_id');
            $table->text('anchor_text')->nullable();
            $table->timestamp('checked_at')->useCurrent();

            $table->foreign('audit_id')->references('id')->on('audits')->cascadeOnDelete();
            $table->foreign('source_page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index('audit_id');
            $table->unique(['audit_id', 'url', 'source_page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_url_checks');
        Schema::dropIfExists('frontier');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('folders');
    }
};