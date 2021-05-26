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
            if (!isset($jsonData['title'])) sendResponse(400, false, "Name field is mandatory and must be provided");

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
        } catch (TaskException $ex) {
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
