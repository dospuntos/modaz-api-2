<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../functions.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

// Check authentication
//$userId = checkAuthStatusAndReturnUserID($writeDB);
$userId = 1; // Bypass authentication for testing
if (array_key_exists("missing", $_GET)) { // GET/PATCH category by ID

    $categoryid = $_GET['categoryid'];

    if ($categoryid === '' || !is_numeric($categoryid)) sendResponse(400, false, "Category ID cannot be blank or must be numeric");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Return category by ID

        try {
            $query = $readDB->prepare("SELECT id, title, path FROM $readDB->tblcategories WHERE id LIKE :categoryid");

            $query->bindParam(':categoryid', $categoryid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "Category not found");
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $category = new Category($row['id'], $row['title'], $row['path']);
                $categoryArray[] = $category->returnCategoryAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = count($categoryArray);
            $returnData['categories'] = $categoryArray;

            sendResponse(200, true, null, true, $returnData);
        } catch (TaskException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get Category");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') { // DELETE CATEGORY

        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");

        $messages = [];
        // Logged in user, attempt to remove item
        try {
            $query = $writeDB->prepare("DELETE FROM $readDB->tblcategories WHERE id = :categoryid");
            $query->bindParam(':categoryid', $categoryid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(404, false, "Category not found");

            $messages[] = "Category ID " . $categoryid . " deleted";
        } catch (PDOException $ex) {
            sendResponse(500, false, "Failed to delete category");
        }

        sendResponse(200, true, $messages);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') { // EDIT CATEGORY
        sendResponse(200, true, "Category PATCH currently not implemented, but the request was successful for category ID " . $categoryid);
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (empty($_GET)) { // Get all images

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare("SELECT images FROM $readDB->tblproducts");
            $query->execute();

            $imagesArray = array();

            $rowCount = $query->rowCount();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                /* $categories = new Category($row['id'], $row['title'], $row['path']);
                $categoriesArray[] = $categories->returnCategoryAsArray();*/
                $imagesArray[] = $row;
            }

            $returnData['rows_returned'] = $rowCount;

            $returnData['images'] = $imagesArray;
            sendResponse(200, true, $userId ? null : "Request by Anonymous user", true, $returnData);
        } catch (TaskException $ex) {
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
