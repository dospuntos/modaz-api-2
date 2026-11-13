<?php
date_default_timezone_set('UTC');
// Get configuration

$projectRoot = dirname(__DIR__);

$localConfig = $projectRoot . '/config/config.local.php';

$documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');

$serverConfig = $documentRoot !== ''
    ? dirname($documentRoot) . '/config/config.php'
    : null;

// Prefer local configuration, otherwise use server configuration
if (is_file($localConfig)) {
    $configFile = $localConfig;
} else {
    $configFile = $serverConfig;
}

if (!$configFile || !is_readable($configFile)) {
    throw new RuntimeException('Configuration file not found');
}

$config = require $configFile;

if (!is_array($config)) {
    throw new RuntimeException('Invalid configuration file');
}

class DB
{
    private static $writeDBConnection = null;
    private static $readDBConnection = null;

    private static function createConnection()
    {
        global $config;

        $prefix = $config['db_prefix'];

        if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) {
            throw new RuntimeException('Invalid database prefix');
        }

        $dsn = 'mysql:host=' . $config['db_host']
            . ';dbname=' . $config['db_name']
            . ';charset=utf8mb4';

        $db = new PDO(
            $dsn,
            $config['db_user'],
            $config['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        $db->exec("SET time_zone = '+00:00'");

        // Table names
        $db->tblusers = $prefix . 'modaz_users';
        $db->tblsessions = $prefix . 'modaz_sessions';
        $db->tblproducts = $prefix . 'modaz_products';
        $db->tblproductvariants = $prefix . 'modaz_product_variants';
        $db->tblcategories = $prefix . 'categories';
        $db->tblproductcategories = $prefix . 'product_categories';

        // Image directory
        $db->img_folder = rtrim($config['image_path'], '/\\')
            . DIRECTORY_SEPARATOR;

        return $db;
    }

    public static function connectWriteDB()
    {
        if (self::$writeDBConnection === null) {
            self::$writeDBConnection = self::createConnection();
        }

        return self::$writeDBConnection;
    }

    public static function connectReadDB()
    {
        if (self::$readDBConnection === null) {
            self::$readDBConnection = self::createConnection();
        }

        return self::$readDBConnection;
    }
}
