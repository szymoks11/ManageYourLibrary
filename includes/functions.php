<?php
require_once '../config/database.php';

function executeQuery($query, $params = []) {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt;
}

function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function callProcedure($procedureName, $params = []) {
    $database = new Database();
    $db = $database->getConnection();
    $placeholders = implode(',', array_fill(0, count($params), '?'));
    $sql = "CALL $procedureName($placeholders)";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>