<?php

require_once('controller/db.php');
require_once('model/Response.php');

function sendResponse($statusCode, $success, $message = null, $toCache = false, $data = null)
{
    $response = new Response();
    $response->setHttpStatusCode($statusCode);
    $response->setSuccess($success);

    if ($message != null) {
        $response->addMessage($message);
    }
    $response->toCache($toCache);

    if ($data != null) {
        $response->setData($data);
    }
    $response->send();
    exit;
}

function checkAuthStatusAndReturnUserID($writeDB)
{
    // BEGIN AUTH SCRIPT
    // Authenticate user with access token
    // Check to see if access token is provided in the HTTP Authorization header and that the value is longer than 0 chars

    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {

        $message = null;

        if (!isset($_SERVER['HTTP:AUTHORIZATION'])) {
            $message = "Access token is missing from the header";
        } else {
            if (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
                $message = "Access token cannot be blank";
            }
        }

        sendResponse(401, false, $message);
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    try {
        $query = $writeDB->prepare("SELECT userid, accesstokenexpiry, useractive, loginattempts FROM $writeDB->tblsessions, $writeDB->tblusers WHERE $writeDB->tblsessions.userid = $writeDB->tblusers.id AND accesstoken = :accesstoken");
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(401, false, "Invalid Access Token");
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_userid = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];

        if ($returned_useractive !== "Y") {
            sendResponse(401, false, "User account not active");
        }

        if ($returned_loginattempts >= 3) {
            sendResponse(401, false, "User account is currently locked out");
        }

        if (strtotime($returned_accesstokenexpiry) < (time() - 3600)) {
            sendResponse(401, false, "Access token expired");
        }

        return $returned_userid;
    } catch (PDOException $ex) {
        sendResponse(500, false, "There was an issue authenticating - please try again");
    }
    // End auth script
}
