<?php

require_once('db.php');
require_once('../model/Category.php');
require_once('../model/Response.php');
require_once('../functions.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

// NO auth script - open to public but some functions are restricted

// Check authentication to return some additional data in query and allow editing
$userId = simpleCheckAuthStatusAndReturnUserID($writeDB);

if (array_key_exists("category", $_GET)) { // GET/PATCH category by ID

    $categoryid = $_GET['category'];

    if ($categoryid === '' || !is_numeric($categoryid)) {
        sendResponse(400, false, "Category ID cannot be blank or must be numeric");
    }

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
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') { // DELETE PRODUCT

        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");

        $messages = [];
        // Logged in user, attempt to remove item
        try {
            $query = $writeDB->prepare("DELETE FROM $readDB->tblproducts WHERE id = :productid");
            $query->bindParam(':productid', $productid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(404, false, "Product not found");

            $messages[] = "Product ID " . $productid . " deleted";
        } catch (PDOException $ex) {
            sendResponse(500, false, "Failed to delete product");
        }

        // Also delete variants
        try {
            $query = $writeDB->prepare("DELETE FROM $readDB->tblproductvariants WHERE product_id = :productid");
            $query->bindParam(':productid', $productid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            $messages[] = $rowCount . " variant(s) deleted";
        } catch (PDOException $ex) {
            sendResponse(500, false, "Failed to delete product");
        }
        sendResponse(200, true, $messages);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') { // EDIT PRODUCT
        sendResponse(200, true, "Product PATCH currently not implemented, but the request was successful for product ID " . $productid);
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (empty($_GET)) { // Get all categories or create category

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare("SELECT id, title, path FROM $readDB->tblcategories");
            $query->execute();

            $categoriesArray = array();

            $rowCount = $query->rowCount();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $categories = new Category($row['id'], $row['title'], $row['path']);
                $categoriesArray[] = $categories->returnCategoryAsArray();
            }

            $returnData['rows_returned'] = $rowCount;

            $returnData['categories'] = $categoriesArray;
            sendResponse(200, true, $userId ? null : "Request by Anonymous user", true, $returnData);
            exit;
        } catch (TaskException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get categories");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { // Create new category

        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");

        try {
            if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') sendResponse(400, false, "Content type header is not set to JSON");

            $rawPOSTData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPOSTData, true)) sendResponse(400, false, "Request body is not valid JSON");

            // Required: Title
            if (!isset($jsonData['title'])) sendResponse(400, false, "Title field is mandatory and must be provided");

            // Create the category
            $title = $jsonData['title'];

            $query = $writeDB->prepare("INSERT INTO $readDB->tblcategories (title, path) VALUES (:title, :path)");
            $query->bindParam(':state', $state, PDO::PARAM_INT);
            $query->bindParam(':release_date', $release_date, PDO::PARAM_STR);

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(500, false, "Failed to create category");

            $lastProductID = $writeDB->lastInsertId();

            $query = $readDB->prepare("SELECT id, title, path FROM $readDB->tblcategories WHERE id LIKE :categoryid");
            $query->bindParam(':categoryid', $lastProductID, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(500, false, "Failed to retrieve category after creation");

            $categoryArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $category = new Category($row['id'], $row['title'], $row['path']);
                $categoryArray[] = $category->returnCategoryAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['categories'] = $categoryArray;

            sendResponse(201, true, "Category created", false, $returnData);
        } catch (TaskException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            sendResponse(500, false, "Failed to insert category into database - check submitted data for errors");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}
