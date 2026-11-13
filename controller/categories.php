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

if (array_key_exists("categoryid", $_GET)) { // GET/PATCH category by ID

    $categoryid = $_GET['categoryid'];

    if ($categoryid === '' || !is_numeric($categoryid)) sendResponse(400, false, "Category ID cannot be blank and must be numeric");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Return category by ID

        try {
            // Prepare statement
            $sql = "SELECT id, parent_id, name, alias, description, image, published, ordering FROM $readDB->tblcategories WHERE";

            if (!$userId) {
                $sql .= " published = 1 AND";
            }

            $sql .= " id = :categoryid ORDER BY parent_id, ordering, name";

            // Execute statement
            $query = $readDB->prepare($sql);

            $query->bindParam(':categoryid', $categoryid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(404, false, "Category not found");
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $category = new Category($row);
                $categoryArray[] = $category->returnCategoryAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = count($categoryArray);
            $returnData['categories'] = $categoryArray;

            sendResponse(200, true, null, true, $returnData);
        } catch (CategoryException $ex) {
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
            if ($ex->getCode() === '23000') {
                sendResponse(409, false, "Category cannot be deleted because it has subcategories");
            }

            error_log("Category deletion error: " . $ex->getMessage());
            sendResponse(500, false, "Failed to delete category");
        }

        sendResponse(200, true, $messages);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') { // EDIT CATEGORY
        if (!$userId) sendResponse(401, false, "User not authorized or not logged in.");

        try {
            // Validate content type
            $contentType = strtolower(trim(
                explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]
            ));

            if ($contentType !== 'application/json') {
                sendResponse(400, false, "Content type header is not set to JSON");
            }

            // Decode JSON
            $rawPOSTData = file_get_contents('php://input');
            $jsonData = json_decode($rawPOSTData);

            if (json_last_error() !== JSON_ERROR_NONE || !is_object($jsonData)) {
                sendResponse(400, false, "Request body is not valid JSON");
            }

            // Fields that can be updated
            $allowedFields = [
                'name',
                'alias',
                'description',
                'image',
                'parent_id',
                'published',
                'ordering'
            ];

            $providedFields = [];

            foreach ($allowedFields as $field) {
                if (property_exists($jsonData, $field)) {
                    $providedFields[] = $field;
                }
            }

            if (empty($providedFields)) {
                sendResponse(400, false, "No category fields provided");
            }

            // Retrieve existing category
            $query = $writeDB->prepare("SELECT id, parent_id, name, alias, description, image, published, ordering FROM $writeDB->tblcategories WHERE id = :categoryid");

            $query->bindValue(':categoryid', (int) $categoryid, PDO::PARAM_INT);
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                sendResponse(404, false, "Category not found");
            }

            // Create category model
            $category = new Category($row);

            // Apply and validate changes
            $updateValues = [];

            if (property_exists($jsonData, 'name')) {
                $category->setName($jsonData->name);
                $updateValues['name'] = [$category->getName(), PDO::PARAM_STR];
            }

            if (property_exists($jsonData, 'alias')) {
                $category->setAlias($jsonData->alias);
                $updateValues['alias'] = [$category->getAlias(), PDO::PARAM_STR];
            }

            if (property_exists($jsonData, 'description')) {
                $category->setDescription($jsonData->description);

                $value = $category->getDescription();
                $updateValues['description'] = [$value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR];
            }

            if (property_exists($jsonData, 'image')) {
                $category->setImage($jsonData->image);

                $value = $category->getImage();
                $updateValues['image'] = [$value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR];
            }

            if (property_exists($jsonData, 'published')) {
                $category->setPublished($jsonData->published);
                $updateValues['published'] = [$category->getPublished(), PDO::PARAM_INT];
            }

            if (property_exists($jsonData, 'ordering')) {
                $category->setOrdering($jsonData->ordering);
                $updateValues['ordering'] = [$category->getOrdering(), PDO::PARAM_INT];
            }

            // Validate parent category and prevent circular hierarchy
            if (property_exists($jsonData, 'parent_id')) {

                $category->setParentId($jsonData->parent_id);
                $parentId = $category->getParentId();

                if ($parentId !== null) {

                    // Cannot be its own parent
                    if ($parentId === (int) $categoryid) {
                        sendResponse(400, false, "A category cannot be its own parent");
                    }

                    // Parent must exist
                    $query = $writeDB->prepare("SELECT id FROM $writeDB->tblcategories WHERE id = :parent_id");

                    $query->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
                    $query->execute();

                    if (!$query->fetchColumn()) {
                        sendResponse(400, false, "Parent category not found");
                    }

                    // Prevent moving category under one of its descendants
                    $query = $writeDB->prepare("WITH RECURSIVE descendants (id) AS
                        (SELECT id FROM $writeDB->tblcategories WHERE parent_id = :categoryid UNION ALL
                            SELECT c.id FROM $writeDB->tblcategories c INNER JOIN descendants d ON c.parent_id = d.id)
                        SELECT id FROM descendants WHERE id = :parentid LIMIT 1");

                    $query->bindValue(':categoryid', (int) $categoryid, PDO::PARAM_INT);
                    $query->bindValue(':parentid', $parentId, PDO::PARAM_INT);
                    $query->execute();

                    if ($query->fetchColumn()) {
                        sendResponse(400, false, "A category cannot be moved under one of its descendants");
                    }
                }

                $updateValues['parent_id'] = [$parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT];
            }

            // Build UPDATE statement
            $queryFields = [];

            foreach (array_keys($updateValues) as $field) {
                $queryFields[] = "$field = :$field";
            }

            $queryString = "UPDATE $writeDB->tblcategories SET " . implode(', ', $queryFields) . " WHERE id = :categoryid";

            $query = $writeDB->prepare($queryString);

            foreach ($updateValues as $field => [$value, $type]) {
                $query->bindValue(":$field", $value, $type);
            }

            $query->bindValue(':categoryid', (int) $categoryid, PDO::PARAM_INT);
            $query->execute();

            // Retrieve updated category
            $query = $writeDB->prepare("SELECT id, parent_id, name, alias, description, image, published, ordering FROM $writeDB->tblcategories WHERE id = :categoryid");

            $query->bindValue(':categoryid', (int) $categoryid, PDO::PARAM_INT);
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                sendResponse(404, false, "Category not found after update");
            }

            $category = new Category($row);

            $returnData = ['rows_returned' => 1, 'categories' => [$category->returnCategoryAsArray()]];

            sendResponse(200, true, "Category updated", true, $returnData);
        } catch (CategoryException $ex) {
            sendResponse(400, false, $ex->getMessage());
        } catch (PDOException $ex) {
            error_log("Category update error: " . $ex->getMessage());

            if ($ex->getCode() === '23000') {
                sendResponse(409, false, "Category update conflicts with existing category data");
            }

            sendResponse(500, false, "Failed to update category");
        }
    } else {
        sendResponse(405, false, "Request method not allowed");
    }
} elseif (empty($_GET)) { // Get all categories or create category

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            // Prepare query
            $sql = "SELECT id, parent_id, name, alias, description,
                        image, published, ordering, created_at, updated_at
                    FROM $readDB->tblcategories";

            if (!$userId) {
                $sql .= " WHERE published = 1";
            }

            $sql .= " ORDER BY parent_id, ordering, name";

            // Execute query
            $query = $readDB->prepare($sql);
            $query->execute();

            $categoriesArray = array();

            $rowCount = $query->rowCount();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $categories = new Category($row);
                $categoriesArray[] = $categories->returnCategoryAsArray();
            }

            $returnData['rows_returned'] = $rowCount;

            $returnData['categories'] = $categoriesArray;
            sendResponse(200, true, $userId ? null : "Request by Anonymous user", true, $returnData);
            exit;
        } catch (CategoryException $ex) {
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

            // Required: Name
            if (!isset($jsonData['name']) || !is_string($jsonData['name']) || trim($jsonData['name']) === '') {
                sendResponse(400, false, "Name field is mandatory and must be provided");
            }

            // Category properties
            $parentId = $jsonData['parent_id'] ?? null;
            $name = trim($jsonData['name']);
            $alias = !empty($jsonData['alias']) ? trim($jsonData['alias']) : generateAlias($name);
            $description = $jsonData['description'] ?? null;
            $image = $jsonData['image'] ?? null;
            $published = $jsonData['published'] ?? 0;
            $ordering = $jsonData['ordering'] ?? 0;

            // Validate parent category
            if ($parentId !== null) {
                if (filter_var($parentId, FILTER_VALIDATE_INT) === false || $parentId < 1) {
                    sendResponse(400, false, "Invalid parent category ID");
                }

                $checkParent = $writeDB->prepare("SELECT id FROM $writeDB->tblcategories WHERE id = :parent_id");
                $checkParent->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
                $checkParent->execute();

                if (!$checkParent->fetch()) {
                    sendResponse(400, false, "Parent category not found");
                }
            }

            // Create category model for validation
            $category = new Category([
                'parent_id' => $parentId,
                'name' => $name,
                'alias' => $alias,
                'description' => $description,
                'image' => $image,
                'published' => $published,
                'ordering' => $ordering
            ]);

            // Use validated values
            $query = $writeDB->prepare("
                INSERT INTO $writeDB->tblcategories
                    (parent_id, name, alias, description, image, published, ordering)
                VALUES
                    (:parent_id, :name, :alias, :description, :image, :published, :ordering)
            ");

            $query->bindValue(':parent_id', $category->getParentId(), $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $query->bindValue(':name', $category->getName(), PDO::PARAM_STR);
            $query->bindValue(':alias', $category->getAlias(), PDO::PARAM_STR);

            $description = $category->getDescription();
            $query->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            $image = $category->getImage();
            $query->bindValue(':image', $image, $image === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            $query->bindValue(':published', $category->getPublished(), PDO::PARAM_INT);
            $query->bindValue(':ordering', $category->getOrdering(), PDO::PARAM_INT);

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) sendResponse(500, false, "Failed to create category");

            $lastCategoryID = $writeDB->lastInsertId();

            $query = $readDB->prepare("SELECT id, parent_id, name, alias, description, image, published, ordering FROM $readDB->tblcategories WHERE id = :categoryid");

            $query->bindValue(':categoryid', $lastCategoryID, PDO::PARAM_INT);
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                sendResponse(500, false, "Failed to retrieve category after creation");
            }

            $category = new Category($row);

            $returnData = [
                'rows_returned' => 1,
                'categories' => [$category->returnCategoryAsArray()]
            ];

            sendResponse(201, true, "Category created", false, $returnData);
        } catch (CategoryException $ex) {
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
