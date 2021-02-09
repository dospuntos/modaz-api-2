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

// NO auth script - open to public
// Script removed
// End auth script
// Check authentication to return some additional data in query
$userId = simpleCheckAuthStatusAndReturnUserID($writeDB);

if (array_key_exists("productid", $_GET)) { // Return product by ID

    $productid = $_GET['productid'];

    if ($productid === '' || !is_numeric($productid)) {
        sendResponse(400, false, "Product ID cannot be blank or must be numeric");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Get single product by ID
        try {
            $query = $readDB->prepare("SELECT id, name, description, images, price, zinprice, price_discount FROM $readDB->tblproducts WHERE id = :productid");

            $query->bindParam(':productid', $productid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "Product not found");
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($row['id'], $row['name'], $row['description'], $row['images'], $row['price'], $row['zinprice'], $row['price_discount']);
                $productArray[] = $product->returnProductAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['products'] = $productArray;

            sendResponse(200, true, null, true, $returnData);
        } catch (TaskException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get Product");
        }
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

            $query = $readDB->prepare("SELECT id, name, description, images, price, zinprice, price_discount FROM $readDB->tblproducts WHERE state = :published");
            $query->bindParam(':published', $published, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            $productsArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($row['id'], $row['name'], $row['description'], $row['images'], $row['price'], $row['zinprice'], $row['price_discount']);
                $productsArray[] = $product->returnProductAsArray();
            }
            $returnData['rows_returned'] = $rowCount;
            $returnData['products'] = $productsArray;

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

            $query = $readDB->prepare("SELECT id, name, description, images, price, zinprice, price_discount FROM $readDB->tblproducts LIMIT :pglimit OFFSET :offset");
            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            $productsArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($row['id'], $row['name'], $row['description'], $row['images'], $row['price'], $row['zinprice'], $row['price_discount']);
                $productsArray[] = $product->returnProductAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $productsCount;
            $returnData['total_pages'] = $numOfPages;
            ($page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
            ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
            $returnData['tasks'] = $productsArray;

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
            //$query = $readDB->prepare("SELECT id, name, description, images, price, zinprice, price_discount from $readDB->tblproducts");
            $query = $readDB->prepare("SELECT p.id, p.name, p.state, p.description, p.images, p.category, p.featured, p.orderdate, p.release_date, p.season, p.wholesaleprice, p.msrp, p.price, p.zinprice, p.price_discount, p.weight, p.composition, p.manufacturer, p.country, v.id AS vid, v.product_id, v.upc, v.size, v.color, v.stock FROM $readDB->tblproducts p, $readDB->tblproductvariants v WHERE p.id = v.product_id ORDER BY p.name ASC");
            $query->execute();

            $productsArray = array();

            $rowCount = $query->rowCount();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // TODO: send full array to Product and check values in constructor
                $product = new Product(
                    $userId,
                    $row['id'],
                    $row['name'],
                    $row['state'],
                    $row['description'],
                    $row['images'],
                    $row['category'],
                    $row['featured'],
                    $row['orderdate'],
                    $row['release_date'],
                    $row['season'],
                    $row['wholesaleprice'],
                    $row['msrp'],
                    $row['price'],
                    $row['zinprice'],
                    $row['price_discount'],
                    $row['weight'],
                    $row['composition'],
                    $row['manufacturer'],
                    $row['country'],
                    $row['vid'],
                    $row['upc'],
                    $row['size'],
                    $row['color'],
                    $row['stock']
                );
                $productsArray[] = $product->returnProductAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['products'] = joinProductsById($productsArray);
            sendResponse(200, true, null, true, $returnData);
            exit;
        } catch (TaskException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get products");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}
