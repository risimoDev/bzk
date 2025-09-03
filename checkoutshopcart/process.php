<?php 
session_start();
require_once __DIR__ . '/../includes/db.php';

$functions_path = __DIR__ . '/../admin/buhgalt/functions.php';
if (file_exists($functions_path)) {
    require_once $functions_path;
} else {
    error_log("process.php: functions.php not found at $functions_path");
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: /cart?error=empty_cart");
    exit();
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$shipping_address = trim($_POST['shipping_address'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9\-\+\s]{6,20}$/', $phone) || !$shipping_address) {
    header("Location: /checkout?error=invalid_fields");
    exit();
}

$is_urgent = isset($_POST['is_urgent']) && $_POST['is_urgent'] == '1';
$promo_data = $_SESSION['promo_data'] ?? null;
$promo_discount = floatval($promo_data['discount'] ?? 0);

$user_id = $_SESSION['user_id'] ?? null;
$original_total_price = array_sum(array_column($cart, 'total_price'));

// Срочность
$total_price = $is_urgent ? $original_total_price * 1.5 : $original_total_price;
$urgent_fee = $is_urgent ? $original_total_price * 0.5 : 0;

// Применяем скидку
$total_price = max(0, $total_price - $promo_discount);

try {
    $pdo->beginTransaction();

    // Создание заказа
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, total_price, shipping_address, contact_info, is_urgent)
        VALUES (?, ?, ?, ?, ?)
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
        $is_urgent ? 1 : 0
    ]);

    $order_id = $pdo->lastInsertId();

    // Товары
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

    // Бухгалтерия — создаём запись в orders_accounting
    $stmt_accounting = $pdo->prepare("
        INSERT INTO orders_accounting (source, order_id, client_name, income, total_expense, estimated_expense, status, tax_amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    // По умолчанию total_expense & estimated_expense = 0, tax_amount = 0 (потом можно считать/обновлять)
    $stmt_accounting->execute([
        'site',
        $order_id,
        $name,
        $total_price,
        0,
        0,
        'unpaid',
        0
    ]);

    $order_accounting_id = $pdo->lastInsertId();

    // Рассчитываем предполагаемые расходы по материалам
    if (function_exists('calculate_estimated_expense')) {
        $estimated_expense = calculate_estimated_expense($pdo, $order_id);
        // Обновляем estimated_expense в accounting
        $stmt_update = $pdo->prepare("UPDATE orders_accounting SET estimated_expense = ? WHERE id = ?");
        $stmt_update->execute([$estimated_expense, $order_accounting_id]);

        // Создаём автоматическую запись расхода и обновляем total_expense
        if (function_exists('create_automatic_expense_record')) {
            if (!create_automatic_expense_record($pdo, $order_accounting_id, $estimated_expense)) {
                error_log("process.php: automatic expense record failed for order_accounting_id={$order_accounting_id}");
            }
        }
    }
    // ✅ Добавляем налог
    if (function_exists('calculate_and_save_tax')) {
        $tax_amount = calculate_and_save_tax($pdo, $order_accounting_id, $total_price);
        error_log("process.php: налог для order_accounting_id={$order_accounting_id} = {$tax_amount}");
    }
    else {
        error_log("process.php: calculate_estimated_expense not defined");
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

header("Location: /checkoutshopcart/confirmation?order_id=" . $order_id);
exit();
