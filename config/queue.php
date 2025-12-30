<?php

return [

    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => env('QUEUE_TABLE', 'jobs'),
            'queue' => env('QUEUE_NAME', 'default'),
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_HOST', '127.0.0.1'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE', 'default'),
            'queue' => env('REDIS_QUEUE_NAME', 'default'),
            'retry_after' => (int) env('REDIS_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'orders'),
        'table' => env('QUEUE_BATCH_TABLE', 'job_batches'),
        'cache_store' => env('QUEUE_CACHE_STORE', null),
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'orders'),
        'table' => env('QUEUE_FAILED_TABLE', 'failed_jobs'),
    ],

];
