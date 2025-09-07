<?php
session_start();
include_once('../../includes/db.php');

// Проверка роли
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

if (isset($_GET['id'], $_GET['product_id'])) {
    $id = (int)$_GET['id'];
    $product_id = (int)$_GET['product_id'];

    $stmt = $pdo->prepare("DELETE FROM product_quantity_prices WHERE id = ?");
    $stmt->execute([$id]);

    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Диапазон цен удалён'];
    header("Location: /admin/product/edit?id=" . $product_id);
    exit();
}

$_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверные параметры'];
header("Location: /admin/product/edit?id=" . $product_id);
exit();
