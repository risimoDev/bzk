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

// ---------- NEW: списание/возврат материалов при изменении количества товара ----------
// ---------- Списание/возврат материалов для товара (дельта количества) ----------
function apply_materials_delta_for_product(PDO $pdo, int $order_id, int $product_id, int $deltaQty, string $comment = ''): void {
    if ($deltaQty === 0) return;

    // Связка товар -> материалы
    $stmt = $pdo->prepare("
        SELECT pm.material_id, pm.quantity_per_unit, m.name
        FROM product_materials pm
        JOIN materials m ON m.id = pm.material_id
        WHERE pm.product_id = ?
    ");
    $stmt->execute([$product_id]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($materials as $row) {
        $material_id = (int)$row['material_id'];
        $qtyPerUnit  = (float)$row['quantity_per_unit'];
        $delta       = $qtyPerUnit * $deltaQty; // может быть отрицательной (возврат)

        if (abs($delta) < 1e-9) continue;

        $type   = $delta > 0 ? 'out' : 'in';
        $absQty = abs($delta);

        // Гарантируем наличие строки в остатках
        $stmt = $pdo->prepare("
            INSERT INTO materials_stock (material_id, quantity) VALUES (?, 0)
            ON DUPLICATE KEY UPDATE material_id = material_id
        ");
        $stmt->execute([$material_id]);

        // Обновляем остаток
        if ($type === 'out') {
            $stmt = $pdo->prepare("UPDATE materials_stock SET quantity = GREATEST(quantity - ?, 0) WHERE material_id = ?");
            $stmt->execute([$absQty, $material_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE materials_stock SET quantity = quantity + ? WHERE material_id = ?");
            $stmt->execute([$absQty, $material_id]);
        }

        // Движение по складу
        $stmt = $pdo->prepare("
            INSERT INTO materials_movements (material_id, type, quantity, reference_type, reference_id, comment)
            VALUES (?, ?, ?, 'order', ?, ?)
        ");
        $stmt->execute([$material_id, $type, $absQty, $order_id, $comment ?: 'Автодвижение по заказу']);
    }
}
// ---------- NEW: пересчет итогов заказа, промо и налогов ----------
// ---------- Пересчет итогов заказа, промокода, налога, себестоимости ----------
function recalc_order_totals(PDO $pdo, int $order_id): array {
    $pdo->beginTransaction();
    try {
        // 1) Чистая сумма позиций
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(price),0) FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $original = (float)$stmt->fetchColumn();

        // 2) Данные заказа
        $stmt = $pdo->prepare("SELECT id, is_urgent, contact_info FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new Exception("Order not found");
        }

        $is_urgent  = ((int)$order['is_urgent'] === 1);
        $urgent_fee = $is_urgent ? round($original * 0.5, 2) : 0.0;
        $subtotal   = round($original + $urgent_fee, 2);

        // 3) Промокод
        $stmt = $pdo->prepare("SELECT id, discount_type, discount_value FROM order_promocodes WHERE order_id = ? LIMIT 1");
        $stmt->execute([$order_id]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);

        $discount = 0.0;
        if ($promo) {
            if ($promo['discount_type'] === 'percentage') {
                $discount = round($subtotal * ((float)$promo['discount_value'] / 100), 2);
            } else {
                $discount = min((float)$promo['discount_value'], $subtotal);
            }
            // Обновляем примененную скидку
            $stmt = $pdo->prepare("UPDATE order_promocodes SET applied_discount = ? WHERE id = ?");
            $stmt->execute([$discount, (int)$promo['id']]);
        }

        $new_total = max(0, round($subtotal - $discount, 2));

        // 4) Обновляем заказ (total_price и contact_info.original_total_price/urgent_fee)
        $ci = json_decode($order['contact_info'] ?: '{}', true);
        if (!is_array($ci)) $ci = [];
        $ci['original_total_price'] = $original;
        $ci['urgent_fee']           = $urgent_fee;

        $stmt = $pdo->prepare("UPDATE orders SET total_price = ?, contact_info = ? WHERE id = ?");
        $stmt->execute([$new_total, json_encode($ci, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $order_id]);

        // 5) Бухгалтерия
        $stmt = $pdo->prepare("SELECT id FROM orders_accounting WHERE order_id = ? FOR UPDATE");
        $stmt->execute([$order_id]);
        $acc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($acc) {
            // Доход
            $stmt = $pdo->prepare("UPDATE orders_accounting SET income = ? WHERE id = ?");
            $stmt->execute([$new_total, (int)$acc['id']]);

            // Налог
            $tax_amount = calculate_and_save_tax($pdo, (int)$acc['id'], $new_total);

            // Оценочная себестоимость
            $estimated = calculate_estimated_expense($pdo, $order_id, 'site');
            $stmt = $pdo->prepare("UPDATE orders_accounting SET estimated_expense = ? WHERE id = ?");
            $stmt->execute([$estimated, (int)$acc['id']]);

            // Коррекция автоматических расходов (дельтой)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount),0) 
                FROM order_expenses 
                WHERE order_accounting_id = ? 
                  AND description LIKE 'Автоматический расчет себестоимости материалов%'
            ");
            $stmt->execute([(int)$acc['id']]);
            $auto_sum = (float)$stmt->fetchColumn();

            $delta = round($estimated - $auto_sum, 2);
            if (abs($delta) >= 0.01) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_expenses (order_accounting_id, amount, description, expense_date)
                    VALUES (?, ?, 'Автоматический расчет себестоимости материалов (коррекция)', NOW())
                ");
                $stmt->execute([(int)$acc['id'], $delta]);

                $stmt = $pdo->prepare("UPDATE orders_accounting SET total_expense = total_expense + ? WHERE id = ?");
                $stmt->execute([$delta, (int)$acc['id']]);
            }
        }

        $pdo->commit();
        return [
            'original'   => $original,
            'urgent_fee' => $urgent_fee,
            'discount'   => $discount,
            'total'      => $new_total,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
// Пересчитать сумму расходов в orders_accounting по фактическим записям order_expenses
function recalc_accounting_total_expense(PDO $pdo, int $order_accounting_id): float {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM order_expenses WHERE order_accounting_id = ?");
    $stmt->execute([$order_accounting_id]);
    $total = (float)$stmt->fetchColumn();

    $upd = $pdo->prepare("UPDATE orders_accounting SET total_expense = ? WHERE id = ?");
    $upd->execute([$total, $order_accounting_id]);

    return $total;
}

// Получить id бухгалтерской записи по id заказа
function get_accounting_id_by_order(PDO $pdo, int $order_id): ?int {
    $stmt = $pdo->prepare("SELECT id FROM orders_accounting WHERE order_id = ? LIMIT 1");
    $stmt->execute([$order_id]);
    $acc_id = $stmt->fetchColumn();
    return $acc_id ? (int)$acc_id : null;
}
?>
