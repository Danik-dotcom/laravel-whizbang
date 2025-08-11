<?php

declare(strict_types=1);

namespace Ludovicguenet\Whizbang\Listeners;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\DB;
use Ludovicguenet\Whizbang\Whizbang;

class SchemaChangeTracker
{
    protected Whizbang $guardian;

    /** @var array<string, mixed> */
    protected array $beforeSnapshot = [];

    public function __construct(Whizbang $guardian)
    {
        $this->guardian = $guardian;
    }

    public function beforeMigration(MigrationsStarted $event): void
    {
        echo "\n🛡️  Whizbang: Taking pre-migration snapshot...\n";

        $this->beforeSnapshot = $this->guardian->captureSchemaSnapshot();
        $snapshotId = $this->guardian->saveSnapshot($this->beforeSnapshot, 'pre_migration');

        echo "📸 Pre-migration snapshot saved (ID: {$snapshotId})\n";
    }

    public function afterMigration(MigrationsEnded $event): void
    {
        echo "\n🛡️  Whizbang: Analyzing schema changes...\n";

        $afterSnapshot = $this->guardian->captureSchemaSnapshot();
        $changes = $this->guardian->analyzeSchemaChanges($this->beforeSnapshot, $afterSnapshot);

        DB::table('schema_changes')->insert([
            'changes_data' => json_encode($changes, JSON_THROW_ON_ERROR),
            'risk_level' => $changes['summary']['risk_assessment'],
            'dangerous_count' => $changes['summary']['dangerous_count'],
            'created_at' => now(),
        ]);

        if (($changes['summary']['dangerous_count'] ?? 0) > 0) {
            echo "⚠️  DANGEROUS CHANGES DETECTED!\n";
            foreach ($changes['dangerous'] as $change) {
                echo '❌ '.$change['message'].' (Risk: '.($change['risk_level'] ?? 'UNKNOWN').")\n";
            }
            echo "\n🔄 To rollback, use: php artisan schema:rollback [snapshot_id]\n";
        } else {
            echo "✅ All changes appear safe\n";
        }

        echo '📊 Summary: '.$changes['summary']['dangerous_count'].' dangerous, '.$changes['summary']['safe_count']." safe changes\n";
        echo '🎯 Risk Level: '.$changes['summary']['risk_assessment']."\n\n";
    }
}
