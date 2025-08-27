<?php
session_start();
error_log("PROCESS SESSION ID: " . session_id());
error_log("PROCESS SESSION: " . print_r($_SESSION, true));
// Подключение к базе данных
include_once __DIR__ . '/../includes/db.php';

$functions_path = __DIR__ . '/../admin/buhgalt/functions.php';
if (file_exists($functions_path)) {
    include_once $functions_path;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: /cart");
    exit();
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$shipping_address = $_POST['shipping_address'] ?? '';
$comment = $_POST['comment'] ?? '';

// Получение данных о срочном заказе и промокоде
$is_urgent = isset($_POST['is_urgent']) && $_POST['is_urgent'] == '1';
$promo_data = $_SESSION['promo_data'] ?? null;
$promo_discount = $promo_data['discount'] ?? 0;

if (!$name || !$email || !$phone || !$shipping_address) {
    header("Location: /checkout?error=missing_fields");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;

// Расчет итоговой суммы
$original_total_price = array_sum(array_column($cart, 'total_price'));

// Применяем срочный заказ (+50%)
if ($is_urgent) {
    $total_price = $original_total_price * 1.5;
    $urgent_fee = $original_total_price * 0.5;
} else {
    $total_price = $original_total_price;
    $urgent_fee = 0;
}

// Применяем скидку по промокоду
$total_price -= $promo_discount;
$total_price = max(0, $total_price);

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

// Сохранение информации о промокоде
if ($promo_data) {
    try {
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
        
        // Увеличиваем счетчик использований
        $stmt_update = $pdo->prepare("UPDATE promocodes SET used_count = used_count + 1 WHERE code = ?");
        $stmt_update->execute([$promo_data['code']]);
    } catch (Exception $e) {
        error_log("Error saving promo code: " . $e->getMessage());
    }
}

// Автоматическое добавление в бухгалтерию
try {
    $stmt_order = $pdo->prepare("SELECT o.total_price, u.name as client_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt_order->execute([$order_id]);
    $order_info = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if ($order_info) {
        $stmt_accounting = $pdo->prepare("
            INSERT INTO orders_accounting (source, order_id, client_name, income, total_expense, estimated_expense, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt_accounting->execute([
            'site',
            $order_id, 
            $order_info['client_name'] ?? $name,
            $total_price, 
            0,
            0,
            'unpaid'
        ]);
        
        $order_accounting_id = $pdo->lastInsertId();
        
        // Рассчитываем и обновляем расходы
        if (function_exists('calculate_estimated_expense')) {
            $estimated_expense = calculate_estimated_expense($pdo, $order_id);
            $stmt_update = $pdo->prepare("UPDATE orders_accounting SET estimated_expense = ? WHERE id = ?");
            $stmt_update->execute([$estimated_expense, $order_accounting_id]);
            
            if (function_exists('create_automatic_expense_record')) {
                create_automatic_expense_record($pdo, $order_accounting_id, $estimated_expense);
            }
        }
    }
} catch (Exception $e) {
    error_log("Accounting error: " . $e->getMessage());
}

// --- Добавлено: Создание чата для нового заказа ---
$chat_functions_path = __DIR__ . '/../includes/chat_functions.php';
if (file_exists($chat_functions_path)) {
    include_once $chat_functions_path;
    error_log("Chat functions file included successfully.");
    
    if (function_exists('create_chat_for_order')) {
        $chat_id = create_chat_for_order($pdo, $order_id);
        if ($chat_id) {
            error_log("SUCCESS: Chat created for order $order_id with ID $chat_id.");
        }
    }
}
// --- Конец добавленного кода ---

// --- Добавлено: Очистка данных о срочном заказе и промокоде ---
unset($_SESSION['cart']);
unset($_SESSION['is_urgent_order']);
unset($_SESSION['promo_data']);

// --- Конец добавленного кода ---

header("Location: /checkoutshopcart/confirmation");
exit();
?>