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
        try {
            $query = $readDB->prepare("SELECT id, images FROM $readDB->tblproducts");
            $query->execute();

            $imagesArray = array();

            $rowCount = $query->rowCount();
            $fixCount = 0;
            $messages = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                if (!$imgArray = json_decode($row['images'], true)) {
                    // Error with JSON data
                    $img = "";
                    // Set default value if NULL
                    if ($row['images'] === NULL || $row['images'] === "") { // NULL or empty
                        $img = array(
                            "image" => "default.php",
                            "color" => "bold-black"
                        );
                    } elseif (preg_match("/^[0a-zA-Z]/", $row['images'])) { // simple image string (no array)
                        $img = array(
                            "image" => $row['images'],
                            "color" => "bold-black"
                        );
                    }

                    // Update image in database
                    if (updateImageInDB($writeDB, $row['id'], json_encode(array($img)))) {
                        $messages[] = "Updated image for ID " . $row['id'] . " from '" . $row['images'] . "' to '" . json_encode(array($img)) . "'";
                        $fixCount++;
                    } else {
                        $messages[] =  "Error updating image for ID " . $row['id'] . " from '" . $row['images'] . "' to '" . json_encode(array($img)) . "'";
                    }
                }
            }

            $returnData['images_fixes'] = $fixCount;
            sendResponse(200, true, $messages, false, $returnData);
        } catch (ImagesException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to fix images");
        }
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
