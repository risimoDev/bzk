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
    try { // <-- Открывающая скобка try

        // 1. Добавляем запись в orders_accounting
        // Явно указываем все значения через placeholder'ы для единообразия и лучшей отладки
        $stmt_accounting = $pdo->prepare("
            INSERT INTO orders_accounting (source, order_id, client_name, income, total_expense, estimated_expense, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $client_name_for_accounting = $order_info['client_name'] ?? $name;
        $income_for_accounting = $order_info['total_price'];
        
        error_log("DEBUG: About to execute accounting insert with values: source='site', order_id=$order_id, client_name='$client_name_for_accounting', income=$income_for_accounting, total_expense=0, estimated_expense=0, status='unpaid'");
        
        $execute_result = $stmt_accounting->execute([
            'site', // source
            $order_id, 
            $client_name_for_accounting,
            $income_for_accounting, 
            0, // total_expense
            0, // estimated_expense
            'unpaid' // status
        ]);
        
        if ($execute_result) {
            $order_accounting_id = $pdo->lastInsertId();
            error_log("SUCCESS: Accounting record created with ID: " . $order_accounting_id);
        } else {
            $error_info = $stmt_accounting->errorInfo();
            error_log("CRITICAL ERROR: Failed to create accounting record.");
            error_log("SQL Query: " . $stmt_accounting->queryString); // Это может не работать в некоторых версиях PDO
            error_log("PDO Error Info: " . print_r($error_info, true));
            error_log("Data being inserted: ");
            error_log("  source: site");
            error_log("  order_id: " . $order_id);
            error_log("  client_name: " . $client_name_for_accounting);
            error_log("  income: " . $income_for_accounting);
            error_log("  total_expense: 0");
            error_log("  estimated_expense: 0");
            error_log("  status: unpaid");
            
            // Проверим, существует ли заказ в основной таблице
            $stmt_check_order = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
            $stmt_check_order->execute([$order_id]);
            $order_exists = $stmt_check_order->fetch();
            if ($order_exists) {
                error_log("INFO: Order with ID $order_id EXISTS in the main orders table.");
            } else {
                error_log("WARNING: Order with ID $order_id DOES NOT EXIST in the main orders table. Foreign key constraint might fail.");
            }
            
            // Продолжаем выполнение, чтобы не сломать основной процесс заказа
            // Но установим флаг, что бухгалтерия не создана
            $order_accounting_id = null;
        }
        
        // 2. Рассчитываем estimated_expense
        // Убедитесь, что функция calculate_estimated_expense существует в functions.php
        if (function_exists('calculate_estimated_expense') && $order_accounting_id !== null) {
            error_log("Function calculate_estimated_expense exists, calling it...");
            $estimated_expense = calculate_estimated_expense($pdo, $order_id);
            error_log("Estimated expense calculated: " . $estimated_expense);
            
            // 3. Обновляем запись в orders_accounting с рассчитанным estimated_expense
            $stmt_update = $pdo->prepare("UPDATE orders_accounting SET estimated_expense = ? WHERE id = ?");
            $update_result = $stmt_update->execute([$estimated_expense, $order_accounting_id]);
            
            if ($update_result) {
                error_log("Accounting record updated with estimated expense.");
                
                // 4. --- Добавлено: Создаем автоматическую запись о расходе ---
                if (function_exists('create_automatic_expense_record')) {
                    $auto_expense_result = create_automatic_expense_record($pdo, $order_accounting_id, $estimated_expense);
                    if ($auto_expense_result) {
                        error_log("SUCCESS: Automatic expense record created in order_expenses for order_accounting_id $order_accounting_id.");
                    } else {
                        error_log("INFO: Automatic expense record was not created or skipped for order_accounting_id $order_accounting_id.");
                    }
                } else {
                    error_log("WARNING: Function create_automatic_expense_record does not exist!");
                }
                // --- Конец добавленного кода ---
                
            } else {
                $error_info = $stmt_update->errorInfo();
                error_log("ERROR: Failed to update accounting record with estimated expense. Error: " . print_r($error_info, true));
            }
        } else {
            if ($order_accounting_id === null) {
                error_log("SKIPPED: Estimated expense calculation/update because accounting record was not created.");
            } else {
                error_log("ERROR: Function calculate_estimated_expense does not exist!");
            }
        }
    } catch (Exception $e) { // <-- Добавлен блок catch
        // Логируем ошибку, но не прерываем основной процесс заказа
        error_log("ERROR при добавлении заказа в бухгалтерию: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        // Можно добавить уведомление для администратора
    }
} else {
    error_log("ERROR: Could not fetch order info for accounting.");
}
// --- Конец добавленного кода ---
// --- Добавлено: Создание чата для нового заказа ---
// Убедитесь, что файл функций чата подключен
$chat_functions_path = __DIR__ . '/../includes/chat_functions.php';
if (file_exists($chat_functions_path)) {
    include_once $chat_functions_path;
    error_log("Chat functions file included successfully.");
    
    // Создаем чат для нового заказа
    if (function_exists('create_chat_for_order')) {
        $chat_id = create_chat_for_order($pdo, $order_id);
        if ($chat_id) {
            error_log("SUCCESS: Chat created for order $order_id with ID $chat_id.");
        } else {
            error_log("INFO/WARNING: Chat was not created for order $order_id (might already exist or error occurred).");
        }
    } else {
        error_log("ERROR: Function 'create_chat_for_order' not found in chat functions file.");
    }
} else {
    error_log("ERROR: Chat functions file not found at: " . $chat_functions_path);
}
// Очистка корзины
unset($_SESSION['cart']);

header("Location: /checkoutshopcart/confirmation");
exit();

?>