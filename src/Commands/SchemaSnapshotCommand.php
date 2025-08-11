<?php

declare(strict_types=1);

namespace Ludovicguenet\Whizbang\Commands;

use Illuminate\Console\Command;
use Ludovicguenet\Whizbang\Whizbang;

class SchemaSnapshotCommand extends Command
{
    /** @var string */
    protected $signature = 'schema:snapshot {--reason=manual : Reason for taking snapshot}';

    /** @var string */
    protected $description = 'Take a snapshot of the current database schema';

    public function handle(Whizbang $guardian): int
    {
        $this->info('Taking schema snapshot...');

        $snapshot = $guardian->captureSchemaSnapshot();
        $id = $guardian->saveSnapshot($snapshot, (string) $this->option('reason'));

        $this->info("Schema snapshot saved with ID: {$id}");
        $this->info('Tables captured: '.count($snapshot['tables']));

        return self::SUCCESS;
    }
}
