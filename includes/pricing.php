<?php
session_start();
require_once __DIR__ . '/db.php';

// Проверка роли
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

/**
 * Получить цену за единицу товара в зависимости от количества
 *
 * @param PDO $pdo
 * @param int $product_id
 * @param int $quantity
 * @return float
 */
function getProductPriceByQuantity($pdo, $product_id, $quantity) {
    // Берем диапазон, который подходит
    $stmt = $pdo->prepare("
        SELECT price 
        FROM product_quantity_prices
        WHERE product_id = ?
          AND min_qty <= ?
          AND (max_qty IS NULL OR max_qty >= ?)
        ORDER BY min_qty DESC
        LIMIT 1
    ");
    $stmt->execute([$product_id, $quantity, $quantity]);
    $price = $stmt->fetchColumn();

    if ($price !== false) {
        return (float)$price;
    }

    // Если диапазона нет — берем базовую цену из products
    $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    return (float)$stmt->fetchColumn();
}
