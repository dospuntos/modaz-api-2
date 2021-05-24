<?php

require_once('db.php');
require_once('../model/Product.php');
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

if (array_key_exists("productid", $_GET)) { // GET/POST/PATCH product by ID
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Return product by ID
        $productid = $_GET['productid'];

        if ($productid === '' || !is_numeric($productid)) {
            sendResponse(400, false, "Product ID cannot be blank or must be numeric");
        }

        try {
            $query = $readDB->prepare("SELECT p.id, p.name, p.state, p.description, p.images, p.category, p.featured, p.orderdate, p.release_date, p.season, p.wholesaleprice, p.msrp, p.price, p.zinprice, p.price_discount, p.weight, p.composition, p.manufacturer, p.country, v.id AS vid, v.product_id, v.upc, v.size, v.color, v.stock FROM $readDB->tblproducts p, $readDB->tblproductvariants v WHERE p.id LIKE :productid AND v.product_id LIKE :productid2 AND p.state LIKE 1");

            $query->bindParam(':productid', $productid, PDO::PARAM_INT);
            $query->bindParam(':productid2', $productid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "Product not found");
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($userId, $row);
                $productArray[] = $product->returnProductAsArray();
            }

            $returnData = array();
            //$returnData['rows_returned'] = $rowCount; // This returns rowCount before joining variants by product name
            $returnData['rows_returned'] = count($productArray);
            $returnData['products'] = $productArray;

            sendResponse(200, true, null, true, $returnData);
        } catch (TaskException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get Product");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') { // DELETE PRODUCT - REMEMBER TO REMOVE VARIANTS AS WELL

        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");

        $productid = $_GET['productid'];

        if ($productid === '' || !is_numeric($productid)) {
            sendResponse(400, false, "Product ID cannot be blank or must be numeric");
        }

        $messages = [];
        // Logged in user, attempt to remove item
        try {
            $query = $writeDB->prepare("DELETE FROM $readDB->tblproducts WHERE id = :productid");
            $query->bindParam(':productid', $productid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(404, false, "Product not found");

            $messages[] = "Product ID " . $productid . " deleted";

            /* $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Task deleted");
            $response->send();
            exit; */
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
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (array_key_exists("published", $_GET)) { // Return published/unpublised products

    $published = $_GET['published'];

    if ($published !== '1' && $published !== '0') {
        sendResponse(400, false, "Published filter must be 1 or 0");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {
            $query = $readDB->prepare("SELECT p.id, p.name, p.state, p.description, p.images, p.category, p.featured, p.orderdate, p.release_date, p.season, p.wholesaleprice, p.msrp, p.price, p.zinprice, p.price_discount, p.weight, p.composition, p.manufacturer, p.country, v.id AS vid, v.product_id, v.upc, v.size, v.color, v.stock FROM $readDB->tblproducts p, $readDB->tblproductvariants v WHERE p.id = v.product_id AND p.state =:published ORDER BY p.name ASC");
            $query->bindParam(':published', $published, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            $productsArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($userId, $row);
                $productsArray[] = $product->returnProductAsArray();
            }
            $returnData['rows_returned'] = $rowCount;
            //$returnData['products'] = $productsArray;
            sendResponse(200, true, null, true, $returnData);
        } catch (TaskException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get products");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (array_key_exists("page", $_GET)) { // Return pagination
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $page = $_GET['page'];

        if ($page == '' || !is_numeric($page)) {
            sendResponse(404, false, "Page number cannot be blank and must be numeric");
        }

        $limitPerPage = 20;

        try {
            $query = $readDB->prepare("SELECT COUNT(id) as totalNoOfProducts from $readDB->tblproducts");
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $productsCount = intval($row['totalNoOfProducts']);

            $numOfPages = ceil($productsCount / $limitPerPage);
            if ($numOfPages == 0) { // At least a single page
                $numOfPages = 1;
            }

            if ($page > $numOfPages || $page == 0) {
                sendResponse(404, false, "Page not found");
            }

            $offset = ($page == 1 ? 0 : ($limitPerPage * ($page - 1)));

            $query = $readDB->prepare("SELECT p.id, p.name, p.state, p.description, p.images, p.category, p.featured, p.orderdate, p.release_date, p.season, p.wholesaleprice, p.msrp, p.price, p.zinprice, p.price_discount, p.weight, p.composition, p.manufacturer, p.country, v.id AS vid, v.product_id, v.upc, v.size, v.color, v.stock FROM $readDB->tblproducts p, $readDB->tblproductvariants v WHERE p.id = v.product_id ORDER BY p.name ASC LIMIT :pglimit OFFSET :offset");

            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            $productsArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($userId, $row);
                $productsArray[] = $product->returnProductAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $productsCount;
            $returnData['total_pages'] = $numOfPages;
            ($page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
            ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
            $returnData['products'] = joinProductsById($productsArray);

            sendResponse(200, true, null, true, $returnData);
        } catch (TaskException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get products");
        }
    } else {
        sendResponse(404, false, "Request method not allowed");
    }
} elseif (empty($_GET)) { // Get all products

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare("SELECT p.id, p.name, p.state, p.description, p.images, p.category, p.featured, p.orderdate, p.release_date, p.season, p.wholesaleprice, p.msrp, p.price, p.zinprice, p.price_discount, p.weight, p.composition, p.manufacturer, p.country, v.id AS vid, v.product_id, v.upc, v.size, v.color, v.stock FROM $readDB->tblproducts p, $readDB->tblproductvariants v WHERE p.id = v.product_id ORDER BY p.name ASC");
            $query->execute();

            $productsArray = array();

            $rowCount = $query->rowCount();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // Skip if no userId (not logged in) and product is unpublished
                if (!$userId && !$row['state']) continue;

                $product = new Product($userId, $row);
                $productsArray[] = $product->returnProductAsArray();
            }

            $joinedProducts = joinProductsById($productsArray);

            $returnData = array();
            //$returnData['rows_returned'] = $rowCount;

            $returnData['rows_returned'] = count($joinedProducts);
            $returnData['products'] = $joinedProducts;
            sendResponse(200, true, $userId ? null : "Request by Anonymous user", true, $returnData);
            exit;
        } catch (TaskException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get products");
        }
    } /* elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') { // DELETE PRODUCT - REMEMBER TO REMOVE VARIANTS AS WELL

        try {
            $query = $writeDB->prepare('DELETE FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response;
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Task not found");
                $response->send();
                exit;
            }

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Task deleted");
            $response->send();
            exit;
        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to delete task");
            $response->send();
            exit;
        }
    } */ elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { // Create new product
        try {
            if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content type header is not set to JSON");
                $response->send();
                exit;
            }

            $rawPOSTData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPOSTData)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }

            if (!isset($jsonData->title) || !isset($jsonData->completed)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->title) ? $response->addMessage("Title field is mandatory and must be provided") : false);
                (!isset($jsonData->completed) ? $response->addMessage("Completed field is mandatory and must be provided") : false);
                $response->send();
                exit;
            }

            $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);

            $title = $newTask->getTitle();
            $description = $newTask->getDescription();
            $deadline = $newTask->getDeadline();
            $completed = $newTask->getCompleted();

            $query = $writeDB->prepare('INSERT INTO tbltasks (title, description, deadline, completed, userid) VALUES (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed, :userid)');
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to create task");
                $response->send();
                exit;
            }

            $lastTaskID = $writeDB->lastInsertId();

            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to retrieve task after creation");
                $response->send();
                exit;
            }

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("Task created");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (TaskException $ex) {
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to insert task into database - check submitted data for errors");
            $response->send();
            exit;
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}
