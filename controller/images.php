<?php

require_once('db.php');
require_once('../model/Images.php');
require_once('../model/Response.php');
require_once('../functions.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

// Check authentication - disabled for testing
$userId = checkAuthStatusAndReturnUserID($writeDB);

if (array_key_exists("fix", $_GET)) { // FIX errors in JSON data
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        sendResponse(200, true, "Method to fix image errors");
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (empty($_GET) || array_key_exists("check", $_GET)) { // Get all images

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $check = (array_key_exists("check", $_GET)) ? true : false;

        try {
            $query = $readDB->prepare("SELECT id, images FROM $readDB->tblproducts");
            $query->execute();

            $imagesArray = array();

            $rowCount = $query->rowCount();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $images = new Images($row['id'], $row['images'], $check);
                $imagesArray[] = $images->returnImageAsArray();
            }

            $returnData['rows_returned'] = $rowCount;

            $returnData['images'] = $imagesArray;
            sendResponse(200, true, $userId ? null : "Request by Anonymous user", true, $returnData);
        } catch (ImagesException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get images");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}
