<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->longText('snapshot_data');
            $table->string('reason')->default('manual');
            $table->timestamp('created_at');

            $table->index('created_at');
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_snapshots');
    }
};
