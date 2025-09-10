<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Проверка прав
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}
include_once('../../includes/db.php');

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE is_new = 1");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['count' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
    error_log("new_orders_count error: " . $e->getMessage());
}
