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

/**
 * ✅ Новый расчёт расходов: на основе product_expenses
 */
function calculate_estimated_expense($pdo, $order_id) {
    error_log("Starting calculate_estimated_expense for order_id: " . $order_id);

    $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_expense = 0;

    foreach ($items as $item) {
        $stmt_exp = $pdo->prepare("
            SELECT material_name, quantity_per_unit, cost_per_unit
            FROM product_expenses
            WHERE product_id = ?
        ");
        $stmt_exp->execute([$item['product_id']]);
        $expenses = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expenses as $exp) {
            $qty = $exp['quantity_per_unit'] * $item['quantity'];
            $cost = $exp['cost_per_unit'] * $qty;
            $total_expense += $cost;
        }
    }

    $final_result = round($total_expense, 2);
    error_log("Final estimated expense for order $order_id: $final_result");

    return $final_result;
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
function create_automatic_expense_record($pdo, $order_accounting_id, $order_id, $description = "Автоматически рассчитанные расходы по материалам") {
    try {
        // Удаляем старые расходы по этому заказу
        $stmt = $pdo->prepare("DELETE FROM order_expenses WHERE order_accounting_id = ?");
        $stmt->execute([$order_accounting_id]);

        $stmt_items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt_items->execute([$order_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        $total_expense = 0;

        foreach ($items as $item) {
            $stmt_exp = $pdo->prepare("
                SELECT material_name, quantity_per_unit, unit, cost_per_unit
                FROM product_expenses
                WHERE product_id = ?
            ");
            $stmt_exp->execute([$item['product_id']]);
            $expenses = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

            foreach ($expenses as $exp) {
                $material_name = $exp['material_name'];
                $unit = $exp['unit'] ?? '';
                $total_qty = $exp['quantity_per_unit'] * $item['quantity'];
                $cost_per_unit = $exp['cost_per_unit'] ?? 0;
                $total_cost = $total_qty * $cost_per_unit;

                $total_expense += $total_cost;

                $stmt_insert = $pdo->prepare("
                    INSERT INTO order_expenses
                        (order_accounting_id, material_name, quantity, unit, cost_per_unit, total_cost, amount, description, expense_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt_insert->execute([
                    $order_accounting_id,
                    $material_name,
                    $total_qty,
                    $unit,
                    $cost_per_unit,
                    $total_cost,
                    $total_cost,
                    "Автоматический расход: $material_name"
                ]);
            }
        }

        // Обновляем orders_accounting
        $stmt_update = $pdo->prepare("
            UPDATE orders_accounting
            SET estimated_expense = ?, total_expense = ?
            WHERE id = ?
        ");
        $stmt_update->execute([$total_expense, $total_expense, $order_accounting_id]);

        error_log("SUCCESS: Auto expenses created for order_accounting_id $order_accounting_id. Total: $total_expense");
        return true;
    } catch (Exception $e) {
        error_log("ERROR in create_automatic_expense_record: " . $e->getMessage());
        return false;
    }
}
?>
