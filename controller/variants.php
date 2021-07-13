<?php

require_once('db.php');
require_once('../model/Variant.php');
require_once('../model/Response.php');
require_once('../functions.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

// Check authentication to return some additional data in query and allow editing
$userId = simpleCheckAuthStatusAndReturnUserID($writeDB);

if (array_key_exists("variantid", $_GET)) { // GET/PATCH variant by ID

    $variantid = $_GET['variantid'];

    if ($variantid === '' || !is_numeric($variantid)) sendResponse(400, false, "Variant ID cannot be blank and must be numeric");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Return variant by ID

        try {
            $query = $readDB->prepare("SELECT id, product_id, size, color, stock, upc, item, transport_id FROM $readDB->tblproductvariants WHERE product_id LIKE :productid");

            $query->bindParam(':productid', $variantid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "Product ID not found");
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $productVariant = new ProductVariant($userId, $row);
                $variantArray[] = $productVariant->returnVariantAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = count($variantArray);
            $returnData['variants'] = $variantArray;

            sendResponse(200, true, null, true, $returnData);
        } catch (VariantException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get Variant");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') { // DELETE VARIANT

        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");

        $messages = [];
        // Logged in user, attempt to remove item
        try {
            $query = $writeDB->prepare("DELETE FROM $readDB->tblproductvariants WHERE id = :variantid");
            $query->bindParam(':variantid', $variantid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(404, false, "Variant not found");

            $messages[] = "Variant ID " . $variantid . " deleted";
        } catch (PDOException $ex) {
            sendResponse(500, false, "Failed to delete variant");
        }

        sendResponse(200, true, $messages);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') { // EDIT VARIANT
        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");
        try {

            if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
                sendResponse(400, false, "Content type header is not set to JSON");
            }

            $rawPOSTData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPOSTData)) {
                sendResponse(400, false, "Request body is not valid JSON");
            }

            $size_updated = false;
            $color_updated = false;
            $stock_updated = false;
            $upc_updated = false;
            $item_updated = false;
            $transport_id_updated = false;

            $queryFields = "";

            if (isset($jsonData->size)) {
                $size_updated = true;
                $queryFields .=  "size = :size, ";
            }

            if (isset($jsonData->color)) {
                $color_updated = true;
                $queryFields .=  "color = :color, ";
            }

            if (isset($jsonData->stock)) {
                $stock_updated = true;
                $queryFields .=  "stock = :stock, ";
            }

            if (isset($jsonData->upc)) {
                $upc_updated = true;
                $queryFields .=  "upc = :upc, ";
            }

            if (isset($jsonData->item)) {
                $item_updated = true;
                $queryFields .=  "item = :item, ";
            }

            if (isset($jsonData->transport_id)) {
                $transport_id_updated = true;
                $queryFields .=  "transport_id = :transport_id, ";
            }


            $queryFields = rtrim($queryFields, ", ");

            if ($size_updated === false && $color_updated === false && $stock_updated === false && $upc_updated === false && $item_updated === false && $transport_id_updated === false) {
                sendResponse(400, false, "No variant fields provided");
            }

            $query = $writeDB->prepare("SELECT id, product_id, size, color, stock, upc, item, transport_id FROM $readDB->tblproductvariants WHERE id LIKE :variantid");
            $query->bindParam(':variantid', $variantid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "No variants found to update");
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $productVariant = new ProductVariant($userId, $row);
            }

            $queryString = "UPDATE $writeDB->tblproductvariants SET " . $queryFields . " WHERE id = :variantid";
            $query = $writeDB->prepare($queryString);

            if ($size_updated === true) {
                $product->setSize($jsonData->size);
                $up_size = $product->getSize();
                $query->bindParam(':size', $up_size, PDO::PARAM_STR);
            }

            if ($color_updated === true) {
                $product->setColor($jsonData->color);
                $up_color = $product->getColor();
                $query->bindParam(':color', $up_color, PDO::PARAM_STR);
            }

            if ($stock_updated === true) {
                $product->setStock($jsonData->stock);
                $up_stock = $product->getStock();
                $query->bindParam(':stock', $up_stock, PDO::PARAM_STR);
            }

            if ($upc_updated === true) {
                $product->setUpc($jsonData->upc);
                $up_upc = $product->getUpc();
                $query->bindParam(':upc', $up_upc, PDO::PARAM_STR);
            }

            if ($item_updated === true) {
                $product->setItem($jsonData->item);
                $up_item = $product->getItem();
                $query->bindParam(':item', $up_item, PDO::PARAM_STR);
            }

            if ($transport_id_updated === true) {
                $product->setTransport_id($jsonData->transport_id);
                $up_transport_id = $product->getTransport_id();
                $query->bindParam(':transport_id', $up_transport_id, PDO::PARAM_STR);
            }

            $query->bindParam(":variantid", $variantid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "Variant not updated");
            }

            // Get updated Variant from database
            $query = $writeDB->prepare("SELECT id, product_id, size, color, stock, upc, item, transport_id FROM $readDB->tblproductvariants WHERE id LIKE :variantid");
            $query->bindParam(':variantid', $variantid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "No variant found after update");
            }

            $variantArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $productVariant = new ProductVariant($userId, $row);
                $variantArray[] = $productVariant->returnVariantAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['variants'] = $variantArray;

            sendResponse(200, true, "Variant updated");
        } catch (VariantException $ex) {
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex);
            sendResponse(500, false, "Failed to update variant - check your data for errors");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (empty($_GET)) { // Get all categories or create variant

    if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Create new ProductVariant

        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");

        try {
            if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') sendResponse(400, false, "Content type header is not set to JSON");

            $rawPOSTData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPOSTData, true)) sendResponse(400, false, "Request body is not valid JSON");

            // Required: Product ID
            if (!isset($jsonData['product_id'])) sendResponse(400, false, "Parent Product ID field is mandatory and must be provided");

            // Create the variant
            $name = $jsonData['name'];
            $description = isset($jsonData['description']) ? $jsonData['description'] : "";

            $query = $writeDB->prepare("INSERT INTO $readDB->tblcategories (name, description, created) VALUES (:name, :description, NOW())");
            $query->bindParam(':name', $name, PDO::PARAM_INT);
            $query->bindParam(':description', $description, PDO::PARAM_STR);

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(500, false, "Failed to create variant");

            $lastVariantid = $writeDB->lastInsertId();

            $query = $readDB->prepare("SELECT id, name, description FROM $readDB->tblcategories WHERE id LIKE :variantid");
            $query->bindParam(':variantid', $lastVariantid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(500, false, "Failed to retrieve variant after creation");

            $variantArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $productVariant = new ProductVariant($row['id'], $row['name'], $row['description']);
                $variantArray[] = $productVariant->returnVariantAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['categories'] = $variantArray;

            sendResponse(201, true, "Variant created", false, $returnData);
        } catch (VariantException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            sendResponse(500, false, "Failed to insert variant into database - check submitted data for errors");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}
