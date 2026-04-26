<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_issues', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('audit_id');
            $table->string('category');
            $table->string('severity');
            $table->string('code');
            $table->text('message');
            $table->text('context')->nullable();

            $table->foreign('audit_id')->references('id')->on('audits')->cascadeOnDelete();
            $table->index('audit_id');
            $table->index(['audit_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_issues');
    }
};
