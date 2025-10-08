<?php
session_start();
require_once '../../includes/security.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}

include_once('../../includes/db.php');
// Возможные сценарии:
// - POST { all: 1 } пометить все новые заказы как прочитанные
// - POST { order_id: 123 } пометить конкретный заказ

// Verify CSRF token
verify_csrf();

try {
    if (isset($_POST['all']) && $_POST['all'] == '1') {
        $stmt = $pdo->prepare("UPDATE orders SET is_new = 0 WHERE is_new = 1");
        $stmt->execute();
    } elseif (isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("UPDATE orders SET is_new = 0 WHERE id = ?");
        $stmt->execute([$order_id]);
    } else {
        // ничего не передали — ничего не делаем
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
    error_log("mark_orders_seen error: " . $e->getMessage());
}
