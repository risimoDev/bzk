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
    // --- Добавлено логирование для отладки ---
    error_log("calculate_estimated_expense called with order_id: " . $order_id);
    // --- Конец логирования ---
    
    $total_estimated_expense = 0.00;
    
    // Получаем все товары в заказе
    $stmt = $pdo->prepare("
        SELECT oi.product_id, oi.quantity, oi.attributes
        FROM order_items oi
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // --- Добавлено логирование для отладки ---
    error_log("Found " . count($order_items) . " items in order " . $order_id);
    // --- Конец логирования ---
    
    foreach ($order_items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        
        // --- Добавлено логирование для отладки ---
        error_log("Processing product_id: " . $product_id . ", quantity: " . $quantity);
        // --- Конец логирования ---
        
        // Получаем расходники для этого товара
        $stmt_expenses = $pdo->prepare("
            SELECT pe.quantity_per_unit, pe.cost_per_unit
            FROM product_expenses pe
            WHERE pe.product_id = ?
        ");
        $stmt_expenses->execute([$product_id]);
        $product_expenses = $stmt_expenses->fetchAll(PDO::FETCH_ASSOC);
        
        // --- Добавлено логирование для отладки ---
        error_log("Found " . count($product_expenses) . " expenses for product " . $product_id);
        // --- Конец логирования ---
        
        foreach ($product_expenses as $expense) {
            $quantity_per_unit = $expense['quantity_per_unit'];
            $cost_per_unit = $expense['cost_per_unit'];
            
            // --- Добавлено логирование для отладки ---
            error_log("Expense: quantity_per_unit=" . $quantity_per_unit . ", cost_per_unit=" . ($cost_per_unit ?? 'NULL'));
            // --- Конец логирования ---
            
            // Если себестоимость указана, считаем расход
            if ($cost_per_unit !== null) {
                $item_expense = $quantity_per_unit * $cost_per_unit * $quantity;
                $total_estimated_expense += $item_expense;
                
                // --- Добавлено логирование для отладки ---
                error_log("Added expense: " . $item_expense . ". Running total: " . $total_estimated_expense);
                // --- Конец логирования ---
            }
        }
    }
    
    $result = round($total_estimated_expense, 2);
    // --- Добавлено логирование для отладки ---
    error_log("Final estimated expense for order " . $order_id . ": " . $result);
    // --- Конец логирования ---
    return $result;
}
?>