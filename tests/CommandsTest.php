<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('schema:status runs without error against empty tables', function (): void {
    // Create minimal tables required by the command
    DB::statement('CREATE TABLE schema_snapshots (id INTEGER PRIMARY KEY, snapshot_data TEXT, reason TEXT, created_at TEXT)');
    DB::statement('CREATE TABLE schema_rollbacks (id INTEGER PRIMARY KEY, snapshot_id INTEGER, rolled_back_at TEXT, rolled_back_by TEXT)');

    $this->artisan('schema:status')->assertSuccessful();
});

it('schema:snapshot inserts a snapshot', function (): void {
    DB::statement('CREATE TABLE schema_snapshots (id INTEGER PRIMARY KEY, snapshot_data TEXT, reason TEXT, created_at TEXT)');

    $this->artisan('schema:snapshot --reason="test"')->assertSuccessful();

    $count = DB::table('schema_snapshots')->count();
    expect($count)->toBe(1);
});
