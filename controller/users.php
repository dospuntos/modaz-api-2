<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../functions.php');

// Try to create a DB connection
try {

    $writeDB = DB::connectWriteDB();
} catch (PDOException $ex) {
    error_log('Connection error: ' . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, "Request method not allowed");
}

// Check for JSON content type
if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
    sendResponse(400, false, "Content Type header not set to JSON");
}

// Get and validate JSON body
$rawPostData = file_get_contents('php://input');

if (!$jsonData = json_decode($rawPostData)) {
    sendResponse(400, false, "Request body is not valid JSON");
}

if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)) {
    $messages = [];
    if (!isset($jsonData->fullname)) $messages[] = "Full name not supplied";
    if (!isset($jsonData->username)) $messages[] = "Username not supplied";
    if (!isset($jsonData->password)) $messages[] = "Password not supplied";

    sendResponse(400, false, empty($messages) ? null : $messages);
}

if (strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname > 255) || strlen($jsonData->username) < 1 || strlen($jsonData->username > 255) || strlen($jsonData->password) < 1 || strlen($jsonData->password > 255)) {
    $messages = [];
    if (strlen($jsonData->fullname) > 255) $messages[] = "Full name cannot be greater than 255 characters";
    if (strlen($jsonData->fullname) < 1) $messages[] = "Full name cannot be blank";
    if (strlen($jsonData->username) > 255) $messages[] = "Username cannot be greater than 255 characters";
    if (strlen($jsonData->username) < 1) $messages[] = "Username cannot be blank";
    if (strlen($jsonData->password) > 255) $messages[] = "Password cannot be greater than 255 characters";
    if (strlen($jsonData->password) < 1) $messages[] = "Password cannot be blank";

    sendResponse(400, false, empty($messages) ? null : $messages);
}

$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;

try {

    $query = $writeDB->prepare('SELECT id FROM tblusers WHERE username = :username');
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount !== 0) {
        sendResponse(409, false, "Username already exists");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = $writeDB->prepare('INSERT INTO tblusers (fullname, username, password) VALUES (:fullname, :username, :password)');
    $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        sendResponse(500, false, "There was an issue creating a user account - please try again.");
    }

    $lastUserID = $writeDB->lastInsertId();

    $returnData = array();
    $returnData['user_id'] = $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    sendResponse(201, true, "User created", false, $returnData);
} catch (PDOException $ex) {
    error_Log("Database query error: " . $ex, 0);
    sendResponse(500, false, "There was an issue creating a user account - please try again.");
}
