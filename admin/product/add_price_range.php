<?php
session_start();
include_once('../../includes/db.php');
require_once '../../includes/security.php';
// Проверка роли
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $product_id = (int) $_POST['product_id'];
    $min_qty = (int) $_POST['min_qty'];
    $max_qty = $_POST['max_qty'] !== '' ? (int) $_POST['max_qty'] : null;
    $price = (float) $_POST['price'];

    if ($product_id && $min_qty && $price) {
        $stmt = $pdo->prepare("
            INSERT INTO product_quantity_prices (product_id, min_qty, max_qty, price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$product_id, $min_qty, $max_qty, $price]);

        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Диапазон цен добавлен'];
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заполните все обязательные поля'];
    }

    header("Location: /admin/product/edit?id=" . $product_id);
    exit();
}
