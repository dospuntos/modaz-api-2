<?php

class DB
{
    private static $writeDBConnection;
    private static $readDBConnection;
    private static $productDBConnection;

    public static function connectWriteDB()
    {
        if (self::$writeDBConnection === null) {
            self::$writeDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', '');
            self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$writeDBConnection;
    }

    public static function connectReadDB()
    {
        if (self::$readDBConnection === null) {
            self::$readDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', '');
            self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$readDBConnection;
    }

    public static function connectProductDB()
    {
        if (self::$productDBConnection === null) {
            self::$productDBConnection = new PDO('mysql:host=localhost;dbname=modazv2;charset=utf8', 'root', '');
            self::$productDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$productDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$productDBConnection;
    }
}
