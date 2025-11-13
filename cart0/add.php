<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

// CSRF verification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /catalog");
    exit();
}

verify_csrf();

$product_id = (int) ($_POST['product_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));
$attributes = $_POST['attributes'] ?? [];

// Получаем товар
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product || (!empty($product['is_hidden']) && (int) $product['is_hidden'] === 1)) {
    header("Location: /catalog?error=product_not_found");
    exit();
}

// Считаем цену
$total_price = $product['base_price'] * $quantity;

if (!empty($attributes)) {
    foreach ($attributes as $value_id) {
        $stmt = $pdo->prepare("SELECT price_modifier FROM attribute_values WHERE id = ?");
        $stmt->execute([$value_id]);
        $modifier = $stmt->fetchColumn();
        $total_price += ($modifier ?? 0) * $quantity;
    }
}

// Корзина
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Если товар с теми же атрибутами уже есть → увеличиваем количество
$found = false;
foreach ($_SESSION['cart'] as &$item) {
    if ($item['product_id'] == $product_id && $item['attributes'] == $attributes) {
        $item['quantity'] += $quantity;
        $item['total_price'] += $total_price;
        $found = true;
        break;
    }
}
unset($item);

if (!$found) {
    $_SESSION['cart'][] = [
        'product_id' => $product_id,
        'quantity' => $quantity,
        'attributes' => $attributes,
        'total_price' => $total_price,
    ];
}

header("Location: /cart");
exit();
