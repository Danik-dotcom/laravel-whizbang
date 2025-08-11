<?php

declare(strict_types=1);

namespace Ludovicguenet\Whizbang;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ludovicguenet\Whizbang\Commands\SchemaRollbackCommand;
use Ludovicguenet\Whizbang\Commands\SchemaSnapshotCommand;
use Ludovicguenet\Whizbang\Commands\SchemaStatusCommand;
use Ludovicguenet\Whizbang\Listeners\SchemaChangeTracker;

class WhizbangServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Whizbang::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/whizbang.php' => config_path('whizbang.php'),
        ], 'whizbang-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SchemaRollbackCommand::class,
                SchemaStatusCommand::class,
                SchemaSnapshotCommand::class,
            ]);
        }

        Event::listen(MigrationsStarted::class, [SchemaChangeTracker::class, 'beforeMigration']);
        Event::listen(MigrationsEnded::class, [SchemaChangeTracker::class, 'afterMigration']);
    }
}
