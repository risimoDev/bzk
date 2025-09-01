<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

$promo_code = trim($_POST['promo_code'] ?? '');
if (empty($promo_code)) {
    echo json_encode(['success' => false, 'message' => 'Введите промокод']);
    exit();
}

// Промокод
$stmt = $pdo->prepare("SELECT * FROM promocodes WHERE code = ? AND is_active = 1");
$stmt->execute([$promo_code]);
$promo = $stmt->fetch();

if (!$promo) {
    echo json_encode(['success' => false, 'message' => 'Промокод не найден']);
    exit();
}

$now = new DateTime();
if ($promo['start_date'] && new DateTime($promo['start_date']) > $now) {
    echo json_encode(['success' => false, 'message' => 'Промокод еще не активен']);
    exit();
}
if ($promo['end_date'] && new DateTime($promo['end_date']) < $now) {
    echo json_encode(['success' => false, 'message' => 'Промокод истек']);
    exit();
}
if ($promo['usage_limit'] && $promo['used_count'] >= $promo['usage_limit']) {
    echo json_encode(['success' => false, 'message' => 'Лимит использований исчерпан']);
    exit();
}

$cart_total = array_sum(array_column($_SESSION['cart'] ?? [], 'total_price'));

// Срочность
if ($_SESSION['is_urgent_order'] ?? false) {
    $cart_total *= 1.5;
}

// Скидка
$discount = ($promo['discount_type'] === 'percentage')
    ? $cart_total * ($promo['discount_value'] / 100)
    : min($promo['discount_value'], $cart_total);

// Сохраняем
$_SESSION['promo_data'] = [
    'code' => $promo['code'],
    'discount' => $discount,
    'discount_type' => $promo['discount_type'],
    'discount_value' => $promo['discount_value']
];

echo json_encode([
    'success' => true,
    'discount' => $discount,
    'new_total' => max(0, $cart_total - $discount),
    'message' => 'Промокод применен!'
]);
