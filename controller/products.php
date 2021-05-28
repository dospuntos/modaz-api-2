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

if (array_key_exists("productid", $_GET)) { // GET/PATCH product by ID

    $productid = $_GET['productid'];

    if ($productid === '' || !is_numeric($productid)) {
        sendResponse(400, false, "Product ID cannot be blank or must be numeric");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Return product by ID

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
        } catch (ProductException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get Product");
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
        } catch (ProductException $ex) {
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
        } catch (ProductException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get products");
        }
    } else {
        sendResponse(404, false, "Request method not allowed");
    }
} elseif (empty($_GET)) { // Get all products or create product

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
        } catch (ProductException $ex) {
            sendResponse(500, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(500, false, "Failed to get products");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { // Create new product

        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");

        try {
            if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
                sendResponse(400, false, "Content type header is not set to JSON");
            }

            $rawPOSTData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPOSTData, true)) {
                sendResponse(400, false, "Request body is not valid JSON");
            }
            // Required: Name
            if (!isset($jsonData['name'])) sendResponse(400, false, "Name field is mandatory and must be provided");

            // Check if name exists in database
            $query = $writeDB->prepare("SELECT name FROM $readDB->tblproducts WHERE name LIKE :name");
            $query->bindParam(':name', $jsonData['name'], PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount !== 0) sendResponse(409, false, "Failed to create product - the product name must be unique");

            // Create the main product
            $newProduct = new Product($userId, $jsonData);

            $state = $newProduct->getState();
            $release_date = $newProduct->getRelease_date();
            $orderdate = $newProduct->getOrderdate();
            $season = $newProduct->getSeason();
            $featured = $newProduct->getFeatured();
            $name = $newProduct->getName();
            $images = json_encode($newProduct->getImages());
            $category = $newProduct->getCategory();
            $description = $newProduct->getDescription();
            $wholesaleprice = $newProduct->getWholesaleprice();
            $msrp = $newProduct->getMsrp();
            $price = $newProduct->getPrice();
            $zinprice = $newProduct->getZinprice();
            $price_discount = $newProduct->getPriceDiscount();
            $weight = $newProduct->getWeight();
            $composition = $newProduct->getComposition();
            $manufacturer = $newProduct->getManufacturer();
            $country = $newProduct->getCountry();

            $query = $writeDB->prepare("INSERT INTO $readDB->tblproducts (state, release_date, orderdate, season, featured, name, images, category, description, wholesaleprice, msrp, price, zinprice, price_discount, weight, composition, manufacturer, country) VALUES (:state, :release_date, :orderdate, :season, :featured, :name, :images, :category, :description, :wholesaleprice, :msrp, :price, :zinprice, :price_discount, :weight, :composition, :manufacturer, :country)");
            $query->bindParam(':state', $state, PDO::PARAM_INT);
            $query->bindParam(':release_date', $release_date, PDO::PARAM_STR);
            $query->bindParam(':orderdate', $orderdate, PDO::PARAM_STR);
            $query->bindParam(':season', $season, PDO::PARAM_STR);
            $query->bindParam(':featured', $featured, PDO::PARAM_STR);
            $query->bindParam(':name', $name, PDO::PARAM_STR);
            $query->bindParam(':images', $images, PDO::PARAM_STR);
            $query->bindParam(':category', $category, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':wholesaleprice', $wholesaleprice, PDO::PARAM_STR);
            $query->bindParam(':msrp', $msrp, PDO::PARAM_INT);
            $query->bindParam(':price', $price, PDO::PARAM_INT);
            $query->bindParam(':zinprice', $zinprice, PDO::PARAM_INT);
            $query->bindParam(':price_discount', $price_discount, PDO::PARAM_INT);
            $query->bindParam(':weight', $weight, PDO::PARAM_STR);
            $query->bindParam(':composition', $composition, PDO::PARAM_STR);
            $query->bindParam(':manufacturer', $manufacturer, PDO::PARAM_STR);
            $query->bindParam(':country', $country, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(500, false, "Failed to create main product");

            $lastProductID = $writeDB->lastInsertId();

            // Create a product variant
            $size = $newProduct->getSize();
            $color = $newProduct->getColor();
            $stock = $newProduct->getStock();
            $upc = $newProduct->getUpc();
            $item = "-";
            $transport_id = 0;

            $query = $writeDB->prepare("INSERT INTO $readDB->tblproductvariants (product_id, size, color, stock, upc, item, transport_id) VALUES (:product_id, :size, :color, :stock, :upc, :item, :transport_id)");
            $query->bindParam(':product_id', $lastProductID, PDO::PARAM_INT);
            $query->bindParam(':size', $size, PDO::PARAM_STR);
            $query->bindParam(':color', $color, PDO::PARAM_STR);
            $query->bindParam(':stock', $stock, PDO::PARAM_INT);
            $query->bindParam(':upc', $upc, PDO::PARAM_STR);
            $query->bindParam(':item', $item, PDO::PARAM_STR);
            $query->bindParam(':transport_id', $transport_id, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(500, false, "Failed to create product variant");

            $query = $readDB->prepare("SELECT p.id, p.name, p.state, p.description, p.images, p.category, p.featured, p.orderdate, p.release_date, p.season, p.wholesaleprice, p.msrp, p.price, p.zinprice, p.price_discount, p.weight, p.composition, p.manufacturer, p.country, v.id AS vid, v.product_id, v.upc, v.size, v.color, v.stock FROM $readDB->tblproducts p, $readDB->tblproductvariants v WHERE p.id LIKE :productid AND v.product_id LIKE :productid2");
            $query->bindParam(':productid', $lastProductID, PDO::PARAM_INT);
            $query->bindParam(':productid2', $lastProductID, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(500, false, "Failed to retrieve product after creation");

            $productArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($userId, $row);
                $productArray[] = $product->returnProductAsArray();
            }

            $joinedProducts = joinProductsById($productArray);

            $returnData = array();
            $returnData['rows_returned'] = count($joinedProducts);
            $returnData['products'] = $joinedProducts;

            sendResponse(201, true, "Product and variant created", false, $returnData);
        } catch (ProductException $ex) {
            error_log("Database query error - " . $ex, 0);
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            sendResponse(500, false, "Failed to insert product into database - check submitted data for errors");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}
