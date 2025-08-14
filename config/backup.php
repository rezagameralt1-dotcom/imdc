<?php
return [
    'dir' => env('BACKUP_DIR', storage_path('app/backups')),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'log_retention_days' => (int) env('LOG_RETENTION_DAYS', 30),
];
