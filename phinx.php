<?php

// --- Load Environment/Configuration ---
// TODO: IMPORTANT! Replace this section with code to load your application's
//       configuration, especially database credentials.
// Example using a hypothetical config file:
// require_once __DIR__ . '/src/bootstrap.php'; // Or wherever your config is loaded
// $config = getConfigArray(); // Replace with your function/method to get config

// --- !! Placeholder Credentials - REPLACE THESE !! ---
$dbConfig = [
    'host' => 'localhost',
    'name' => 'your_database_name',
    'user' => 'your_database_user',
    'pass' => 'your_database_password',
    'port' => 3306, // Or your database port
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
// --- End Placeholder Credentials ---


// --- Phinx Configuration ---
return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog', // Name of the migration tracking table
        'default_environment' => 'production', // Or 'development', 'testing'
        'production' => [
            'adapter' => 'mysql', // Or 'pgsql', 'sqlite', 'sqlsrv'
            'host' => $dbConfig['host'],
            'name' => $dbConfig['name'],
            'user' => $dbConfig['user'],
            'pass' => $dbConfig['pass'],
            'port' => $dbConfig['port'],
            'charset' => $dbConfig['charset'],
            'collation' => $dbConfig['collation'],
        ],
        // You can define other environments like development or testing here
        // 'development' => [
        //     'adapter' => 'mysql',
        //     'host' => 'localhost',
        //     'name' => 'development_db',
        //     'user' => 'dev_user',
        //     'pass' => 'dev_pass',
        //     'port' => 3306,
        //     'charset' => 'utf8mb4',
        // ]
    ],
    'version_order' => 'creation' // Order migrations by creation time
]; 