<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/telegram.php';
require_once __DIR__ . '/../includes/chat_functions.php';

$functions_path = __DIR__ . '/../admin/buhgalt/functions.php';
if (file_exists($functions_path)) {
    require_once $functions_path;
} else {
    error_log("process.php: functions.php not found at $functions_path");
}

// Verify CSRF token
verify_csrf();

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: /cart?error=empty_cart");
    exit();
}

$name = sanitize_text($_POST['name'] ?? '', 100);
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone = sanitize_text($_POST['phone'] ?? '', 20);
$shipping_address = sanitize_text($_POST['shipping_address'] ?? '', 500);
$comment = sanitize_text($_POST['comment'] ?? '', 1000);

// Comprehensive input validation
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = 'Имя должно содержать минимум 2 символа';
}

if (!$email) {
    $errors[] = 'Введите корректный email адрес';
}

if (empty($phone)) {
    $errors[] = 'Введите номер телефона';
}

if (empty($shipping_address) || strlen($shipping_address) < 10) {
    $errors[] = 'Адрес доставки должен содержать минимум 10 символов';
}

if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_data'] = [
        'name' => $name,
        'email' => $_POST['email'] ?? '',
        'phone' => $phone,
        'shipping_address' => $shipping_address,
        'comment' => $comment
    ];
    header("Location: /checkout?error=validation_failed");
    exit();
}

$is_urgent = isset($_POST['is_urgent']) && $_POST['is_urgent'] == '1';
$promo_data = $_SESSION['promo_data'] ?? null;
$promo_discount = floatval($promo_data['discount'] ?? 0);

$user_id = $_SESSION['user_id'] ?? null;

/**
 * ✅ Пересчитываем корзину (как в checkout.php)
 */
$original_total_price = 0;
$cart_items = [];

foreach ($cart as $item) {
    // 1. Базовая цена
    $stmt = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
    $stmt->execute([$item['product_id']]);
    $base_price = (float) $stmt->fetchColumn();

    // 2. Цена с учётом диапазона
    $unit_price = getUnitPrice($pdo, $item['product_id'], $item['quantity']);

    // 3. Модификаторы атрибутов
    $total_attributes_price = 0;
    foreach ($item['attributes'] as $attribute_id => $value_id) {
        $stmt = $pdo->prepare("
            SELECT av.price_modifier
            FROM attribute_values av
            WHERE av.id = ?
        ");
        $stmt->execute([$value_id]);
        $mod = (float) $stmt->fetchColumn();
        $total_attributes_price += $mod;
    }

    // 4. Итог по позиции
    $item_total_price = ($unit_price + $total_attributes_price) * $item['quantity'];
    $original_total_price += $item_total_price;

    // 5. Собираем новый массив
    $cart_items[] = [
        'product_id' => $item['product_id'],
        'quantity' => $item['quantity'],
        'attributes' => $item['attributes'],
        'unit_price' => $unit_price,
        'attr_price' => $total_attributes_price,
        'total_price' => $item_total_price
    ];
}

// Срочность
$total_price = $is_urgent ? $original_total_price * 1.5 : $original_total_price;
$urgent_fee = $is_urgent ? $original_total_price * 0.5 : 0;

// Применяем скидку
$total_price = max(0, $total_price - $promo_discount);

try {
    $pdo->beginTransaction();

    // Создание заказа
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, total_price, shipping_address, contact_info, is_urgent, is_new)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $total_price,
        $shipping_address,
        json_encode([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'comment' => $comment,
            'is_urgent' => $is_urgent,
            'original_total_price' => $original_total_price,
            'urgent_fee' => $urgent_fee,
            'promo_data' => $promo_data
        ]),
        $is_urgent ? 1 : 0,
        1

    ]);

    $order_id = $pdo->lastInsertId();

    // Товары
    foreach ($cart_items as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price, attributes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['total_price'], // 👈 корректная сумма
            json_encode($item['attributes'])
        ]);
    }

    // Промокод
    if ($promo_data) {
        $stmt_promo = $pdo->prepare("
            INSERT INTO order_promocodes (order_id, promo_code, discount_type, discount_value, applied_discount) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt_promo->execute([
            $order_id,
            $promo_data['code'],
            $promo_data['discount_type'],
            $promo_data['discount_value'],
            $promo_discount
        ]);

        $stmt_update = $pdo->prepare("UPDATE promocodes SET used_count = used_count + 1 WHERE code = ?");
        $stmt_update->execute([$promo_data['code']]);
    }

    // Бухгалтерия
    $stmt_accounting = $pdo->prepare("
        INSERT INTO orders_accounting (source, order_id, client_name, income, total_expense, estimated_expense, status, tax_amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_accounting->execute([
        'site',
        $order_id,
        $name,
        $total_price, // 👈 корректный доход
        0,
        0,
        'unpaid',
        0
    ]);

    $order_accounting_id = $pdo->lastInsertId();

    // Себестоимость
    if (function_exists('calculate_estimated_expense')) {
        $estimated_expense = calculate_estimated_expense($pdo, $order_id);
        $stmt_update = $pdo->prepare("UPDATE orders_accounting SET estimated_expense = ? WHERE id = ?");
        $stmt_update->execute([$estimated_expense, $order_accounting_id]);

        if (function_exists('create_automatic_expense_record')) {
            if (!create_automatic_expense_record($pdo, $order_accounting_id, $estimated_expense)) {
                error_log("process.php: automatic expense record failed for order_accounting_id={$order_accounting_id}");
            }
        }
    }

    // Налог
    if (function_exists('calculate_and_save_tax')) {
        $tax_amount = calculate_and_save_tax($pdo, $order_accounting_id, $total_price);
        error_log("process.php: налог для order_accounting_id={$order_accounting_id} = {$tax_amount}");
    } else {
        error_log("process.php: calculate_and_save_tax not defined");
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Checkout error: " . $e->getMessage());
    header("Location: /checkout?error=processing_failed");
    exit();
}

// Очистка
unset($_SESSION['cart'], $_SESSION['is_urgent_order'], $_SESSION['promo_data']);

// Уведомления: создаём чат (если его ещё нет) и рассылаем уведомление админам/менеджерам
try {
    // Создание чата с назначением менеджера (один раз)
    create_chat_for_order($pdo, (int) $order_id);
} catch (Exception $e) {
    error_log('create_chat_for_order error: ' . $e->getMessage());
}

try {
    sendNewSiteOrderNotification((int) $order_id);
} catch (Exception $e) {
    error_log('sendNewSiteOrderNotification error: ' . $e->getMessage());
}

header("Location: /checkoutshopcart/confirmation?order_id=" . $order_id);
exit();
