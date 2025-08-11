<?php

declare(strict_types=1);

namespace Ludovicguenet\Whizbang\Commands;

use Illuminate\Console\Command;
use Ludovicguenet\Whizbang\Whizbang;

class SchemaRollbackCommand extends Command
{
    /** @var string */
    protected $signature = 'schema:rollback {snapshot_id : The snapshot ID to rollback to} {--force : Force rollback even if risky}';

    /** @var string */
    protected $description = 'Rollback schema to a previous snapshot';

    public function handle(Whizbang $guardian): int
    {
        $snapshotId = (int) $this->argument('snapshot_id');
        $force = (bool) $this->option('force');

        $this->warn('⚠️  You are about to rollback your database schema!');
        $this->warn('This operation can cause DATA LOSS!');

        if ($force === false) {
            $safety = $guardian->canSafelyRollback($snapshotId);

            if ($safety['safe'] === false) {
                $this->error('❌ Rollback is not safe!');
                $this->error('Reason: '.$safety['reason']);

                foreach ($safety['risks'] ?? [] as $risk) {
                    $this->warn('⚠️  '.$risk);
                }

                $this->info('Use --force to override safety checks');

                return self::FAILURE;
            }

            $this->info('✅ Rollback safety check passed');
        }

        if ((bool) config('whizbang.safety_checks.require_confirmation', true)) {
            if ($this->confirm('Are you absolutely sure you want to proceed?') === false) {
                $this->info('Rollback cancelled');

                return self::SUCCESS;
            }
        }

        $this->info('Executing rollback...');

        $result = $guardian->executeRollback($snapshotId);

        if ($result['success'] === true) {
            $this->info('✅ '.$result['message']);

            return self::SUCCESS;
        }

        $this->error('❌ '.$result['message']);
        foreach ($result['risks'] ?? [] as $risk) {
            $this->warn('⚠️  '.$risk);
        }

        return self::FAILURE;
    }
}
