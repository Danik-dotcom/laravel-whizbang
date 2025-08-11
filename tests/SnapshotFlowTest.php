<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Ludovicguenet\Whizbang\Whizbang;

it('captureSchemaSnapshot returns expected structure', function (): void {
    /** @var Whizbang $service */
    $service = app(Whizbang::class);

    // Create a simple table to be included in snapshot
    DB::statement('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)');

    $snapshot = $service->captureSchemaSnapshot();

    expect($snapshot)->toHaveKeys(['timestamp', 'tables', 'indexes', 'foreign_keys'])
        ->and($snapshot['tables'])->toBeArray();
});

it('saveSnapshot persists snapshot with reason', function (): void {
    DB::statement('CREATE TABLE schema_snapshots (id INTEGER PRIMARY KEY, snapshot_data TEXT, reason TEXT, created_at TEXT)');

    /** @var Whizbang $service */
    $service = app(Whizbang::class);

    $id = $service->saveSnapshot(['tables' => []], 'test-reason');
    $row = DB::table('schema_snapshots')->where('id', $id)->first();

    expect($row)->not()->toBeNull()
        ->and($row->reason)->toBe('test-reason');
});


