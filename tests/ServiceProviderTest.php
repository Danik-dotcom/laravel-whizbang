<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Ludovicguenet\Whizbang\Commands\SchemaRollbackCommand;
use Ludovicguenet\Whizbang\Commands\SchemaSnapshotCommand;
use Ludovicguenet\Whizbang\Commands\SchemaStatusCommand;
use Ludovicguenet\Whizbang\Whizbang;

it('binds the Whizbang service as a singleton', function (): void {
    expect(app()->bound(Whizbang::class))->toBeTrue();
    expect(app(Whizbang::class))->toBeInstanceOf(Whizbang::class);
});

it('registers console commands', function (): void {
    /** @var ConsoleKernel $kernel */
    $kernel = app(ConsoleKernel::class);

    $commands = array_keys($kernel->all());

    expect($commands)->toContain('schema:snapshot');
    expect($commands)->toContain('schema:status');
    expect($commands)->toContain('schema:rollback');

    // Smoke-run list to ensure kernel is operational
    test()->artisan('list');
});
