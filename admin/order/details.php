<?php
// admin/order/details.php
session_start();
$pageTitle = "Детали заказа";

include_once('../../includes/chat_functions.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных и функций
include_once('../../includes/db.php');
include_once('../buhgalt/functions.php');

// CSRF init (PHP 7.4)
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

$statuses = [
  'pending' => 'В ожидании',
  'processing' => 'В обработке',
  'shipped' => 'Отправлен',
  'delivered' => 'Доставлен',
  'cancelled' => 'Отменен',
  'completed' => 'Полностью готов'
];

$status_colors = [
  'pending' => 'bg-yellow-100 text-yellow-800',
  'processing' => 'bg-blue-100 text-blue-800',
  'shipped' => 'bg-purple-100 text-purple-800',
  'delivered' => 'bg-indigo-100 text-indigo-800',
  'cancelled' => 'bg-red-100 text-red-800',
  'completed' => 'bg-green-100 text-green-800'
];

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    header("Location: /admin/orders");
    exit();
}

// Заказ
$stmt = $pdo->prepare("
    SELECT o.*, u.name AS user_name, u.email, u.phone 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    header("Location: /admin/orders");
    exit();
}

// contact_info разбираем единожды
$contact_info = json_decode($order['contact_info'] ?? '{}', true);
if (!is_array($contact_info)) $contact_info = [];
$original_total_price = (float)($contact_info['original_total_price'] ?? 0);
$is_urgent = !empty($order['is_urgent']) || !empty($contact_info['is_urgent']);
$urgent_fee = (float)($contact_info['urgent_fee'] ?? 0.0);

// Промокод по заказу (для отображения)
$stmt_order_promo = $pdo->prepare("SELECT * FROM order_promocodes WHERE order_id = ?");
$stmt_order_promo->execute([$order_id]);
$order_promo = $stmt_order_promo->fetch(PDO::FETCH_ASSOC);

// Список продуктов (для добавления)
$all_products = $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Категории расходов для выпадающего списка
$expense_categories = $pdo->query("SELECT id, name FROM expenses_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Позиции заказа (с кастомными)
$stmt = $pdo->prepare("
    SELECT
      oi.id AS item_id, oi.order_id, oi.product_id, oi.is_custom,
      oi.item_name, oi.item_note, oi.quantity, oi.unit_price, oi.price, oi.attributes,
      p.name AS product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Утилита: названия атрибутов
function getAttributeNames($pdo, $attributes_json) {
  $attributes = json_decode($attributes_json, true);
  if (!is_array($attributes)) return '';
  $result = [];
  foreach ($attributes as $attribute_id => $value_id) {
      $stmt = $pdo->prepare("
          SELECT av.value, pa.name as attribute_name
          FROM attribute_values av 
          JOIN product_attributes pa ON av.attribute_id = pa.id 
          WHERE av.id = ?
      ");
      $stmt->execute([(int)$value_id]);
      $attr = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($attr) {
          $result[] = $attr['attribute_name'] . ': ' . $attr['value'];
      }
  }
  return implode(', ', $result);
}

// --- Чат ---
$chat = get_chat_by_order_id($pdo, $order_id);
if (!$chat) {
    $chat_id = create_chat_for_order($pdo, $order_id);
    if ($chat_id) {
        $chat = get_chat_by_order_id($pdo, $order_id);
    }
}
$messages = $chat ? get_chat_messages($pdo, $chat['id']) : [];
if ($chat) {
    mark_messages_as_read($pdo, $chat['id'], $_SESSION['user_id']);
}
$managers_admins = get_managers_and_admins($pdo);

// Обработка: Назначение менеджера (с CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_user'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка безопасности (CSRF).'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    $new_user_id = (int)($_POST['assigned_user_id'] ?? 0);
    if ($chat && $new_user_id > 0) {
        if (assign_user_to_chat($pdo, $chat['id'], $new_user_id)) {
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Менеджер успешно назначен.'];
            $chat = get_chat_by_order_id($pdo, $order_id);
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при назначении менеджера.'];
        }
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Обработка: Изменение статуса заказа (с CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка безопасности (CSRF).'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    $new_status = $_POST['update_status'] ?? null;
    if ($new_status && isset($statuses[$new_status])) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        $order['status'] = $new_status;
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Статус заказа успешно изменен.'];
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Не удалось изменить статус заказа.'];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Бухгалтерия и изменение статуса оплаты
$stmt = $pdo->prepare("SELECT id, status FROM orders_accounting WHERE order_id = ?");
$stmt->execute([$order_id]);
$accounting = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка безопасности (CSRF).'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    $new_status = $_POST['payment_status'] ?? 'unpaid';
    if ($accounting) {
        $stmt = $pdo->prepare("UPDATE orders_accounting SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $accounting['id']]);
        $accounting['status'] = $new_status;
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Статус оплаты успешно изменен.'];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// --- NEW: обработчики позиций (добавить/редактировать/удалить) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка безопасности (CSRF).'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    try {
        if ($_POST['action'] === 'add_item_catalog') {
            $product_id = (int)($_POST['product_id'] ?? 0);
            $quantity   = max(1, (int)($_POST['quantity'] ?? 1));
            if ($product_id <= 0) throw new Exception('Выберите товар из каталога.');

            $unit_price = getUnitPrice($pdo, $product_id, $quantity);
            $line_total = round($unit_price * $quantity, 2);

            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, is_custom, item_name, item_note, quantity, unit_price, price, attributes)
                VALUES (?, ?, 0, NULL, NULL, ?, ?, ?, NULL)
            ");
            $stmt->execute([$order_id, $product_id, $quantity, $unit_price, $line_total]);

            apply_materials_delta_for_product($pdo, $order_id, $product_id, $quantity, 'Списание при добавлении позиции');
            recalc_order_totals($pdo, $order_id);

            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Позиция из каталога добавлена.'];

        } elseif ($_POST['action'] === 'add_item_custom') {
    $item_name  = trim($_POST['item_name'] ?? '');
    $item_note  = trim($_POST['item_note'] ?? '');
    $quantity   = max(1, (int)($_POST['quantity'] ?? 1));
    $unit_price = round((float)($_POST['unit_price'] ?? 0), 2);

    if ($item_name === '' || $unit_price <= 0) {
        throw new Exception('Укажите название и корректную цену за единицу.');
    }

    $line_total = round($unit_price * $quantity, 2);

    // 1) Вставляем строку заказа
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, is_custom, item_name, item_note, quantity, unit_price, price, attributes)
        VALUES (?, NULL, 1, ?, ?, ?, ?, ?, NULL)
    ");
    $stmt->execute([$order_id, $item_name, $item_note, $quantity, $unit_price, $line_total]);
    $order_item_id = (int)$pdo->lastInsertId();

    // 2) Пересчет доходной части (итог, промокод, налог и т.п.)
    recalc_order_totals($pdo, $order_id);

    // 3) (Опционально) — записываем расход, привязанный к этой строке
    $add_expense = isset($_POST['add_expense']) && $_POST['add_expense'] === '1';
    if ($add_expense) {
        $exp_amount      = round((float)($_POST['exp_amount'] ?? 0), 2);
        $exp_category_id = (int)($_POST['exp_category_id'] ?? 0) ?: null;
        $exp_desc        = trim($_POST['exp_desc'] ?? '');

        if ($exp_amount > 0) {
            $acc_id = get_accounting_id_by_order($pdo, (int)$order_id);
            if ($acc_id) {
                $stmtIns = $pdo->prepare("
                    INSERT INTO order_expenses (order_accounting_id, order_item_id, category_id, amount, description, material_name, expense_date)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtIns->execute([
                    $acc_id,
                    $order_item_id, // ВАЖНО: привязываем расход к строке
                    $exp_category_id,
                    $exp_amount,
                    $exp_desc !== '' ? $exp_desc : ('Расход по позиции: ' . $item_name),
                    $item_name
                ]);

                // Пересчитываем total_expense целиком (точно и просто)
                recalc_accounting_total_expense($pdo, $acc_id);

                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Кастомная позиция и расход добавлены.'];
            } else {
                $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Позиция добавлена, но не удалось записать расход: нет записи в бухгалтерии.'];
            }
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Позиция добавлена. Расход не записан: укажите корректную сумму.'];
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Кастомная позиция добавлена.'];
    }

        } elseif ($_POST['action'] === 'delete_item') {
            $item_id = (int)($_POST['item_id'] ?? 0);

            // Загружаем строку
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE id = ? AND order_id = ?");
            $stmt->execute([$item_id, $order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Позиция не найдена.');

            // Возврат материалов, если это товар из каталога
            if ((int)$row['is_custom'] === 0 && !empty($row['product_id'])) {
                apply_materials_delta_for_product($pdo, $order_id, (int)$row['product_id'], -((int)$row['quantity']), 'Возврат при удалении позиции');
            }
          
            // Удаляем связанные расходы (если были) и пересчитываем total_expense
            $acc_id = get_accounting_id_by_order($pdo, (int)$order_id);
            if ($acc_id) {
                // Сначала удаляем сами записи расходов по этой строке
                $pdo->prepare("DELETE FROM order_expenses WHERE order_accounting_id = ? AND order_item_id = ?")
                    ->execute([$acc_id, $item_id]);
            
                // Пересчитываем сумму расходов в бухгалтерии
                recalc_accounting_total_expense($pdo, $acc_id);
            }
          
            // Удаляем саму строку заказа
            $pdo->prepare("DELETE FROM order_items WHERE id = ?")->execute([$item_id]);
          
            // Пересчет итогов заказа, налогов и т.п.
            recalc_order_totals($pdo, (int)$order_id);
          
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Позиция удалена.'];
        

        } elseif ($_POST['action'] === 'edit_item') {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE id = ? AND order_id = ?");
            $stmt->execute([$item_id, $order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Позиция не найдена.');

            if ((int)$row['is_custom'] === 1) {
                $item_name  = trim($_POST['item_name'] ?? '');
                $item_note  = trim($_POST['item_note'] ?? '');
                $quantity   = max(1, (int)($_POST['quantity'] ?? 1));
                $unit_price = round((float)($_POST['unit_price'] ?? 0), 2);
                if ($item_name === '' || $unit_price <= 0) throw new Exception('Укажите корректные имя и цену.');

                $line_total = round($unit_price * $quantity, 2);
                $upd = $pdo->prepare("
                    UPDATE order_items
                    SET item_name = ?, item_note = ?, quantity = ?, unit_price = ?, price = ?
                    WHERE id = ?
                ");
                $upd->execute([$item_name, $item_note, $quantity, $unit_price, $line_total, $item_id]);

            } else {
                $new_qty   = max(1, (int)($_POST['quantity'] ?? 1));
                $old_qty   = (int)$row['quantity'];
                $product_id = (int)$row['product_id'];

                $unit_price = getUnitPrice($pdo, $product_id, $new_qty);
                $line_total = round($unit_price * $new_qty, 2);
                $upd = $pdo->prepare("UPDATE order_items SET quantity = ?, unit_price = ?, price = ? WHERE id = ?");
                $upd->execute([$new_qty, $unit_price, $line_total, $item_id]);

                $deltaQty = $new_qty - $old_qty;
                if ($deltaQty !== 0) {
                    apply_materials_delta_for_product($pdo, $order_id, $product_id, $deltaQty, 'Корректировка при редактировании позиции');
                }
            }

            recalc_order_totals($pdo, $order_id);
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Позиция обновлена.'];
        }

    } catch (Exception $e) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => $e->getMessage()];
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

include_once('../../includes/header.php');
?>
<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin/orders" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">Все заказы</a>
      </div>
    </div>

    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Заказ #<?php echo htmlspecialchars($order['id']); ?></h1>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
    </div>

    <?php if (!empty($_SESSION['notifications'])): ?>
      <?php foreach ($_SESSION['notifications'] as $n): ?>
        <div class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
          <?php echo htmlspecialchars($n['message']); ?>
        </div>
      <?php endforeach; unset($_SESSION['notifications']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div class="lg:col-span-2 space-y-8">
        <!-- Информация -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4 mb-6">
            <div>
              <h2 class="text-2xl font-bold text-gray-800 mb-2">Информация о заказе</h2>
              <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                  <?php echo htmlspecialchars($statuses[$order['status']] ?? 'Неизвестно'); ?>
                </span>
                <?php if ($is_urgent): ?>
                  <span class="px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">Срочный заказ (+50%)</span>
                <?php endif; ?>
                <span class="ml-1 text-gray-600">Создан: <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></span>
              </div>
            </div>

            <form action="" method="POST" class="flex items-center gap-2">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <select name="update_status" class="rounded-l-lg border-gray-300 focus:border-[#118568] focus:ring-[#17B890]">
                <?php foreach ($statuses as $status_key => $status_name): ?>
                  <option value="<?php echo $status_key; ?>" <?php echo $order['status'] === $status_key ? 'selected' : ''; ?>>
                    <?php echo $status_name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="px-4 py-2 bg-[#118568] text-white rounded-r-lg hover:bg-[#0f755a]" title="Сохранить статус">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              </button>
            </form>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gray-50 rounded-2xl p-4">
              <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Клиент
              </h3>
              <div class="space-y-2">
                <p class="text-gray-700"><span class="font-medium">Имя:</span> <?php echo htmlspecialchars($order['user_name'] ?? 'Не указано'); ?></p>
                <p class="text-gray-700"><span class="font-medium">Email:</span> <?php echo htmlspecialchars($order['email'] ?? 'Не указано'); ?></p>
                <p class="text-gray-700"><span class="font-medium">Телефон:</span> <?php echo htmlspecialchars($order['phone'] ?? 'Не указано'); ?></p>
                <?php if (!empty($contact_info['comment'])): ?>
                  <p class="text-gray-700"><span class="font-medium">Комментарий:</span> <?php echo nl2br(htmlspecialchars($contact_info['comment'])); ?></p>
                <?php endif; ?>
              </div>
            </div>
            <div class="bg-gray-50 rounded-2xl p-4">
              <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0L5 15.243V19a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2h-5z"/></svg>
                Доставка
              </h3>
              <div class="space-y-2">
                <p class="text-gray-700"><span class="font-medium">Адрес:</span> <?php echo htmlspecialchars($order['shipping_address'] ?? 'Не указано'); ?></p>
                <p class="text-gray-700"><span class="font-medium">Контакт:</span> <?php echo htmlspecialchars($contact_info['name'] ?? 'Не указано'); ?></p>
              </div>
            </div>

            <div class="md:col-span-2 bg-gray-50 rounded-2xl p-4">
              <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0L5 15.243V19a2 2 0 01-2 2H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2h-3.586l-4.243-4.243z"/></svg>
                Финансовая информация
              </h3>
              <div class="flex flex-col md:flex-row gap-4 md:items-end">
                <form method="POST" class="flex items-center gap-2">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                  <input type="hidden" name="update_payment_status" value="1">
                  <label for="payment_status" class="font-medium text-gray-700">Оплата:</label>
                  <select name="payment_status" id="payment_status" onchange="this.form.submit()" class="rounded-lg border-gray-300 focus:border-[#118568] focus:ring-[#17B890]">
                    <option value="unpaid"  <?php echo ($accounting['status'] ?? '') === 'unpaid'  ? 'selected' : ''; ?>>Не оплачен</option>
                    <option value="partial" <?php echo ($accounting['status'] ?? '') === 'partial' ? 'selected' : ''; ?>>Частично</option>
                    <option value="paid"    <?php echo ($accounting['status'] ?? '') === 'paid'    ? 'selected' : ''; ?>>Оплачен</option>
                  </select>
                </form>

                <div class="ml-auto text-right">
                  <p class="text-gray-700"><span class="font-medium">Стоимость товаров:</span> <?php echo number_format($original_total_price, 2, '.', ' '); ?> ₽</p>
                  <?php if ($is_urgent): ?>
                    <p class="text-gray-700"><span class="font-medium">Срочный заказ (+50%):</span> <span class="text-[#17B890]">+<?php echo number_format($urgent_fee, 2, '.', ' '); ?> ₽</span></p>
                  <?php endif; ?>
                  <?php if ($order_promo): ?>
                    <p class="text-gray-700">
                      <span class="font-medium">Промокод (<?php echo htmlspecialchars($order_promo['promo_code']); ?>):</span>
                      <span class="text-red-500">-<?php echo number_format($order_promo['applied_discount'], 2, '.', ' '); ?> ₽</span>
                      <span class="text-xs text-gray-500 ml-2">
                        <?php echo $order_promo['discount_type'] === 'percentage' ? ($order_promo['discount_value'].'%') : (number_format($order_promo['discount_value'], 2, '.', ' ').' ₽'); ?>
                      </span>
                    </p>
                  <?php endif; ?>
                  <div class="border-t border-gray-200 pt-2 mt-2">
                    <div class="flex justify-between text-lg font-bold">
                      <span>Итого к оплате:</span>
                      <span class="text-[#118568]"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> ₽</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Товары и добавление -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-gray-800">Товары в заказе</h2>
            <button type="button" id="toggle-add-panel" class="px-3 py-2 text-sm rounded bg-[#118568] text-white hover:bg-[#0f755a]">
              Добавить позицию
            </button>
          </div>

          <!-- Панель добавления с табами -->
          <div id="add-panel" class="hidden bg-white rounded-2xl p-4 mb-6 border border-gray-200">
            <div class="flex gap-2 mb-4">
              <button type="button" data-tab="tab-catalog" class="add-tab px-3 py-2 rounded bg-[#118568] text-white text-sm">Из каталога</button>
              <button type="button" data-tab="tab-custom"  class="add-tab px-3 py-2 rounded bg-gray-200 text-gray-800 text-sm">Своя позиция</button>
            </div>

            <div id="tab-catalog" class="tab-content">
              <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="add_item_catalog">

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Товар</label>
                  <input type="text" id="prod-search" placeholder="Поиск..." class="w-full mb-2 border rounded px-3 py-2">
                  <select id="prod-select" name="product_id" class="w-full border rounded px-3 py-2">
                    <option value="">— Выберите товар —</option>
                    <?php foreach ($all_products as $p): ?>
                      <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Количество</label>
                  <input type="number" name="quantity" min="1" value="1" class="w-full border rounded px-3 py-2">
                </div>

                <div class="md:col-span-3">
                  <button type="submit" class="px-4 py-2 bg-[#118568] text-white rounded hover:bg-[#0f755a]">Добавить</button>
                </div>
              </form>
            </div>

            <div id="tab-custom" class="tab-content hidden">
              <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="add_item_custom">

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Название</label>
                  <input type="text" name="item_name" required class="w-full border rounded px-3 py-2" placeholder="Например, Доработка макета">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Количество</label>
                  <input type="number" name="quantity" min="1" value="1" required class="w-full border rounded px-3 py-2">
                </div>

                <div class="md:col-span-3">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Описание/примечание</label>
                  <textarea name="item_note" class="w-full border rounded px-3 py-2" rows="2" placeholder="Комментарии для производства/клиента"></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Цена за единицу, ₽</label>
                  <input type="number" name="unit_price" step="0.01" min="0.01" required class="w-full border rounded px-3 py-2">
                </div>

                <!-- Учесть расход -->
                <div class="md:col-span-3 mt-2">
                  <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="toggle-expense" class="mr-2" name="add_expense" value="1">
                    <span class="text-sm font-medium text-gray-800">Учесть расход по этой позиции</span>
                  </label>

                  <div id="expense-fields" class="hidden mt-3 grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-lg border">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Сумма расхода, ₽</label>
                      <input type="number" step="0.01" min="0.01" name="exp_amount" class="w-full border rounded px-3 py-2" placeholder="Например, 500.00">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Категория</label>
                      <select name="exp_category_id" class="w-full border rounded px-3 py-2">
                        <option value="">— Не выбрана —</option>
                        <?php foreach ($expense_categories as $cat): ?>
                          <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="md:col-span-3">
                      <label class="block text-sm font-medium text-gray-700 mb-1">Описание расхода</label>
                      <input type="text" name="exp_desc" id="exp_desc" class="w-full border rounded px-3 py-2" placeholder="Например, Внеплановая допечатная подготовка">
                      <p class="text-xs text-gray-500 mt-1">Запишется в бухгалтерию заказа как отдельный расход.</p>
                    </div>
                  </div>
                </div>
                        
                <div class="md:col-span-3">
                  <button type="submit" class="px-4 py-2 bg-[#118568] text-white rounded hover:bg-[#0f755a]">Добавить</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Список позиций -->
          <div class="space-y-4">
            <?php if (empty($order_items)): ?>
              <div class="p-6 bg-gray-50 rounded-2xl text-gray-600">Пока нет позиций</div>
            <?php else: ?>
              <?php foreach ($order_items as $item): 
                $is_custom    = (int)$item['is_custom'] === 1;
                $display_name = $is_custom ? ($item['item_name'] ?: 'Своя позиция') : ($item['product_name'] ?? '—');
                $unit_price   = $item['unit_price'] !== null ? (float)$item['unit_price'] : ($item['quantity'] > 0 ? ((float)$item['price'] / (int)$item['quantity']) : 0);
              ?>
              <div class="p-4 bg-gray-50 rounded-2xl border hover:bg-gray-100 transition">
                <div class="flex justify-between items-start">
                  <div>
                    <h3 class="font-bold text-gray-800 mb-1">
                      <?php echo htmlspecialchars($display_name); ?>
                      <?php if ($is_custom): ?>
                        <span class="ml-2 text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Своя позиция</span>
                      <?php endif; ?>
                    </h3>
                    <?php if (!$is_custom && !empty($item['attributes'])): ?>
                      <div class="text-sm text-gray-600">Характеристики: <?php echo htmlspecialchars(getAttributeNames($pdo, $item['attributes'])); ?></div>
                    <?php endif; ?>
                    <?php if ($is_custom && !empty($item['item_note'])): ?>
                      <div class="text-sm text-gray-600">Примечание: <?php echo nl2br(htmlspecialchars($item['item_note'])); ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="text-lg font-bold text-[#118568]"><?php echo number_format((float)$item['price'], 2, '.', ' '); ?> ₽</div>
                </div>

                <div class="mt-2 text-sm text-gray-700">
                  Количество: <?php echo (int)$item['quantity']; ?> | Цена за единицу: <?php echo number_format($unit_price, 2, '.', ' '); ?> ₽
                </div>

                <div class="mt-3 flex flex-col md:flex-row gap-2">
                  <!-- Редактирование -->
                  <form method="POST" class="flex flex-wrap items-end gap-2 bg-white p-3 rounded-lg border">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="edit_item">
                    <input type="hidden" name="item_id" value="<?php echo (int)$item['item_id']; ?>">

                    <?php if ($is_custom): ?>
                      <div>
                        <label class="block text-xs text-gray-600">Название</label>
                        <input type="text" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" class="border rounded px-2 py-1">
                      </div>
                      <div>
                        <label class="block text-xs text-gray-600">Кол-во</label>
                        <input type="number" name="quantity" min="1" value="<?php echo (int)$item['quantity']; ?>" class="border rounded px-2 py-1 w-24">
                      </div>
                      <div>
                        <label class="block text-xs text-gray-600">Цена/ед., ₽</label>
                        <input type="number" step="0.01" min="0.01" name="unit_price" value="<?php echo number_format($unit_price, 2, '.', ''); ?>" class="border rounded px-2 py-1 w-28">
                      </div>
                      <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs text-gray-600">Примечание</label>
                        <input type="text" name="item_note" value="<?php echo htmlspecialchars($item['item_note'] ?? ''); ?>" class="border rounded px-2 py-1 w-full">
                      </div>
                    <?php else: ?>
                      <div>
                        <label class="block text-xs text-gray-600">Кол-во</label>
                        <input type="number" name="quantity" min="1" value="<?php echo (int)$item['quantity']; ?>" class="border rounded px-2 py-1 w-24">
                        <div class="text-[11px] text-gray-500 mt-1">Цена пересчитается автоматически</div>
                      </div>
                    <?php endif; ?>

                    <button type="submit" class="px-3 py-2 bg-[#17B890] text-white text-sm rounded hover:bg-[#14a380]">Сохранить</button>
                  </form>

                  <!-- Удаление -->
                  <form method="POST" onsubmit="return confirm('Удалить позицию?');" class="self-start">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_id" value="<?php echo (int)$item['item_id']; ?>">
                    <button type="submit" class="px-3 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700">Удалить</button>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="flex justify-end">
              <div class="text-right">
                <div class="text-gray-600">Итого:</div>
                <div class="text-2xl font-bold text-[#118568]"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> ₽</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Боковая панель -->
      <div class="space-y-8">
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Сводка заказа</h2>
          <div class="space-y-4">
            <div class="flex justify-between"><span class="text-gray-600">Товарных позиций:</span><span class="font-medium"><?php echo count($order_items); ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Сумма по строкам:</span><span class="font-medium"><?php echo number_format($original_total_price, 2, '.', ' '); ?> ₽</span></div>
            <div class="flex justify-between"><span class="text-gray-600">Доставка:</span><span class="font-medium">Бесплатно</span></div>
            <div class="border-t border-gray-200 pt-4">
              <div class="flex justify-between text-lg font-bold">
                <span>К оплате:</span>
                <span class="text-[#118568]"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> ₽</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Чат & управление -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Чат и управление</h2>

          <div class="bg-gray-50 rounded-2xl p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Назначить менеджера</h3>
            <?php if ($chat): ?>
              <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <select name="assigned_user_id" class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568]">
                  <option value="">Не назначен</option>
                  <?php foreach ($managers_admins as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo ($chat['assigned_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['role'] === 'admin' ? 'Админ' : 'Менеджер'; ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="assign_user" class="w-full px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a]">Назначить</button>
              </form>
              <?php if (!empty($chat['assigned_user_name'])): ?>
                <div class="mt-4 p-3 bg-[#DEE5E5] rounded-lg text-sm">
                  <span class="font-medium">Текущий менеджер:</span>
                  <span class="text-[#118568]"><?php echo htmlspecialchars($chat['assigned_user_name']); ?></span>
                  <?php if ($chat['assigned_user_role'] === 'admin'): ?><span class="px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded ml-1">(Админ)</span><?php endif; ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <p class="text-gray-600">Чат еще не создан.</p>
            <?php endif; ?>
          </div>

          <div class="bg-gray-50 rounded-2xl">
            <div class="p-4 border-b border-[#DEE5E5] bg-white rounded-t-2xl">
              <h3 class="text-lg font-bold text-gray-800">Чат по заказу #<?php echo $order_id; ?></h3>
            </div>
            <div id="admin-chat-messages" class="flex-grow p-4 overflow-y-auto max-h-96 bg-white">
              <?php if (empty($messages)): ?>
                <div class="text-center text-gray-500 py-8">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                  <p>Сообщений пока нет</p>
                  <p class="text-xs mt-1">Напишите первое сообщение клиенту</p>
                </div>
              <?php else: ?>
                <?php foreach ($messages as $message): 
                  $is_own_message = $message['user_id'] == $_SESSION['user_id'];
                  $message_time = date('H:i', strtotime($message['created_at']));
                  $sender_role_class = $message['sender_role'] === 'admin' ? 'bg-red-100 text-red-800' : ($message['sender_role'] === 'manager' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800');
                ?>
                <div class="mb-4 <?php echo $is_own_message ? 'text-right' : 'text-left'; ?>">
                  <?php if (!$is_own_message): ?>
                    <div class="text-xs text-gray-500 mb-1 flex items-center">
                      <span><?php echo htmlspecialchars($message['sender_name']); ?></span>
                      <span class="ml-1 px-1 py-0.5 <?php echo $sender_role_class; ?> text-xs rounded">
                        <?php echo $message['sender_role'] === 'admin' ? 'Админ' : ($message['sender_role'] === 'manager' ? 'Менеджер' : 'Клиент'); ?>
                      </span>
                      <span class="ml-1"><?php echo $message_time; ?></span>
                    </div>
                  <?php endif; ?>
                  <div class="inline-block max-w-xs md:max-w-md px-4 py-3 rounded-2xl <?php echo $is_own_message ? ' bg-[#118568] text-white rounded-tr-none' : ' bg-gray-200 text-gray-800 rounded-tl-none'; ?>">
                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                  </div>
                  <?php if ($is_own_message): ?>
                    <div class="text-xs text-gray-500 mt-1 text-right"><?php echo $message_time; ?></div>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="p-4 border-t border-[#DEE5E5] bg-white rounded-b-2xl">
              <?php if ($chat): ?>
                <form id="admin-chat-form" class="flex gap-2">
                  <input type="text" id="admin-message-text" class="flex-grow px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] text-sm" placeholder="Написать клиенту..." required maxlength="1000">
                  <button type="submit" id="admin-send-button" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] flex items-center justify-center">
                    <svg id="admin-send-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <svg id="admin-sending-icon" class="h-5 w-5 hidden animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  </button>
                </form>
                <p class="text-xs text-gray-500 mt-2 text-center">Нажмите Enter для отправки</p>
              <?php else: ?>
                <p class="text-gray-500 text-center">Чат недоступен</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Панель добавления
  const toggleBtn = document.getElementById('toggle-add-panel');
  const addPanel = document.getElementById('add-panel');
  const tabs = document.querySelectorAll('.add-tab');
  const contents = document.querySelectorAll('.tab-content');

  if (toggleBtn && addPanel) {
    toggleBtn.addEventListener('click', () => addPanel.classList.toggle('hidden'));
  }
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      tabs.forEach(b => b.classList.remove('bg-[#118568]', 'text-white'));
      tabs.forEach(b => b.classList.add('bg-gray-200', 'text-gray-800'));
      btn.classList.add('bg-[#118568]', 'text-white');
      btn.classList.remove('bg-gray-200', 'text-gray-800');

      const id = btn.getAttribute('data-tab');
      contents.forEach(c => c.classList.add('hidden'));
      document.getElementById(id).classList.remove('hidden');
    });
  });

  // Поиск по списку товаров
  const search = document.getElementById('prod-search');
  const select = document.getElementById('prod-select');
  if (search && select) {
    const original = Array.from(select.options).map(o => ({value: o.value, text: o.text}));
    search.addEventListener('input', () => {
      const q = search.value.toLowerCase();
      select.innerHTML = '';
      const def = document.createElement('option');
      def.value = '';
      def.textContent = '— Выберите товар —';
      select.appendChild(def);
      original
        .filter(o => o.value === '' || o.text.toLowerCase().includes(q))
        .forEach(o => {
          const opt = document.createElement('option');
          opt.value = o.value;
          opt.textContent = o.text;
          select.appendChild(opt);
        });
    });
  }

  // Чат отправка
  const adminForm = document.getElementById('admin-chat-form');
  const adminInput = document.getElementById('admin-message-text');
  const adminSendButton = document.getElementById('admin-send-button');
  const adminSendIcon = document.getElementById('admin-send-icon');
  const adminSendingIcon = document.getElementById('admin-sending-icon');
  const adminMessagesContainer = document.getElementById('admin-chat-messages');
  if (adminForm) {
    adminForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const messageText = adminInput.value.trim();
      if (!messageText) return;
      adminSendButton.disabled = true;
      adminSendIcon.classList.add('hidden');
      adminSendingIcon.classList.remove('hidden');
      fetch('/ajax/send_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'order_id=<?php echo (int)$order_id; ?>&message_text=' + encodeURIComponent(messageText)
      }).then(r => r.json()).then(data => {
        if (data.success) {
          updateAdminMessages(data.messages || []);
          adminInput.value = '';
        } else {
          alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
        }
      }).catch(() => alert('Ошибка отправки сообщения.'))
        .finally(() => {
          adminSendButton.disabled = false;
          adminSendIcon.classList.remove('hidden');
          adminSendingIcon.classList.add('hidden');
        });
    });
  }

  function updateAdminMessages(messages) {
    if (!messages || messages.length === 0) {
      adminMessagesContainer.innerHTML = '<div class="text-center text-gray-500 py-8"><p>Сообщений пока нет</p></div>';
      return;
    }
    let html = '';
    const currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    messages.forEach(msg => {
      const own = msg.user_id == currentUserId;
      const t = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
      const role = msg.sender_role;
      const roleClass = role === 'admin' ? 'bg-red-100 text-red-800' : (role === 'manager' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800');
      html += `<div class="mb-4 ${own ? 'text-right' : ''}">`;
      if (!own) {
        html += `<div class="text-xs text-gray-500 mb-1 flex items-center"><span>${escapeHtml(msg.sender_name || '')}</span><span class="ml-1 px-1 py-0.5 ${roleClass} text-xs rounded">` + (role === 'admin' ? 'Админ' : (role === 'manager' ? 'Менеджер' : 'Клиент')) + `</span><span class="ml-1">${t}</span></div>`;
      }
      html += `<div class="inline-block max-w-xs md:max-w-md px-4 py-3 rounded-2xl ${own ? 'bg-[#118568] text-white rounded-tr-none' : 'bg-gray-200 text-gray-800 rounded-tl-none'}">${nl2br(escapeHtml(msg.message || ''))}</div>`;
      if (own) html += `<div class="text-xs text-gray-500 mt-1 text-right">${t}</div>`;
      html += `</div>`;
    });
    adminMessagesContainer.innerHTML = html;
    adminMessagesContainer.scrollTop = adminMessagesContainer.scrollHeight;
  }
  function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));}
  function nl2br(s){return String(s).replace(/\n/g,'<br>');}
});
  
  // Переключение блока "Учесть расход"
const expToggle = document.getElementById('toggle-expense');
const expFields = document.getElementById('expense-fields');
if (expToggle && expFields) {
  expToggle.addEventListener('change', () => {
    expFields.classList.toggle('hidden', !expToggle.checked);
  });
  // автозаполнение описания расхода по имени позиции
  const nameInput = document.querySelector('input[name="item_name"]');
  const descInput = document.getElementById('exp_desc');
  if (nameInput && descInput) {
    nameInput.addEventListener('blur', () => {
      if (!descInput.value.trim() && nameInput.value.trim()) {
        descInput.value = 'Расход по позиции: ' + nameInput.value.trim();
      }
    });
  }
}
</script>

<?php include_once('../../includes/footer.php'); ?>