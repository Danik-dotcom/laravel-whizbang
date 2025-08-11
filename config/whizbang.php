<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('WHIZBANG_ENABLED', true),

    'auto_snapshot' => [
        'before_migration' => (bool) env('WHIZBANG_AUTO_SNAPSHOT', true),
        'retention_days' => (int) env('WHIZBANG_RETENTION_DAYS', 30),
    ],

    'safety_checks' => [
        'max_row_increase' => 1000,
        'require_confirmation' => (bool) env('WHIZBANG_REQUIRE_CONFIRMATION', true),
    ],

    'notifications' => [
        'dangerous_changes' => (bool) env('WHIZBANG_NOTIFY_DANGEROUS', true),
        'rollbacks' => (bool) env('WHIZBANG_NOTIFY_ROLLBACKS', true),
        'channels' => ['mail'],
    ],
];
