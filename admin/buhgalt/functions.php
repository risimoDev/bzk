<?php
// admin/buhgalt/functions.php

function calculate_profit($income, $total_expense) {
    return $income - $total_expense;
}

function get_order_profit($pdo, $order_accounting_id) {
    $stmt = $pdo->prepare("SELECT income, total_expense FROM orders_accounting WHERE id = ?");
    $stmt->execute([$order_accounting_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        return calculate_profit($order['income'], $order['total_expense']);
    }
    return 0;
}

function get_total_income($pdo, $start_date = null, $end_date = null) {
    $sql = "SELECT SUM(income) as total FROM orders_accounting WHERE 1=1";
    $params = [];
    if ($start_date) {
        $sql .= " AND created_at >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        // Предполагаем, что дата окончания включает весь день
        $sql .= " AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

function get_total_expense($pdo, $start_date = null, $end_date = null) {
    $sql = "SELECT SUM(total_expense) as total FROM orders_accounting WHERE 1=1";
    $params = [];
    if ($start_date) {
        $sql .= " AND created_at >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql .= " AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

function get_expenses_by_category($pdo, $start_date = null, $end_date = null) {
    $sql = "SELECT ec.name as category_name, SUM(oe.amount) as total_expense
            FROM order_expenses oe
            JOIN orders_accounting oa ON oe.order_accounting_id = oa.id
            LEFT JOIN expenses_categories ec ON oe.category_id = ec.id
            WHERE 1=1";
    $params = [];
    if ($start_date) {
        $sql .= " AND oa.created_at >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql .= " AND oa.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date;
    }
    $sql .= " GROUP BY ec.name ORDER BY total_expense DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_orders_with_finances($pdo, $start_date = null, $end_date = null, $page = 1, $limit = 20) {
    $offset = ($page - 1) * $limit;

    $sql = "SELECT oa.*, o.total_price as site_order_total, u.name as client_name_from_order
            FROM orders_accounting oa
            LEFT JOIN orders o ON oa.order_id = o.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE 1=1";
    $params = [];

    if ($start_date) {
        $sql .= " AND oa.created_at >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql .= " AND oa.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date;
    }

    $sql .= " ORDER BY oa.created_at DESC LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    
    // Привязываем обычные параметры
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    
    // Привязываем LIMIT и OFFSET как целые числа
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_total_orders_count($pdo, $start_date = null, $end_date = null) {
    $sql = "SELECT COUNT(*) as total FROM orders_accounting oa WHERE 1=1";
    $params = [];
    if ($start_date) {
        $sql .= " AND oa.created_at >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql .= " AND oa.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

function calculate_estimated_expense($pdo, $order_id) {
    error_log("Starting calculate_estimated_expense for order_id: " . $order_id);
    
    // 1. Получаем ИТОГОВУЮ сумму заказа с промокодом из таблицы orders
    $stmt_order = $pdo->prepare("SELECT total_price FROM orders WHERE id = ?");
    $stmt_order->execute([$order_id]);
    $order_total = $stmt_order->fetchColumn();
    
    error_log("Order total price (with promo): " . $order_total);
    
    // 2. Рассчитываем расходы как процент от итоговой суммы
    // Настройте этот процент под ваш бизнес (30% - пример)
    $expense_percentage = 0.30; // 30% - себестоимость материалов
    
    $total_estimated_expense = $order_total * $expense_percentage;
    
    $final_result = round($total_estimated_expense, 2);
    error_log("Final estimated expense for order $order_id: $final_result ($expense_percentage% of $order_total)");
    
    return $final_result;
}


function create_automatic_expense_record($pdo, $order_accounting_id, $estimated_expense, $description = "Автоматически рассчитанные расходы на материалы") {
    if ($estimated_expense <= 0) {
        error_log("INFO: Skipping automatic expense creation for order_accounting_id $order_accounting_id because estimated_expense is $estimated_expense.");
        return false;
    }

    try {
        // Проверим, существует ли категория "Автоматические расходы" или создадим её
        $stmt_check_cat = $pdo->prepare("SELECT id FROM expenses_categories WHERE name = ?");
        $stmt_check_cat->execute(['Автоматические расходы']);
        $category = $stmt_check_cat->fetch(PDO::FETCH_ASSOC);
        
        $category_id = null;
        if ($category) {
            $category_id = $category['id'];
        } else {
            // Создаем категорию, если её нет
            $stmt_create_cat = $pdo->prepare("INSERT INTO expenses_categories (name) VALUES (?)");
            $stmt_create_cat->execute(['Автоматические расходы']);
            $category_id = $pdo->lastInsertId();
            error_log("INFO: Created new expense category 'Автоматические расходы' with ID $category_id.");
        }
        
        // Вставляем запись в order_expenses
        $stmt_insert = $pdo->prepare("
            INSERT INTO order_expenses (order_accounting_id, category_id, amount, description, expense_date) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt_insert->execute([$order_accounting_id, $category_id, $estimated_expense, $description]);
        
        // Обновляем total_expense в orders_accounting
        // Сначала получим текущее значение total_expense
        $stmt_get_current = $pdo->prepare("SELECT total_expense FROM orders_accounting WHERE id = ?");
        $stmt_get_current->execute([$order_accounting_id]);
        $current_total_expense = $stmt_get_current->fetchColumn() ?? 0;
        
        $new_total_expense = $current_total_expense + $estimated_expense;
        
        $stmt_update_accounting = $pdo->prepare("UPDATE orders_accounting SET total_expense = ? WHERE id = ?");
        $stmt_update_accounting->execute([$new_total_expense, $order_accounting_id]);
        
        error_log("SUCCESS: Automatic expense record created for order_accounting_id $order_accounting_id. Amount: $estimated_expense. New total_expense: $new_total_expense.");
        return true;
        
    } catch (Exception $e) {
        error_log("ERROR in create_automatic_expense_record: " . $e->getMessage());
        return false;
    }
}

?>