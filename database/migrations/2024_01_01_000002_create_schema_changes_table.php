<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_changes', function (Blueprint $table): void {
            $table->id();
            $table->longText('changes_data');
            $table->enum('risk_level', ['LOW', 'MEDIUM', 'HIGH']);
            $table->integer('dangerous_count')->default(0);
            $table->timestamp('created_at');

            $table->index('risk_level');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_changes');
    }
};
