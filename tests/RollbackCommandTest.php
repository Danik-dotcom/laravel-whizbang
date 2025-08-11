<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('schema:rollback --force executes and logs rollback', function (): void {
    DB::statement('CREATE TABLE schema_snapshots (id INTEGER PRIMARY KEY, snapshot_data TEXT, reason TEXT, created_at TEXT)');
    DB::statement('CREATE TABLE schema_rollbacks (id INTEGER PRIMARY KEY, snapshot_id INTEGER, rolled_back_at TEXT, rolled_back_by TEXT)');

    $snapshot = ['tables' => []];
    DB::table('schema_snapshots')->insert([
        'id' => 1,
        'snapshot_data' => json_encode($snapshot),
        'reason' => 'manual',
        'created_at' => now()->toISOString(),
    ]);

    $this->artisan('schema:rollback 1 --force')
        ->expectsConfirmation('Are you absolutely sure you want to proceed?', 'yes')
        ->assertSuccessful();

    expect(DB::table('schema_rollbacks')->count())->toBe(1);
});

it('schema:rollback without --force fails if unsafe', function (): void {
    // Lower threshold so few rows trigger risk
    config()->set('whizbang.safety_checks.max_row_increase', 0);

    DB::statement('CREATE TABLE schema_snapshots (id INTEGER PRIMARY KEY, snapshot_data TEXT, reason TEXT, created_at TEXT)');
    DB::statement('CREATE TABLE schema_rollbacks (id INTEGER PRIMARY KEY, snapshot_id INTEGER, rolled_back_at TEXT, rolled_back_by TEXT)');

    // Current DB has 1 row in users
    DB::statement('CREATE TABLE users (id INTEGER)');
    DB::table('users')->insert(['id' => 1]);

    $snapshot = [
        'tables' => [
            'users' => ['columns' => [], 'row_count' => 0],
        ],
    ];
    DB::table('schema_snapshots')->insert([
        'id' => 2,
        'snapshot_data' => json_encode($snapshot),
        'reason' => 'manual',
        'created_at' => now()->toISOString(),
    ]);

    $this->artisan('schema:rollback 2')->assertFailed();
    expect(DB::table('schema_rollbacks')->count())->toBe(0);
});


