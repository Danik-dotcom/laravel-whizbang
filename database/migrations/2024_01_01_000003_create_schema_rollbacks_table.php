<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_rollbacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('snapshot_id');
            $table->timestamp('rolled_back_at');
            $table->string('rolled_back_by');

            $table->foreign('snapshot_id')->references('id')->on('schema_snapshots');
            $table->index('rolled_back_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_rollbacks');
    }
};
