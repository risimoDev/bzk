<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}

include_once __DIR__ . '/../includes/db.php';

$promo_code = trim($_POST['promo_code'] ?? '');
if (empty($promo_code)) {
    echo json_encode(['success' => false, 'message' => 'Введите промокод']);
    exit();
}

// Проверяем промокод
$stmt = $pdo->prepare("SELECT * FROM promocodes WHERE code = ? AND is_active = 1");
$stmt->execute([$promo_code]);
$promo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$promo) {
    echo json_encode(['success' => false, 'message' => 'Промокод не найден']);
    exit();
}

// Проверяем срок действия
$now = new DateTime();
if ($promo['start_date'] && new DateTime($promo['start_date']) > $now) {
    echo json_encode(['success' => false, 'message' => 'Промокод еще не активен']);
    exit();
}
if ($promo['end_date'] && new DateTime($promo['end_date']) < $now) {
    echo json_encode(['success' => false, 'message' => 'Промокод истек']);
    exit();
}

// Проверяем лимит использований
if ($promo['usage_limit'] && $promo['used_count'] >= $promo['usage_limit']) {
    echo json_encode(['success' => false, 'message' => 'Лимит использований исчерпан']);
    exit();
}

// Рассчитываем корзину
$cart_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $stmt = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
    $stmt->execute([$item['product_id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $item_total = $product['base_price'] * $item['quantity'];
        
        // Добавляем стоимость атрибутов
        if (!empty($item['attributes'])) {
            foreach ($item['attributes'] as $value_id) {
                $stmt_attr = $pdo->prepare("SELECT price_modifier FROM attribute_values WHERE id = ?");
                $stmt_attr->execute([$value_id]);
                $attribute = $stmt_attr->fetch(PDO::FETCH_ASSOC);
                if ($attribute) {
                    $item_total += $attribute['price_modifier'] * $item['quantity'];
                }
            }
        }
        
        $cart_total += $item_total;
    }
}

// Учитываем срочный заказ
if ($_SESSION['is_urgent_order'] ?? false) {
    $cart_total *= 1.5;
}

// Рассчитываем скидку
$discount = 0;
if ($promo['discount_type'] === 'percentage') {
    $discount = $cart_total * ($promo['discount_value'] / 100);
} else {
    $discount = min($promo['discount_value'], $cart_total);
}

// Сохраняем в сессию
$_SESSION['promo_data'] = [
    'code' => $promo['code'],
    'discount' => $discount,
    'discount_type' => $promo['discount_type'],
    'discount_value' => $promo['discount_value']
];

echo json_encode([
    'success' => true,
    'discount' => $discount,
    'new_total' => $cart_total - $discount,
    'message' => 'Промокод применен!'
]);
?>