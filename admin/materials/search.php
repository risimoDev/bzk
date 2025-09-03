<?php
// admin/materials/search.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Простая авторизация: только логин и роль admin/manager (подкорректируй под проект)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

include_once __DIR__ . '/../../includes/db.php';

$q = trim($_GET['q'] ?? '');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($limit <= 0) $limit = 20;

if ($q === '') {
    echo json_encode([]);
    exit;
}

try {
    // Ищем по имени или по SKU
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT id, name, sku, unit, COALESCE(cost_per_unit, 0) AS cost_per_unit, COALESCE(quantity_in_stock, 0) AS quantity_in_stock
        FROM materials
        WHERE name LIKE ? OR sku LIKE ?
        ORDER BY name ASC
        LIMIT ?
    ");
    $stmt->bindValue(1, $like, PDO::PARAM_STR);
    $stmt->bindValue(2, $like, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    error_log('materials/search.php error: ' . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
