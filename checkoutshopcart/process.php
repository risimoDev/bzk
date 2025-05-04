<?php
session_start();

// Подключение к базе данных
include_once __DIR__ . '/../includes/db.php';

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: /cart");
    exit();
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$shipping_address = $_POST['shipping_address'] ?? '';

if (!$name || !$email || !$phone || !$shipping_address) {
    header("Location: /checkout?error=missing_fields");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$total_price = array_sum(array_column($cart, 'total_price'));

// Создание заказа
$stmt = $pdo->prepare("
    INSERT INTO orders (user_id, total_price, shipping_address, contact_info)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$user_id, $total_price, $shipping_address, json_encode(['name' => $name, 'email' => $email, 'phone' => $phone])]);

$order_id = $pdo->lastInsertId();

// Добавление товаров в заказ
foreach ($cart as $item) {
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price, attributes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $order_id,
        $item['product_id'],
        $item['quantity'],
        $item['total_price'],
        json_encode($item['attributes'])
    ]);
}

// Очистка корзины
unset($_SESSION['cart']);

header("Location: /checkoutshopcart/confirmation");
exit();
?>