<?php

declare(strict_types=1);

namespace Ludovicguenet\Whizbang\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SchemaStatusCommand extends Command
{
    /** @var string */
    protected $signature = 'schema:status';

    /** @var string */
    protected $description = 'Show recent schema changes and snapshots';

    public function handle(): int
    {
        $this->info('Whizbang Status');
        $this->newLine();

        $snapshots = DB::table('schema_snapshots')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $this->info('Recent Snapshots:');
        $headers = ['ID', 'Reason', 'Created At', 'Tables'];
        $rows = [];

        foreach ($snapshots as $snapshot) {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $snapshot->snapshot_data, true, 512, JSON_THROW_ON_ERROR);
            $rows[] = [
                $snapshot->id,
                $snapshot->reason,
                $snapshot->created_at,
                count($data['tables'] ?? []),
            ];
        }

        $this->table($headers, $rows);

        $rollbacks = DB::table('schema_rollbacks')
            ->join('schema_snapshots', 'schema_rollbacks.snapshot_id', '=', 'schema_snapshots.id')
            ->orderBy('rolled_back_at', 'desc')
            ->limit(5)
            ->get();

        if ($rollbacks->count() > 0) {
            $this->newLine();
            $this->info('Recent Rollbacks:');
            $rollbackHeaders = ['Snapshot ID', 'Rolled Back At', 'Rolled Back By'];
            $rollbackRows = [];

            foreach ($rollbacks as $rollback) {
                $rollbackRows[] = [
                    $rollback->snapshot_id,
                    $rollback->rolled_back_at,
                    $rollback->rolled_back_by,
                ];
            }

            $this->table($rollbackHeaders, $rollbackRows);
        }

        return self::SUCCESS;
    }
}
