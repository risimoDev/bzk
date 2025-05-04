<?php
session_start();

// Подключение к базе данных
include_once('../includes/db.php');

$product_id = $_POST['product_id'] ?? null;
$quantity = $_POST['quantity'] ?? 1;
$attributes = $_POST['attributes'] ?? [];

if (!$product_id || !$quantity || empty($attributes)) {
    header("Location: /catalog");
    exit();
}

// Получение информации о товаре
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: /catalog");
    exit();
}

// Вычисление стоимости с учетом характеристик
$total_price = $product['base_price'] * $quantity;
foreach ($attributes as $attribute_id => $value_id) {
    $stmt = $pdo->prepare("SELECT price_modifier FROM attribute_values WHERE id = ?");
    $stmt->execute([$value_id]);
    $modifier = $stmt->fetchColumn();
    $total_price += $modifier * $quantity;
}

// Добавление товара в корзину
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$_SESSION['cart'][] = [
    'product_id' => $product_id,
    'quantity' => $quantity,
    'attributes' => $attributes,
    'total_price' => $total_price,
];

header("Location: /cart");
exit();
?>