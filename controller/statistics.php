
<?php

require_once('db.php');
require_once('../model/Statistics.php');
require_once('../model/Response.php');
require_once('../functions.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

// Authentication is required
$userId = checkAuthStatusAndReturnUserID($writeDB);

if (!$userId) {
    sendResponse(401, false, "User not authorized or not logged in.");
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    try {
        $statistics = new Statistics($readDB);
        $returnData = $statistics->getStatistics();

        sendResponse(200, true, null, true, $returnData);
    } catch (StatisticsException $ex) {
        error_log("Statistics error - " . $ex, 0);
        sendResponse(500, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log("Database query error - " . $ex, 0);
        sendResponse(500, false, "Failed to get statistics");
    }
} else {
    sendResponse(405, false, "Request method not allowed");
}
