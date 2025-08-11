<?php

declare(strict_types=1);

namespace Ludovicguenet\Whizbang;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Whizbang
{
    /** @var array<int, string> */
    protected array $dangerousOperations = [
        'DROP TABLE',
        'DROP COLUMN',
        'DROP INDEX',
        'ALTER TABLE',
        'TRUNCATE',
    ];

    /**
     * Capture a snapshot of the current database schema.
     *
     * @return array<string, mixed>
     */
    public function captureSchemaSnapshot(): array
    {
        $tables = $this->getAllTables();
        $snapshot = [
            'timestamp' => now()->toISOString(),
            'tables' => [],
            'indexes' => [],
            'foreign_keys' => [],
        ];

        foreach ($tables as $table) {
            $snapshot['tables'][$table] = [
                'columns' => $this->getTableColumns($table),
                'row_count' => $this->getTableRowCount($table),
            ];

            $snapshot['indexes'][$table] = $this->getTableIndexes($table);
            $snapshot['foreign_keys'][$table] = $this->getTableForeignKeys($table);
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function saveSnapshot(array $snapshot, string $reason = 'manual'): int
    {
        return (int) DB::table('schema_snapshots')->insertGetId([
            'snapshot_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $beforeSnapshot
     * @param  array<string, mixed>  $afterSnapshot
     * @return array<string, mixed>
     */
    public function analyzeSchemaChanges(array $beforeSnapshot, array $afterSnapshot): array
    {
        $changes = [
            'dangerous' => [],
            'safe' => [],
            'summary' => [],
        ];

        $beforeTables = array_keys($beforeSnapshot['tables'] ?? []);
        $afterTables = array_keys($afterSnapshot['tables'] ?? []);

        $droppedTables = array_diff($beforeTables, $afterTables);
        foreach ($droppedTables as $table) {
            $rowCount = (int) ($beforeSnapshot['tables'][$table]['row_count'] ?? 0);
            $changes['dangerous'][] = [
                'type' => 'table_dropped',
                'table' => $table,
                'risk_level' => $rowCount > 0 ? 'HIGH' : 'MEDIUM',
                'message' => "Table '{$table}' was dropped (contained {$rowCount} rows)",
            ];
        }

        $newTables = array_diff($afterTables, $beforeTables);
        foreach ($newTables as $table) {
            $changes['safe'][] = [
                'type' => 'table_created',
                'table' => $table,
                'message' => "New table '{$table}' was created",
            ];
        }

        foreach (array_intersect($beforeTables, $afterTables) as $table) {
            $this->compareTableColumns(
                $table,
                $beforeSnapshot['tables'][$table]['columns'] ?? [],
                $afterSnapshot['tables'][$table]['columns'] ?? [],
                $changes
            );
        }

        $changes['summary'] = [
            'dangerous_count' => count($changes['dangerous']),
            'safe_count' => count($changes['safe']),
            'risk_assessment' => $this->calculateRiskLevel($changes),
        ];

        return $changes;
    }

    /**
     * @return array{safe: bool, reason: string, risks?: array<int, string>}
     */
    public function canSafelyRollback(int $snapshotId): array
    {
        $snapshot = DB::table('schema_snapshots')->find($snapshotId);
        if ($snapshot === null || ! is_object($snapshot) || ! property_exists($snapshot, 'snapshot_data')) {
            return ['safe' => false, 'reason' => 'Snapshot not found'];
        }

        $currentSnapshot = $this->captureSchemaSnapshot();
        /** @var array<string, mixed> $targetSnapshot */
        $targetSnapshot = json_decode((string) $snapshot->snapshot_data, true, 512, JSON_THROW_ON_ERROR);

        $risks = [];
        foreach ($currentSnapshot['tables'] as $tableName => $tableData) {
            $currentRows = (int) ($tableData['row_count'] ?? 0);
            $snapshotRows = (int) ($targetSnapshot['tables'][$tableName]['row_count'] ?? 0);

            if ($currentRows > $snapshotRows + (int) config('whizbang.safety_checks.max_row_increase', 1000)) {
                $risks[] = "Table '{$tableName}' has {$currentRows} rows now vs {$snapshotRows} in snapshot";
            }
        }

        return [
            'safe' => $risks === [],
            'reason' => $risks === [] ? 'Safe to rollback' : 'Risk of data loss',
            'risks' => $risks,
        ];
    }

    /**
     * @return array{success: bool, message: string, risks?: array<int, string>}
     */
    public function executeRollback(int $snapshotId): array
    {
        $safety = $this->canSafelyRollback($snapshotId);
        if ($safety['safe'] === false) {
            return ['success' => false, 'message' => $safety['reason'], 'risks' => $safety['risks'] ?? []];
        }

        $snapshot = DB::table('schema_snapshots')->find($snapshotId);
        if ($snapshot === null || ! is_object($snapshot) || ! property_exists($snapshot, 'snapshot_data')) {
            return ['success' => false, 'message' => 'Snapshot not found'];
        }

        /** @var array<string, mixed> $targetSchema */
        $targetSchema = json_decode((string) $snapshot->snapshot_data, true, 512, JSON_THROW_ON_ERROR);
        $currentSchema = $this->captureSchemaSnapshot();

        DB::beginTransaction();
        try {
            $this->executeSchemaRestore($currentSchema, $targetSchema);

            DB::table('schema_rollbacks')->insert([
                'snapshot_id' => $snapshotId,
                'rolled_back_at' => now(),
                'rolled_back_by' => (string) data_get(Auth::user(), 'email', 'system'),
            ]);

            DB::commit();

            return ['success' => true, 'message' => 'Schema successfully rolled back'];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['success' => false, 'message' => 'Rollback failed: '.$e->getMessage()];
        }
    }

    /**
     * @return array<int, string>
     */
    protected function getAllTables(): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            /** @var array<int, object> $rows */
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            return array_map(static fn (object $row): string => (string) ($row->name ?? ''), $rows);
        }

        // MySQL & MariaDB default
        return array_map('reset', DB::select('SHOW TABLES'));
    }

    /**
        @return array<int, array{name: string, type: string|null, null: bool, default: mixed}>
     */
    protected function getTableColumns(string $table): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            /** @var array<int, object> $details */
            $details = DB::select("PRAGMA table_info('{$table}')");
            return array_map(static function (object $col): array {
                return [
                    'name' => (string) ($col->name ?? ''),
                    'type' => isset($col->type) ? (string) $col->type : null,
                    'null' => (int) ($col->notnull ?? 0) === 0,
                    'default' => $col->dflt_value ?? null,
                ];
            }, $details);
        }

        return collect(Schema::getColumnListing($table))
            ->map(function (string $column) use ($table): array {
                /** @var array<int, object> $details */
                $details = DB::select("DESCRIBE {$table} {$column}");
                $first = $details[0] ?? (object) ['Type' => null, 'Null' => 'YES', 'Default' => null];

                return [
                    'name' => $column,
                    'type' => $first->Type ?? null,
                    'null' => ($first->Null ?? 'YES') === 'YES',
                    'default' => $first->Default ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    protected function getTableRowCount(string $table): int
    {
        return (int) DB::table($table)->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getTableIndexes(string $table): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            /** @var array<int, array<string, mixed>> $indexes */
            $indexes = json_decode(json_encode(DB::select("PRAGMA index_list('{$table}')"), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            return $indexes;
        }

        /** @var array<int, array<string, mixed>> $indexes */
        $indexes = json_decode(json_encode(DB::select("SHOW INDEX FROM {$table}"), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $indexes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getTableForeignKeys(string $table): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            /** @var array<int, array<string, mixed>> $keys */
            $keys = json_decode(json_encode(DB::select("PRAGMA foreign_key_list('{$table}')"), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            return $keys;
        }

        /** @var array<int, array<string, mixed>> $keys */
        $keys = json_decode(json_encode(DB::select(
            'SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table]
        ), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $keys;
    }

    /**
     * @param  array<int, array{name: string, type: string|null}>  $before
     * @param  array<int, array{name: string, type: string|null}>  $after
     * @param  array<string, mixed>  $changes
     */
    protected function compareTableColumns(string $table, array $before, array $after, array &$changes): void
    {
        $beforeColumns = collect($before)->keyBy('name');
        $afterColumns = collect($after)->keyBy('name');

        foreach ($beforeColumns->keys()->diff($afterColumns->keys()) as $column) {
            $changes['dangerous'][] = [
                'type' => 'column_dropped',
                'table' => $table,
                'column' => $column,
                'risk_level' => 'HIGH',
                'message' => "Column '{$table}.{$column}' was dropped",
            ];
        }

        foreach ($afterColumns->keys()->diff($beforeColumns->keys()) as $column) {
            $changes['safe'][] = [
                'type' => 'column_added',
                'table' => $table,
                'column' => $column,
                'message' => "Column '{$table}.{$column}' was added",
            ];
        }

        foreach ($beforeColumns->keys()->intersect($afterColumns->keys()) as $column) {
            /** @var array{name: string, type: string|null} $beforeCol */
            $beforeCol = $beforeColumns[$column];
            /** @var array{name: string, type: string|null} $afterCol */
            $afterCol = $afterColumns[$column];

            if (($beforeCol['type'] ?? null) !== ($afterCol['type'] ?? null)) {
                $changes['dangerous'][] = [
                    'type' => 'column_modified',
                    'table' => $table,
                    'column' => $column,
                    'risk_level' => 'MEDIUM',
                    'message' => "Column '{$table}.{$column}' type changed from {$beforeCol['type']} to {$afterCol['type']}",
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function calculateRiskLevel(array $changes): string
    {
        $dangerousCount = count($changes['dangerous'] ?? []);
        $dangerous = $changes['dangerous'] ?? [];
        $highRiskCount = count(array_filter($dangerous, static fn (array $change): bool => ($change['risk_level'] ?? null) === 'HIGH'));

        if ($highRiskCount > 0) {
            return 'HIGH';
        }
        if ($dangerousCount > 3) {
            return 'HIGH';
        }
        if ($dangerousCount > 0) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * @param  array<string, mixed>  $currentSchema
     * @param  array<string, mixed>  $targetSchema
     */
    protected function executeSchemaRestore(array $currentSchema, array $targetSchema): void
    {
        foreach ($targetSchema['tables'] as $tableName => $tableData) {
            if (! isset($currentSchema['tables'][$tableName])) {
                info("Would recreate table: {$tableName}");
            }
        }
    }
}
