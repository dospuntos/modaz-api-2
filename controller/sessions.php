<?php

/*  /sessions = POST - create a session / log in
    /sessions/3 = DELETE - log out a user
    /sessions/3 = PATCH - refresh session */

require_once('db.php');
require_once('../model/Response.php');
require_once('../functions.php');

try {

    $writeDB = DB::ConnectWriteDB();
} catch (PDOException $ex) {
    error_log('Connection error: ' . $ex, 0);
    sendResponse(500, false, "Database connection error");
}

if (array_key_exists("sessionid", $_GET)) {
    //  session.php?sessionid=3
    $sessionid = $_GET['sessionid'];

    if ($sessionid === '' || !is_numeric($sessionid)) {
        $messages = [];
        if ($sessionid === '') $messages[] = "Session ID cannot be blank";
        if (!is_numeric($sessionid)) $messages[] = "Session ID must be numeric";

        sendResponse(400, false, empty($messages) ? null : $messages);
    }

    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $messages = [];
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) $messages[] = "Access token is missing from the header";
        if (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) $messages[] = "Access token cannot be blank";

        sendResponse(400, false, empty($messages) ? null : $messages);
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') { // Log out (delete session)

        try {

            $query = $writeDB->prepare("DELETE FROM $writeDB->tblsessions WHERE id = :sessionid and accesstoken = :accesstoken");
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(400, false, 'Failed to log out of this session using access token provided');
            }

            $returnData = array();
            $returnData['session_id'] = intval($sessionid);

            sendResponse(200, true, 'Logged out', false, $returnData);
        } catch (PDOException $ex) {
            error_log('Cannot log out - ' . $ex);
            sendResponse(500, false, 'There was an issue logging out - please try again');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') { // Refresh token (get new access token if refresh token is still valid)

        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            sendResponse(400, false, "Content Type header not set to JSON");
        }

        $rawPatchData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPatchData)) {
            sendResponse(400, false, "Request body is not valid JSON");
        }

        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
            $messages = [];
            if (!isset($jsonData->refresh_token)) $messages[] = "Refresh token not supplied";
            if (strlen($jsonData->refresh_token) < 1) $messages[] = "Refresh token cannot be blank";

            sendResponse(400, false, $messages);
        }


        try {

            $refreshtoken = $jsonData->refresh_token;

            $query = $writeDB->prepare("
                SELECT $writeDB->tblsessions.id AS sessionid,
                        $writeDB->tblsessions.userid AS userid,
                        accesstoken,
                        refreshtoken,
                        useractive,
                        loginattempts,
                        accesstokenexpiry,
                        refreshtokenexpiry
                    FROM $writeDB->tblsessions, $writeDB->tblusers
                    WHERE $writeDB->tblusers.id = $writeDB->tblsessions.userid
                    AND $writeDB->tblsessions.id = :sessionid
                    AND $writeDB->tblsessions.accesstoken = :accesstoken
                    AND $writeDB->tblsessions.refreshtoken = :refreshtoken");

            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(401, false, 'Access token or refresh token is incorrect for session id');
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_useractive = $row['useractive'];
            $returned_loginattempts = $row['loginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            if ($returned_useractive !== 'Y') {
                sendResponse(401, false, 'User account is not active');
            }

            if ($returned_loginattempts >= 3) {
                sendResponse(401, false, 'User account is currently locked out');
            }

            // Check if refresh token has expired
            if (strtotime($returned_refreshtokenexpiry) < time()) {
                sendResponse(401, false, 'Refresh token has expired, please log in again');
            }

            $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24) . time()));
            $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24) . time()));

            $access_token_expiry_seconds = 1200;
            $refresh_token_expiry_seconds = 1209600;

            $query = $writeDB->prepare("UPDATE $writeDB->tblsessions SET
                accesstoken = :accesstoken,
                accesstokenexpiry = DATE_ADD(NOW(), INTERVAL :accesstokenexpiryseconds SECOND),
                refreshtoken = :refreshtoken,
                refreshtokenexpiry = DATE_ADD(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND)
                WHERE id = :sessionid
                AND userid = :userid
                AND accesstoken = :returnedaccesstoken
                AND refreshtoken = :returnedrefreshtoken
             ");
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
            $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                sendResponse(401, false, 'Access token could not be refreshed - please log in again');
            }

            $returnData = array();
            $returnData['session_id'] = $returned_sessionid;
            $returnData['access_token'] = $accesstoken;
            $returnData['access_token_expiry'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refreshtoken;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

            sendResponse(200, true, 'Token refreshed', false, $returnData);
        } catch (PDOException $ex) {
            sendResponse(500, false, 'There was an issue refreshing access token - please log in again');
        }
    } else {
        sendResponse(405, false, 'Request method not allowed');
    }
} elseif (empty($_GET)) { // create session using username and password

    // Make sure only POST allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, false, "Request method not allowed");
    }

    // Wait 1 second to stop brute force attacks.
    sleep(1);

    //die(json_encode($_SERVER['CONTENT_TYPE']));
    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        sendResponse(400, false, "Content type header not set to JSON");
    }

    $rawPostData = file_get_contents('php://input');

    if (!$jsonData = json_decode($rawPostData)) {
        sendResponse(400, false, "Request body is not valid JSON");
    }

    if (!isset($jsonData->username) || !isset($jsonData->password)) {
        $messages = [];
        if (!isset($jsonData->username)) $messages[] = "Username not supplied";
        if (!isset($jsonData->password)) $messages[] = "Password not supplied";
        sendResponse(400, false, empty($messages) ? null : $messages);
    }

    if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255  || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {
        $messages = [];
        if (strlen($jsonData->username) > 255) $messages[] = "Username must be less than 255 characters";
        if (strlen($jsonData->username) < 1) $messages[] = "Username cannot be blank";
        if (strlen($jsonData->password) > 255) $messages[] = "Password must be less than 255 characters";
        if (strlen($jsonData->password) < 1) $messages[] = "Password cannot be blank";
        sendResponse(400, false, empty($messages) ? null : $messages);
    }

    try {

        $username = $jsonData->username;
        $password = $jsonData->password;

        $query = $writeDB->prepare("SELECT id, fullname, username, password, useractive, loginattempts FROM $writeDB->tblusers WHERE username = :username");
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(401, false, "Username or password is incorrect");
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_id = $row['id'];
        $returned_fullname = $row['fullname'];
        $returned_username = $row['username'];
        $returned_password = $row['password'];
        $returned_useractive = $row['useractive'];
        $returned_logginattempts = $row['loginattempts'];

        // Check if user account is active
        if ($returned_useractive !== 'Y') {
            sendResponse(401, false, "User account not active");
        }

        if ($returned_logginattempts >= 3) {
            sendResponse(401, false, "User account is currently locked out");
        }

        // Check if password is not valid
        if (!password_verify($password, $returned_password)) {
            $query = $writeDB->prepare("UPDATE $writeDB->tblusers SET loginattempts = loginattempts+1 WHERE id = :id");
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            $query->execute();

            sendResponse(401, false, "Username or password is incorrect");
        }

        // Create tokens
        $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
        $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

        $access_token_expiry_seconds = 1200;
        $refresh_token_expiry_seconds = 1209600;
    } catch (PDOException $ex) {
        sendResponse(500, false, "There was an issue logging in");
    }

    // Try to save session in database
    try {

        $writeDB->beginTransaction();

        $query = $writeDB->prepare("UPDATE $writeDB->tblusers SET loginattempts = 0 WHERE id = :id");
        $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
        $query->execute();

        $stmt = "INSERT INTO $writeDB->tblsessions
            (userid,
            accesstoken,
            accesstokenexpiry,
            refreshtoken,
            refreshtokenexpiry)
            VALUES
            (:userid,
            :accesstoken,
            date_add(NOW(),
            INTERVAL :accesstokenexpiryseconds SECOND),
            :refreshtoken,
            date_add(NOW(),
            INTERVAL :refreshtokenexpiryseconds SECOND))";

        $query = $writeDB->prepare($stmt);

        $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
        $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
        $query->execute();
        $lastSessionID = $writeDB->lastInsertId();

        $writeDB->commit();

        $returnData = array();
        $returnData['session_id'] = intval($lastSessionID);
        $returnData['full_name'] = $returned_fullname;
        $returnData['access_token'] = $accesstoken;
        $returnData['access_token_expires_in'] = $access_token_expiry_seconds;
        $returnData['refresh_token'] = $refreshtoken;
        $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;

        sendResponse(201, true, null, false, $returnData);
    } catch (PDOException $ex) {
        error_log("Login error: " . $ex, 0);
        $writeDB->rollBack();
        sendResponse(500, false, "There was an issue logging in - please try again");
    }
} else {
    sendResponse(404, false, "Endpoint not found");
}
