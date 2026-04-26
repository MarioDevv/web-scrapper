<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_snapshots', function (Blueprint $table) {
            $table->string('audit_id')->primary();
            $table->text('overview_json');
            $table->timestamp('generated_at');

            $table->foreign('audit_id')->references('id')->on('audits')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_snapshots');
    }
};
