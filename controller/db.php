<?php

// Get Joomla configuration
$parentparentdir = basename(dirname(dirname(__FILE__)));
if ($parentparentdir === "2") { // Live API v2 - include config file from Joomla
    include_once('../../../configuration.php');
} elseif ($parentparentdir === "2s") { // Staging API v2 on server, include config file for staging site
    include_once('../../../staging/configuration.php');
} else {
    include_once('../configuration.php');
}

class DB
{
    private static $writeDBConnection;
    private static $readDBConnection;

    public static function connectWriteDB()
    {
        $var_cls = new JConfig(); // object of the class
        if (self::$writeDBConnection === null) {
            self::$writeDBConnection = new PDO('mysql:host=' . $var_cls->host . ';dbname=' . $var_cls->db . ';charset=utf8', $var_cls->user, $var_cls->password);
            self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // set table names
            self::$writeDBConnection->tblusers = $var_cls->dbprefix . "modaz_users";
            self::$writeDBConnection->tblsessions = $var_cls->dbprefix . "modaz_sessions";
            self::$writeDBConnection->tblproducts = $var_cls->dbprefix . "modaz_products_api2";
            self::$writeDBConnection->tblproductvariants = $var_cls->dbprefix . "modaz_product_variants";
            self::$writeDBConnection->tblcategories = $var_cls->dbprefix . "categories";
        }
        return self::$writeDBConnection;
    }

    public static function connectReadDB()
    {
        $var_cls = new JConfig(); // object of the class
        if (self::$readDBConnection === null) {
            self::$readDBConnection = new PDO('mysql:host=' . $var_cls->host . ';dbname=' . $var_cls->db . ';charset=utf8', $var_cls->user, $var_cls->password);
            self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // set table names
            self::$readDBConnection->tblusers = $var_cls->dbprefix . "modaz_users";
            self::$readDBConnection->tblsessions = $var_cls->dbprefix . "modaz_sessions";
            self::$readDBConnection->tblproducts = $var_cls->dbprefix . "modaz_products_api2";
            self::$readDBConnection->tblproductvariants = $var_cls->dbprefix . "modaz_product_variants";
            self::$readDBConnection->tblcategories = $var_cls->dbprefix . "categories";
        }
        return self::$readDBConnection;
    }
}
