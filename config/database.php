<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'core'),

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'core' => [
            'driver' => 'pgsql',
            'url' => env('CORE_DB_URL'),
            'host' => env('DB_CORE_HOST', env('DB_HOST', 'db')),
            'port' => env('DB_CORE_PORT', env('DB_PORT', '5432')),
            'database' => env('DB_CORE_DATABASE', env('DB_DATABASE', 'imdc_core')),
            'username' => env('DB_CORE_USERNAME', env('DB_USERNAME', 'postgres')),
            'password' => env('DB_CORE_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('CORE_DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('CORE_DB_SCHEMA', 'public'),
            'sslmode' => env('CORE_DB_SSLMODE', 'prefer'),
        ],

        'orders' => [
            'driver' => 'pgsql',
            'url' => env('ORDERS_DB_URL'),
            'host' => env('DB_ORDERS_HOST', env('DB_HOST', 'db')),
            'port' => env('DB_ORDERS_PORT', env('DB_PORT', '5432')),
            'database' => env('DB_ORDERS_DATABASE', env('DB_DATABASE', 'imdc_orders')),
            'username' => env('DB_ORDERS_USERNAME', env('DB_USERNAME', 'postgres')),
            'password' => env('DB_ORDERS_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('ORDERS_DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('ORDERS_DB_SCHEMA', 'public'),
            'sslmode' => env('ORDERS_DB_SSLMODE', 'prefer'),
        ],

        'products' => [
            'driver' => 'pgsql',
            'url' => env('PRODUCTS_DB_URL'),
            'host' => env('DB_PRODUCTS_HOST', env('DB_HOST', 'db')),
            'port' => env('DB_PRODUCTS_PORT', env('DB_PORT', '5432')),
            'database' => env('DB_PRODUCTS_DATABASE', env('DB_DATABASE', 'imdc_products')),
            'username' => env('DB_PRODUCTS_USERNAME', env('DB_USERNAME', 'postgres')),
            'password' => env('DB_PRODUCTS_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('PRODUCTS_DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('PRODUCTS_DB_SCHEMA', 'public'),
            'sslmode' => env('PRODUCTS_DB_SSLMODE', 'prefer'),
        ],

        'inventory' => [
            'driver' => 'pgsql',
            'url' => env('INVENTORY_DB_URL'),
            'host' => env('DB_INVENTORY_HOST', env('DB_HOST', 'db')),
            'port' => env('DB_INVENTORY_PORT', env('DB_PORT', '5432')),
            'database' => env('DB_INVENTORY_DATABASE', env('DB_DATABASE', 'imdc_inventory')),
            'username' => env('DB_INVENTORY_USERNAME', env('DB_USERNAME', 'postgres')),
            'password' => env('DB_INVENTORY_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('INVENTORY_DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('INVENTORY_DB_SCHEMA', 'public'),
            'sslmode' => env('INVENTORY_DB_SSLMODE', 'prefer'),
        ],

        'nfts' => [
            'driver' => 'pgsql',
            'url' => env('NFTS_DB_URL'),
            'host' => env('DB_NFTS_HOST', env('DB_HOST', 'db')),
            'port' => env('DB_NFTS_PORT', env('DB_PORT', '5432')),
            'database' => env('DB_NFTS_DATABASE', env('DB_DATABASE', 'imdc_nfts')),
            'username' => env('DB_NFTS_USERNAME', env('DB_USERNAME', 'postgres')),
            'password' => env('DB_NFTS_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('NFTS_DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('NFTS_DB_SCHEMA', 'public'),
            'sslmode' => env('NFTS_DB_SSLMODE', 'prefer'),
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
