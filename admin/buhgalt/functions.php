<?php
// admin/buhgalt/functions.php

function calculate_profit($income, $total_expense, $tax_amount = 0) {
    return $income - ($total_expense + $tax_amount);
}

function get_order_profit($pdo, $order_accounting_id) {
    $stmt = $pdo->prepare("SELECT income, total_expense, tax_amount FROM orders_accounting WHERE id = ?");
    $stmt->execute([$order_accounting_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        return calculate_profit($order['income'], $order['total_expense'], $order['tax_amount']);
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
        $sql .= " AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

function get_total_expense($pdo, $start_date = null, $end_date = null) {
    $sql = "SELECT SUM(total_expense + tax_amount) as total FROM orders_accounting WHERE 1=1";
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
    $params = [];
    $where_order = "1=1";
    $where_general = "1=1";

    if ($start_date) {
        $where_order .= " AND oa.created_at >= ?";
        $where_general .= " AND ge.expense_date >= ?";
        $params[] = $start_date;
        $params[] = $start_date;
    }
    if ($end_date) {
        $where_order .= " AND oa.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $where_general .= " AND ge.expense_date < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date;
        $params[] = $end_date;
    }

    // --- 1. Берём расходы из заказов ---
    $sql_order = "
        SELECT ec.name AS category_name, SUM(oe.amount) AS total_expense
        FROM order_expenses oe
        JOIN orders_accounting oa ON oe.order_accounting_id = oa.id
        LEFT JOIN expenses_categories ec ON oe.category_id = ec.id
        WHERE $where_order
        GROUP BY ec.name
    ";
    $stmt1 = $pdo->prepare($sql_order);
    $stmt1->execute(array_slice($params, 0, count($params)/2));
    $order_expenses = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. Берём общие расходы (зарплата, аренда и т.д.) ---
    $sql_general = "
        SELECT ec.name AS category_name, SUM(ge.amount) AS total_expense
        FROM general_expenses ge
        LEFT JOIN expenses_categories ec ON ge.category_id = ec.id
        WHERE $where_general
        GROUP BY ec.name
    ";
    $stmt2 = $pdo->prepare($sql_general);
    $stmt2->execute(array_slice($params, count($params)/2));
    $general_expenses = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Объединяем ---
    $all_expenses = [];

    foreach ($order_expenses as $exp) {
        $cat = $exp['category_name'] ?? 'Без категории';
        $all_expenses[$cat] = ($all_expenses[$cat] ?? 0) + $exp['total_expense'];
    }

    foreach ($general_expenses as $exp) {
        $cat = $exp['category_name'] ?? 'Без категории';
        $all_expenses[$cat] = ($all_expenses[$cat] ?? 0) + $exp['total_expense'];
    }

    // --- 4. Превращаем обратно в массив для вывода ---
    $result = [];
    foreach ($all_expenses as $cat => $sum) {
        $result[] = [
            'category_name' => $cat,
            'total_expense' => $sum
        ];
    }

    // Сортируем по убыванию суммы
    usort($result, function($a, $b) {
        return $b['total_expense'] <=> $a['total_expense'];
    });

    return $result;
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
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    
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

function get_total_general_expenses($pdo, $start_date = null, $end_date = null) {
    $sql = "SELECT SUM(amount) as total FROM general_expenses WHERE 1=1";
    $params = [];
    if ($start_date) {
        $sql .= " AND expense_date >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql .= " AND expense_date < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

/**
 * ✅ Новый расчёт расходов: на основе product_expenses
 */
function calculate_estimated_expense(PDO $pdo, int $order_id, string $source = 'site'): float {
    $total_expense = 0;

    if ($source === 'site') {
        // Товары из обычных заказов
        $stmt = $pdo->prepare("
            SELECT oi.product_id, oi.quantity
            FROM order_items oi
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($source === 'external') {
        // Товары из внешних заказов
        $stmt = $pdo->prepare("
            SELECT eoi.product_id, eoi.quantity
            FROM external_order_items eoi
            WHERE eoi.external_order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        return 0;
    }

    // Считаем себестоимость по расходникам
    foreach ($items as $item) {
        $stmt = $pdo->prepare("
            SELECT pm.quantity_per_unit, m.cost_per_unit
            FROM product_materials pm
            JOIN materials m ON pm.material_id = m.id
            WHERE pm.product_id = ?
        ");
        $stmt->execute([$item['product_id']]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($materials as $mat) {
            $total_expense += $mat['quantity_per_unit'] * $mat['cost_per_unit'] * $item['quantity'];
        }
    }

    return round($total_expense, 2);
}


function getUnitPrice($pdo, $product_id, $quantity) {
    // Получаем все диапазоны для товара
    $stmt = $pdo->prepare("SELECT * FROM product_quantity_prices WHERE product_id = ? ORDER BY min_qty ASC");
    $stmt->execute([$product_id]);
    $ranges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unitPrice = null;

    foreach ($ranges as $r) {
        $min = (int)$r['min_qty'];
        $max = $r['max_qty'] ? (int)$r['max_qty'] : PHP_INT_MAX;

        if ($quantity >= $min && $quantity <= $max) {
            $unitPrice = (float)$r['price'];
        }
    }

    // Если диапазон не найден → используем базовую цену
    if ($unitPrice === null) {
        $stmt = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $unitPrice = (float)$stmt->fetchColumn();
    }

    return $unitPrice;
}

/**
 * Получить значение настройки (например, налог)
 */
function get_setting(PDO $pdo, string $name, $default = null) {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = ?");
    $stmt->execute([$name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

/**
 * Установить значение настройки
 */
function set_setting(PDO $pdo, string $name, $value): bool {
    $stmt = $pdo->prepare("
        INSERT INTO settings (name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    return $stmt->execute([$name, $value]);
}

/**
 * Рассчитать налог с заказа и записать в orders_accounting
 */
function calculate_and_save_tax(PDO $pdo, int $order_accounting_id, float $income): float {
    $tax_rate = (float) get_setting($pdo, 'tax_rate', 20.0); // % по умолчанию 20
    $tax_amount = round($income * ($tax_rate / 100), 2);

    $stmt = $pdo->prepare("UPDATE orders_accounting SET tax_amount = ? WHERE id = ?");
    $stmt->execute([$tax_amount, $order_accounting_id]);

    return $tax_amount;
}


/**
 * ✅ Создание детальных автоматических расходов по заказу
 */
function create_automatic_expense_record(PDO $pdo, int $order_accounting_id, float $amount): bool {
    if ($amount <= 0) return false;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO order_expenses (order_accounting_id, amount, description, expense_date)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $order_accounting_id,
            $amount,
            "Автоматический расчет себестоимости материалов"
        ]);

        // обновляем поле total_expense в orders_accounting
        $stmt = $pdo->prepare("UPDATE orders_accounting SET total_expense = total_expense + ? WHERE id = ?");
        $stmt->execute([$amount, $order_accounting_id]);

        return true;
    } catch (Exception $e) {
        error_log("create_automatic_expense_record error: " . $e->getMessage());
        return false;
    }
}

?>
