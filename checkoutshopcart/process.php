<?php
session_start();

// Подключение к базе данных
include_once __DIR__ . '/../includes/db.php';

// --- Добавлено: Проверка подключения functions.php ---
$functions_path = __DIR__ . '/../admin/buhgalt/functions.php';
if (file_exists($functions_path)) {
    include_once $functions_path;
    error_log("Functions file included successfully.");
} else {
    error_log("ERROR: Functions file not found at: " . $functions_path);
    // Можно установить флаг или обработать ошибку по-другому
}
// --- Конец добавленного кода ---

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
// --- Добавлено логирование для отладки ---
error_log("Order created with ID: " . $order_id);
// --- Конец логирования ---

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
        $item['total_price'], // Это общая цена за quantity единиц
        json_encode($item['attributes'])
    ]);
}

// --- Добавлено: Автоматическое добавление заказа в бухгалтерию ---
// Получаем информацию о заказе для бухгалтерии
$stmt_order = $pdo->prepare("SELECT o.total_price, u.name as client_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt_order->execute([$order_id]);
$order_info = $stmt_order->fetch(PDO::FETCH_ASSOC);

// --- Добавлено логирование для отладки ---
error_log("Order info fetched: " . print_r($order_info, true));
// --- Конец логирования ---

if ($order_info) {
    try {
        // 1. Добавляем запись в orders_accounting
        $stmt_accounting = $pdo->prepare("
            INSERT INTO orders_accounting (source, order_id, client_name, income, total_expense, estimated_expense, status) 
            VALUES ('site', ?, ?, ?, 0, 0, 'unpaid')
        ");
        $stmt_accounting->execute([
            $order_id, 
            $order_info['client_name'] ?? $name, // Используем имя из формы, если нет юзера
            $order_info['total_price'], 
            $order_info['total_price'] // income
        ]);
        
        $order_accounting_id = $pdo->lastInsertId();
        // --- Добавлено логирование для отладки ---
        error_log("Accounting record created with ID: " . $order_accounting_id);
        // --- Конец логирования ---
        
        // 2. Рассчитываем estimated_expense
        // Убедитесь, что функция calculate_estimated_expense существует в functions.php
        if (function_exists('calculate_estimated_expense')) {
            error_log("Function calculate_estimated_expense exists, calling it...");
            $estimated_expense = calculate_estimated_expense($pdo, $order_id);
            error_log("Estimated expense calculated: " . $estimated_expense);
            
            // 3. Обновляем запись в orders_accounting с рассчитанным estimated_expense
            $stmt_update = $pdo->prepare("UPDATE orders_accounting SET estimated_expense = ? WHERE id = ?");
            $stmt_update->execute([$estimated_expense, $order_accounting_id]);
            error_log("Accounting record updated with estimated expense.");
        } else {
            error_log("ERROR: Function calculate_estimated_expense does not exist!");
        }
        
    } catch (Exception $e) {
        // Логируем ошибку, но не прерываем основной процесс заказа
        error_log("ERROR при добавлении заказа в бухгалтерию: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        // Можно добавить уведомление для администратора
    }
} else {
    error_log("ERROR: Could not fetch order info for accounting.");
}
// --- Конец добавленного кода ---

// Очистка корзины
unset($_SESSION['cart']);

header("Location: /checkoutshopcart/confirmation");
exit();

?>