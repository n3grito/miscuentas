<?php

return [

    'backup' => [

        'name' => env('APP_NAME', 'MisCuentas'),

        'source' => [

            'files' => [
                'include' => [
                    base_path(),
                ],

                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('storage/logs'),
                    base_path('storage/framework/cache'),
                    base_path('storage/framework/sessions'),
                    base_path('.git'),
                ],

                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => null,
            ],

            'databases' => [
                'mysql' => [
                    'dump' => [
                        'exclude_tables' => [],
                        'useSingleTransaction' => true,
                        'timeout' => 60,
                    ],
                ],
            ],
        ],

        'destination' => [
            'filename_prefix' => 'miscuentas_',
            'disk' => 'local',
            'directory' => 'backups',
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'notifications' => [
            'mail' => [
                'to' => env('BACKUP_NOTIFY_EMAIL', env('MAIL_FROM_ADDRESS', 'admin@tallerssh.cu')),
            ],

            'slack' => [
                'webhook_url' => env('BACKUP_SLACK_WEBHOOK', ''),
            ],

            'notification' => [
                'using' => \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class,
            ],

            '_failed' => [
                'using' => \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class,
            ],
        ],
    ],

    'notifications' => [
        'channels' => ['mail'],
        'failed' => [
            'using' => \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class,
        ],
        'success' => [
            'using' => \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class,
        ],
        'warning' => [
            'using' => \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class,
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default' => [
            'keep_all_backups_for_days' => 30,
            'keep_daily_backups_for_days' => 14,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 6,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],

    'monitor' => [
        'backups' => [
            [
                'name' => 'mis-cuentas-backup',
                'disks' => ['local'],
                'health_checks' => [\Spatie\Backup\Tasks\Monitor\HealthCheck\DefaultHealthCheck::class],
            ],
        ],
    ],

    'scheduler' => [
        \Spatie\Backup\Tasks\Cleanup\Schedule::class => [
            'frequency' => 'daily',
            'at' => '02:00',
        ],
    ],
];