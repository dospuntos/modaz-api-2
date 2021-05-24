<?php

require_once('db.php');
require_once('../model/Task.php');
require_once('../model/Response.php');

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header('Access-Control-Allow-Origin: http://localhost:8080');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');    // cache for 1 day
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS, DELETE');
header("Access-Control-Allow-Headers: *");
header('Vary: Origin');
header('Content-type: application/json;charset=utf-8');

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        // may also be using PUT, PATCH, HEAD etc
        header("Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS, DELETE");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit;
}

// Begin auth script

if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
    (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
    $response->send();
    exit;
}

$accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

try {
    $query = $writeDB->prepare("SELECT userid, accesstokenexpiry, useractive, loginattempts FROM $writeDB->tblsessions, $writeDB->tblusers WHERE $writeDB->tblsessions.userid = $writeDB->tblusers.id AND accesstoken = :accesstoken");
    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("Invalid Access Token");
        $response->send();
        exit;
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $returned_userid = $row['userid'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry'];
    $returned_useractive = $row['useractive'];
    $returned_loginattempts = $row['loginattempts'];

    if ($returned_useractive !== "Y") {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("User account not active");
        $response->send();
        exit;
    }

    if ($returned_loginattempts >= 3) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("User account is currently locked out");
        $response->send();
        exit;
    }

    if (strtotime($returned_accesstokenexpiry) < (time())) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage("Access token expired (Current time: " . time() . ", expiration time: " . strtotime($returned_accesstokenexpiry));
        $response->send();
        exit;
    }
} catch (PDOException $ex) {

    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("There was an issue authenticating - please try again");
    $response->send();
    exit;
}



// End auth script

if (array_key_exists("taskid", $_GET)) { // Return task by ID

    $taskid = $_GET['taskid'];

    if ($taskid === '' || !is_numeric($taskid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task ID cannot be blank or must be numeric");
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid");
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Task not found");
                $response->send();
                exit;
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (TaskException $ex) {
            $response = new Response;
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get Task");
            $response->send();
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

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
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

        try {

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content Type header not set to JSON");
                $response->send();
                exit;
            }

            $rawPatchData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPatchData)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }

            $title_updated = false;
            $description_updated = false;
            $deadline_updated = false;
            $completed_updated = false;

            $queryFields = "";

            if (isset($jsonData->title)) {
                $title_updated = true;
                $queryFields .=  "title = :title, ";
            }

            if (isset($jsonData->description)) {
                $description_updated = true;
                $queryFields .= "description = :description, ";
            }

            if (isset($jsonData->deadline)) {
                $deadline_updated = true;
                $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
            }

            if (isset($jsonData->completed)) {
                $completed_updated = true;
                $queryFields .= "completed = :completed, ";
            }

            $queryFields = rtrim($queryFields, ", ");

            if ($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No task fields provided");
                $response->send();
                exit;
            }

            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No task found to update");
                $response->send();
                exit;
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
            }

            $queryString = "UPDATE tbltasks SET " . $queryFields . " WHERE id = :taskid AND userid = :userid";
            $query = $writeDB->prepare($queryString);

            if ($title_updated === true) {
                $task->setTitle($jsonData->title);
                $up_title = $task->getTitle();
                $query->bindParam(':title', $up_title, PDO::PARAM_STR);
            }

            if ($description_updated === true) {
                $task->setDescription($jsonData->description);
                $up_description = $task->getDescription();
                $query->bindParam(':description', $up_description, PDO::PARAM_STR);
            }

            if ($deadline_updated === true) {
                $task->setDeadline($jsonData->deadline);
                $up_deadline = $task->getDeadline();
                $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
            }

            if ($completed_updated === true) {
                $task->setCompleted($jsonData->completed);
                $up_completed = $task->getCompleted();
                $query->bindParam(':completed', $up_completed);
            }

            $query->bindParam(":taskid", $taskid, PDO::PARAM_INT);
            $query->bindParam(":userid", $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Task not updated");
                $response->send();
                exit;
            }

            // Get inserted Task from database
            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No task found after update");
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
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Task updated");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to update task - check your data for errors");
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} elseif (array_key_exists("completed", $_GET)) { // Return completed/incompleted tasks

    $completed = $_GET['completed'];

    if ($completed !== 'Y' && $completed !== 'N') {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Completed filter must be Y or N");
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {

            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE completed = :completed AND userid = :userid');
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response;
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database query error - " - $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get tasks");
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} elseif (array_key_exists("page", $_GET)) { // Return pagination
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $page = $_GET['page'];

        if ($page == '' || !is_numeric($page)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Page number cannot be blank and must be numeric");
            $response->send();
            exit;
        }

        $limitPerPage = 20;

        try {

            $query = $readDB->prepare('SELECT COUNT(id) as totalNoOfTasks from tbltasks WHERE userid = :userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $tasksCount = intval($row['totalNoOfTasks']);

            $numOfPages = ceil($tasksCount / $limitPerPage);
            if ($numOfPages == 0) { // At least a single page
                $numOfPages = 1;
            }

            if ($page > $numOfPages || $page == 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Page not found");
                $response->send();
                exit;
            }

            $offset = ($page == 1 ? 0 : ($limitPerPage * ($page - 1)));

            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks WHERE userid = :userid LIMIT :pglimit OFFSET :offset');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $tasksCount;
            $returnData['total_pages'] = $numOfPages;
            ($page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
            ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get tasks");
            $response->send();
            exit;
        }
    } else {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} elseif (empty($_GET)) { // Get all tasks or create tasks

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks WHERE userid = :userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $taskArray = array();

            $rowCount = $query->rowCount();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database query error - " - $ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get tasks");
            $response->send();
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { // Create new task

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
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint not found");
    $response->send();
    exit;
}
