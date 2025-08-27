<?php
// /ajax/check_promo_code.php
session_start();

error_log("PROMO SESSION ID: " . session_id());
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован.']);
    exit();
}

include_once __DIR__ . '/../includes/db.php';

$promo_code = trim($_POST['promo_code'] ?? '');
error_log("PROMO CHECK: Code = '$promo_code', User = " . ($_SESSION['user_id'] ?? 'guest'));

if (empty($promo_code)) {
    echo json_encode(['success' => false, 'message' => 'Промокод не указан.']);
    exit();
}

// Проверка промокода в БД
$stmt = $pdo->prepare("
    SELECT id, code, discount_type, discount_value, usage_limit, used_count, start_date, end_date, is_active
    FROM promocodes 
    WHERE code = ? AND is_active = 1
");
$stmt->execute([$promo_code]);
$promo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$promo) {
    echo json_encode(['success' => false, 'message' => 'Промокод не найден или не активен.']);
    exit();
}

// Проверка срока действия
$current_time = new DateTime();
if ($promo['start_date'] && new DateTime($promo['start_date']) > $current_time) {
    echo json_encode(['success' => false, 'message' => 'Промокод еще не действует.']);
    exit;
}
if ($promo['end_date'] && new DateTime($promo['end_date']) < $current_time) {
    echo json_encode(['success' => false, 'message' => 'Срок действия промокода истек.']);
    exit;
}

// Проверка лимита использований
if ($promo['usage_limit'] !== null && $promo['used_count'] >= $promo['usage_limit']) {
    echo json_encode(['success' => false, 'message' => 'Лимит использований промокода исчерпан.']);
    exit;
}

// Расчет общей суммы корзины с учетом атрибутов
$cart_total = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $stmt = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $item_total = $product['base_price'] * $item['quantity'];
            
            // Расчет стоимости атрибутов
            if (!empty($item['attributes']) && is_array($item['attributes'])) {
                foreach ($item['attributes'] as $attribute_id => $value_id) {
                    $stmt_attr = $pdo->prepare("
                        SELECT price_modifier 
                        FROM attribute_values 
                        WHERE id = ?
                    ");
                    $stmt_attr->execute([$value_id]);
                    $attribute = $stmt_attr->fetch(PDO::FETCH_ASSOC);
                    
                    if ($attribute && $attribute['price_modifier'] > 0) {
                        $item_total += $attribute['price_modifier'] * $item['quantity'];
                    }
                }
            }
            
            $cart_total += $item_total;
        }
    }
}

// Учитываем срочный заказ
$is_urgent = $_SESSION['is_urgent_order'] ?? false;
$urgent_multiplier = $is_urgent ? 1.5 : 1;
$cart_total *= $urgent_multiplier;

$discount_amount = 0;
if ($promo['discount_type'] === 'percentage') {
    $discount_amount = $cart_total * ($promo['discount_value'] / 100);
} elseif ($promo['discount_type'] === 'fixed') {
    // Ограничиваем фиксированную скидку общей суммой корзины
    $discount_amount = min($promo['discount_value'], $cart_total);
}

// Сохраняем информацию о промокоде в сессии
$_SESSION['applied_promo_code'] = $promo['code'];
$_SESSION['promo_discount_amount'] = $discount_amount;

// Важно: явно сохраняем сессию
session_write_close();

error_log("PROMO RESULT: Cart total = $cart_total, Discount = $discount_amount, Success = true");
error_log("SESSION SET: applied_promo_code = " . $promo['code']);
error_log("SESSION SET: promo_discount_amount = " . $discount_amount);

echo json_encode([
    'success' => true, 
    'message' => 'Промокод успешно применен!',
    'discount_amount' => $discount_amount,
    'cart_total' => $cart_total
]);
?>