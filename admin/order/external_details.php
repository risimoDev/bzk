<?php
session_start();
$pageTitle = "Детали внешнего заказа";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';
require_once '../buhgalt/functions.php';
require_once '../../includes/security.php'; // для CSRF

// Инициализация CSRF токена (как в details.php)
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// Получение ID заказа
$order_id = intval($_GET['id'] ?? 0);
if (!$order_id) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный ID заказа.'];
    header("Location: /admin/order/external_orders.php");
    exit();
}

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

// Получение данных заказа
$sql = "
    SELECT 
        eo.*,
        oa.id as accounting_id,
        oa.income as accounting_income,
        oa.total_expense,
        oa.estimated_expense,
        oa.tax_amount,
        (oa.income - (oa.total_expense + oa.tax_amount)) as profit
    FROM external_orders eo
    LEFT JOIN orders_accounting oa ON oa.external_order_id = eo.id AND oa.source = 'external'
    WHERE eo.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заказ не найден.'];
    header("Location: /admin/order/external_orders.php");
    exit();
}

// Хелпер: пересчет итогов внешнего заказа (доход, оценка расходов, налог)
function recalc_external_order_totals(PDO $pdo, int $external_order_id): void
{
    // Сумма всех позиций (price хранит итог по строке)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(price),0) FROM external_order_items WHERE external_order_id = ?");
    $stmt->execute([$external_order_id]);
    $total_income = (float) $stmt->fetchColumn();

    // Обновляем external_orders.total_price
    $up = $pdo->prepare("UPDATE external_orders SET total_price = ? WHERE id = ?");
    $up->execute([$total_income, $external_order_id]);

    // Бухгалтерия
    $stmt = $pdo->prepare("SELECT id FROM orders_accounting WHERE external_order_id = ? AND source = 'external' LIMIT 1");
    $stmt->execute([$external_order_id]);
    $acc_id = $stmt->fetchColumn();
    if ($acc_id) {
        // Доход
        $pdo->prepare("UPDATE orders_accounting SET income = ? WHERE id = ?")->execute([$total_income, $acc_id]);
        // Оценочный расход (материалы)
        $estimated = calculate_estimated_expense($pdo, $external_order_id, 'external');
        // Пользовательские расходы из позиций
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(expense_amount),0) FROM external_order_items WHERE external_order_id = ? AND is_custom = 1");
        $stmt->execute([$external_order_id]);
        $custom_exp = (float) $stmt->fetchColumn();
        $estimated_total = round($estimated + $custom_exp, 2);
        $pdo->prepare("UPDATE orders_accounting SET estimated_expense = ? WHERE id = ?")->execute([$estimated_total, $acc_id]);

        // Налог
        calculate_and_save_tax($pdo, (int) $acc_id, $total_income);

        // Коррекция автоматического расхода (дельта) — ищем сумму авторасходов
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM order_expenses WHERE order_accounting_id = ? AND description LIKE 'Автоматический расчет себестоимости материалов%'");
        $stmt->execute([(int) $acc_id]);
        $auto_sum = (float) $stmt->fetchColumn();
        $delta = round($estimated_total - $auto_sum, 2);
        if (abs($delta) >= 0.01) {
            $ins = $pdo->prepare("INSERT INTO order_expenses (order_accounting_id, amount, description, expense_date) VALUES (?, ?, 'Автоматический расчет себестоимости материалов (коррекция внеш.)', NOW())");
            $ins->execute([(int) $acc_id, $delta]);
            // обновляем total_expense
            $pdo->prepare("UPDATE orders_accounting SET total_expense = total_expense + ? WHERE id = ?")
                ->execute([$delta, (int) $acc_id]);
        }
    }
}

// Обработка CRUD по позициям (кроме статуса оплаты)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ext_action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка безопасности (CSRF).'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    try {
        $action = $_POST['ext_action'];
        if ($action === 'add_catalog') {
            $product_id = (int) ($_POST['product_id'] ?? 0);
            $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
            if ($product_id <= 0)
                throw new Exception('Выберите товар из каталога.');
            $unit_price = getUnitPrice($pdo, $product_id, $quantity);
            $line_total = round($unit_price * $quantity, 2);
            $stmt = $pdo->prepare("INSERT INTO external_order_items (external_order_id, product_id, is_custom, quantity, price, expense_amount) VALUES (?, ?, 0, ?, ?, 0)");
            $stmt->execute([$order_id, $product_id, $quantity, $line_total]);
            recalc_external_order_totals($pdo, $order_id);
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Позиция добавлена.'];
        } elseif ($action === 'add_custom') {
            $name = trim($_POST['item_name'] ?? '');
            $descr = trim($_POST['item_description'] ?? '');
            $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
            $unit_price = round((float) ($_POST['unit_price'] ?? 0), 2);
            $expense_unit = round((float) ($_POST['expense_unit'] ?? 0), 2);
            if ($name === '' || $unit_price <= 0)
                throw new Exception('Укажите название и корректную цену.');
            $total_price = round($unit_price * $quantity, 2);
            $total_expense = round($expense_unit * $quantity, 2);
            $stmt = $pdo->prepare("INSERT INTO external_order_items (external_order_id, product_id, is_custom, item_name, item_description, quantity, price, expense_amount) VALUES (?, NULL, 1, ?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $name, $descr, $quantity, $total_price, $total_expense]);
            recalc_external_order_totals($pdo, $order_id);
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Пользовательская позиция добавлена.'];
        } elseif ($action === 'edit_item') {
            $item_id = (int) ($_POST['item_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM external_order_items WHERE id = ? AND external_order_id = ?");
            $stmt->execute([$item_id, $order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row)
                throw new Exception('Позиция не найдена.');
            $is_custom = (int) $row['is_custom'] === 1;
            if ($is_custom) {
                $name = trim($_POST['item_name'] ?? '');
                $descr = trim($_POST['item_description'] ?? '');
                $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
                $unit_price = round((float) ($_POST['unit_price'] ?? 0), 2);
                $expense_unit = round((float) ($_POST['expense_unit'] ?? 0), 2);
                if ($name === '' || $unit_price <= 0)
                    throw new Exception('Заполните корректно название и цену.');
                $total_price = round($unit_price * $quantity, 2);
                $total_expense = round($expense_unit * $quantity, 2);
                $upd = $pdo->prepare("UPDATE external_order_items SET item_name = ?, item_description = ?, quantity = ?, price = ?, expense_amount = ? WHERE id = ?");
                $upd->execute([$name, $descr, $quantity, $total_price, $total_expense, $item_id]);
            } else {
                $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
                $unit_price = getUnitPrice($pdo, (int) $row['product_id'], $quantity);
                $line_total = round($unit_price * $quantity, 2);
                $upd = $pdo->prepare("UPDATE external_order_items SET quantity = ?, price = ? WHERE id = ?");
                $upd->execute([$quantity, $line_total, $item_id]);
            }
            recalc_external_order_totals($pdo, $order_id);
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Позиция обновлена.'];
        } elseif ($action === 'delete_item') {
            $item_id = (int) ($_POST['item_id'] ?? 0);
            $pdo->prepare("DELETE FROM external_order_items WHERE id = ? AND external_order_id = ?")
                ->execute([$item_id, $order_id]);
            recalc_external_order_totals($pdo, $order_id);
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Позиция удалена.'];
        }
    } catch (Exception $e) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => $e->getMessage()];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Обработка: примерная дата готовности внешнего заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ext_ready_action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка безопасности (CSRF).'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    try {
        $mode = $_POST['ready_mode'] ?? 'set';
        $reason = trim($_POST['ready_reason'] ?? '');
        $stmt = $pdo->prepare("SELECT estimated_ready_date FROM external_orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $prev = $stmt->fetchColumn();
        if ($mode === 'prolong') {
            $days = (int) ($_POST['prolong_days'] ?? 0);
            if ($days === 0)
                throw new Exception('Укажите количество дней для продления.');
            $base = !empty($prev) ? new DateTime($prev) : new DateTime();
            $base->modify("+{$days} day");
            $newDate = $base->format('Y-m-d H:i:s');
        } else {
            $input = trim($_POST['ready_date'] ?? '');
            if ($input === '')
                throw new Exception('Укажите дату.');
            $dt = new DateTime($input);
            $newDate = $dt->format('Y-m-d H:i:s');
        }
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE external_orders SET estimated_ready_date = ? WHERE id = ?")->execute([$newDate, $order_id]);
        $ctype = $mode === 'prolong' ? 'prolong' : (!empty($prev) ? 'edit' : 'set');
        $pdo->prepare("INSERT INTO external_order_ready_history (external_order_id, previous_date, new_date, change_type, reason, changed_by) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$order_id, $prev, $newDate, $ctype, $reason !== '' ? $reason : null, $_SESSION['user_id'] ?? null]);
        $pdo->commit();
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Примерная дата готовности обновлена.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Получение позиций заказа (после возможных изменений)
$items_sql = "
    SELECT 
        eoi.*,
        p.name as product_name,
        p.base_price as product_base_price
    FROM external_order_items eoi
    LEFT JOIN products p ON eoi.product_id = p.id
    WHERE eoi.external_order_id = ?
    ORDER BY eoi.id ASC
";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение расходов по заказу (если есть бухгалтерская запись)
$expenses = [];
if ($order['accounting_id']) {
    $expenses_sql = "
        SELECT 
            oe.*,
            ec.name as category_name
        FROM order_expenses oe
        LEFT JOIN expenses_categories ec ON oe.category_id = ec.id
        WHERE oe.order_accounting_id = ?
        ORDER BY oe.expense_date DESC
    ";
    $expenses_stmt = $pdo->prepare($expenses_sql);
    $expenses_stmt->execute([$order['accounting_id']]);
    $expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Обработка формы изменения статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = $_POST['status'] ?? '';
    if (in_array($new_status, ['unpaid', 'partial', 'paid'])) {
        try {
            $pdo->beginTransaction();

            // Обновляем статус в external_orders
            $stmt = $pdo->prepare("UPDATE external_orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);

            // Обновляем статус в orders_accounting
            if ($order['accounting_id']) {
                $stmt = $pdo->prepare("UPDATE orders_accounting SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $order['accounting_id']]);
            }

            $pdo->commit();
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Статус заказа обновлен.'];
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при обновлении статуса: ' . $e->getMessage()];
        }
    }
}

// Статистика по позициям
$catalog_items = array_filter($order_items, fn($item) => !$item['is_custom']);
$custom_items = array_filter($order_items, fn($item) => $item['is_custom']);
$total_custom_expense = array_sum(array_column($custom_items, 'expense_amount'));
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
    <div class="container mx-auto px-6 max-w-7xl">

        <!-- Заголовок -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
            <div>
                <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
            </div>
            <div class="flex gap-3">
                <a href="/admin/order/external_orders.php"
                    class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition text-sm font-medium">
                    ← К списку
                </a>
                <?php if ($order['accounting_id']): ?>
                    <a href="/admin/buhgalt/order_accounting.php?external_id=<?php echo $order_id; ?>"
                        class="px-5 py-2.5 bg-[#17B890] text-white rounded-xl hover:bg-[#15a081] transition text-sm font-medium">
                        Бухгалтерия
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Заголовок страницы -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Внешний заказ #<?php echo $order['id']; ?></h1>
            <p class="text-lg text-gray-700"><?php echo htmlspecialchars($order['client_name']); ?></p>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <!-- Уведомления -->
        <?php foreach ($notifications as $n): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success'
                ? 'bg-green-100 ring-1 ring-green-300 text-green-700'
                : 'bg-red-100 ring-1 ring-red-300 text-red-700'; ?>">
                <?php echo htmlspecialchars($n['message']); ?>
            </div>
        <?php endforeach; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Основная информация -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Информация о заказе -->
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <div class="flex justify-between items-start mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Информация о заказе</h2>
                        <span class="px-4 py-2 text-sm font-medium rounded-full
              <?php
              switch ($order['status']) {
                  case 'paid':
                      echo 'bg-green-100 text-green-800';
                      break;
                  case 'partial':
                      echo 'bg-yellow-100 text-yellow-800';
                      break;
                  default:
                      echo 'bg-red-100 text-red-800';
                      break;
              }
              ?>">
                            <?php
                            switch ($order['status']) {
                                case 'paid':
                                    echo 'Оплачен';
                                    break;
                                case 'partial':
                                    echo 'Частично';
                                    break;
                                default:
                                    echo 'Не оплачен';
                                    break;
                            }
                            ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-600 text-sm font-medium mb-1">Клиент</label>
                            <p class="text-lg font-semibold"><?php echo htmlspecialchars($order['client_name']); ?></p>
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm font-medium mb-1">Дата создания</label>
                            <p class="text-lg"><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
                        </div>
                        <?php if ($order['email']): ?>
                            <div>
                                <label class="block text-gray-600 text-sm font-medium mb-1">Email</label>
                                <p class="text-lg"><?php echo htmlspecialchars($order['email']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['phone']): ?>
                            <div>
                                <label class="block text-gray-600 text-sm font-medium mb-1">Телефон</label>
                                <p class="text-lg"><?php echo htmlspecialchars($order['phone']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['address']): ?>
                            <div class="md:col-span-2">
                                <label class="block text-gray-600 text-sm font-medium mb-1">Адрес</label>
                                <p class="text-lg"><?php echo htmlspecialchars($order['address']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['description']): ?>
                            <div class="md:col-span-2">
                                <label class="block text-gray-600 text-sm font-medium mb-1">Описание</label>
                                <p class="text-lg"><?php echo htmlspecialchars($order['description']); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="md:col-span-2 bg-gray-50 rounded-2xl p-4">
                            <h3 class="font-bold text-gray-800 mb-3">Примерная дата готовности</h3>
                            <p class="text-gray-700 mb-3">Текущая: <span
                                    class="font-medium"><?php echo !empty($order['estimated_ready_date']) ? date('d.m.Y H:i', strtotime($order['estimated_ready_date'])) : 'не установлена'; ?></span>
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <form method="POST" class="bg-white p-3 rounded-xl border">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                    <input type="hidden" name="ext_ready_action" value="1">
                                    <input type="hidden" name="ready_mode" value="edit">
                                    <label
                                        class="block text-xs font-medium text-gray-700 mb-1">Установить/изменить</label>
                                    <input type="datetime-local" name="ready_date"
                                        class="w-full border rounded px-2 py-2 mb-2"
                                        value="<?php echo !empty($order['estimated_ready_date']) ? date('Y-m-d\TH:i', strtotime($order['estimated_ready_date'])) : ''; ?>">
                                    <input type="text" name="ready_reason" class="w-full border rounded px-2 py-2 mb-2"
                                        placeholder="Причина (необязательно)">
                                    <button type="submit"
                                        class="w-full px-3 py-2 bg-[#118568] text-white rounded hover:bg-[#0f755a] text-sm">Сохранить</button>
                                </form>
                                <form method="POST" class="bg-white p-3 rounded-xl border">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                    <input type="hidden" name="ext_ready_action" value="1">
                                    <input type="hidden" name="ready_mode" value="prolong">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Продлить (дней)</label>
                                    <input type="number" min="1" name="prolong_days"
                                        class="w-full border rounded px-2 py-2 mb-2" placeholder="Напр., 2">
                                    <input type="text" name="ready_reason" class="w-full border rounded px-2 py-2 mb-2"
                                        placeholder="Причина (необязательно)">
                                    <button type="submit"
                                        class="w-full px-3 py-2 bg-[#17B890] text-white rounded hover:bg-[#14a380] text-sm">Продлить</button>
                                </form>
                            </div>
                            <?php
                            $hstmt = $pdo->prepare("SELECT * FROM external_order_ready_history WHERE external_order_id = ? ORDER BY id DESC LIMIT 5");
                            $hstmt->execute([$order_id]);
                            $ext_history = $hstmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php if (!empty($ext_history)): ?>
                                <div class="mt-2 text-sm text-gray-700">
                                    <div class="font-medium mb-1">Последние изменения (до 5):</div>
                                    <ul class="list-disc ml-5 space-y-1">
                                        <?php foreach ($ext_history as $h): ?>
                                            <li>
                                                <?php echo date('d.m.Y H:i', strtotime($h['created_at'] ?? $h['new_date'])); ?>
                                                —
                                                <?php echo htmlspecialchars($h['change_type']); ?> →
                                                <?php echo date('d.m.Y H:i', strtotime($h['new_date'])); ?>
                                                <?php if (!empty($h['reason'])): ?>
                                                    — <span class="italic"><?php echo htmlspecialchars($h['reason']); ?></span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Позиции заказа -->
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Позиции заказа</h2>
                        <button type="button" id="toggle-ext-add"
                            class="px-4 py-2 text-sm rounded bg-[#118568] text-white hover:bg-[#0f755a]">Добавить
                            позицию</button>
                    </div>

                    <!-- Панель добавления -->
                    <div id="ext-add-panel" class="hidden mb-8 border border-gray-200 rounded-2xl p-4 bg-gray-50">
                        <div class="flex gap-2 mb-4">
                            <button type="button" data-tab="ext-tab-catalog"
                                class="ext-tab px-3 py-2 rounded bg-[#118568] text-white text-sm">Из каталога</button>
                            <button type="button" data-tab="ext-tab-custom"
                                class="ext-tab px-3 py-2 rounded bg-gray-200 text-gray-800 text-sm">Своя
                                позиция</button>
                        </div>
                        <div id="ext-tab-catalog" class="ext-content">
                            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <input type="hidden" name="csrf_token"
                                    value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                <input type="hidden" name="ext_action" value="add_catalog">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Товар</label>
                                    <input type="text" id="ext-search" placeholder="Поиск..."
                                        class="w-full mb-2 border rounded px-3 py-2">
                                    <select id="ext-select" name="product_id" class="w-full border rounded px-3 py-2">
                                        <option value="">— Выберите товар —</option>
                                        <?php
                                        $products_all = $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($products_all as $p): ?>
                                            <option value="<?php echo (int) $p['id']; ?>">
                                                <?php echo htmlspecialchars($p['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Количество</label>
                                    <input type="number" name="quantity" min="1" value="1"
                                        class="w-full border rounded px-3 py-2">
                                </div>
                                <div class="md:col-span-3">
                                    <button type="submit"
                                        class="px-4 py-2 bg-[#118568] text-white rounded hover:bg-[#0f755a]">Добавить</button>
                                </div>
                            </form>
                        </div>
                        <div id="ext-tab-custom" class="ext-content hidden">
                            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <input type="hidden" name="csrf_token"
                                    value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                <input type="hidden" name="ext_action" value="add_custom">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                                    <input type="text" name="item_name" required class="w-full border rounded px-3 py-2"
                                        placeholder="Например, Доп. дизайн">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Кол-во</label>
                                    <input type="number" name="quantity" min="1" value="1" required
                                        class="w-full border rounded px-3 py-2">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                                    <textarea name="item_description" rows="2" class="w-full border rounded px-3 py-2"
                                        placeholder="Комментарий к позиции"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена/ед., ₽ *</label>
                                    <input type="number" step="0.01" min="0.01" name="unit_price" required
                                        class="w-full border rounded px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Расход/ед., ₽</label>
                                    <input type="number" step="0.01" min="0" name="expense_unit"
                                        class="w-full border rounded px-3 py-2">
                                </div>
                                <div class="md:col-span-3">
                                    <button type="submit"
                                        class="px-4 py-2 bg-[#118568] text-white rounded hover:bg-[#0f755a]">Добавить</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($catalog_items)): ?>
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Товары из каталога</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b-2 border-gray-200">
                                            <th class="text-left py-3 px-4 font-medium text-gray-600">Товар</th>
                                            <th class="text-center py-3 px-4 font-medium text-gray-600">Количество</th>
                                            <th class="text-right py-3 px-4 font-medium text-gray-600">Сумма</th>
                                            <th class="text-center py-3 px-4 font-medium text-gray-600">Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($catalog_items as $item): ?>
                                            <tr class="border-b border-gray-100 align-top">
                                                <td class="py-4 px-4">
                                                    <div>
                                                        <p class="font-semibold">
                                                            <?php echo htmlspecialchars($item['product_name'] ?: 'Товар удален'); ?>
                                                        </p>
                                                        <?php if ($item['product_base_price']): ?>
                                                            <p class="text-xs text-gray-600">Базовая цена:
                                                                <?php echo number_format($item['product_base_price'], 0, '', ' '); ?>
                                                                ₽
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 text-center font-medium">
                                                    <?php echo (int) $item['quantity']; ?>
                                                </td>
                                                <td class="py-4 px-4 text-right font-bold text-[#118568]">
                                                    <?php echo number_format($item['price'], 0, '', ' '); ?> ₽
                                                </td>
                                                <td class="py-4 px-4 text-center">
                                                    <form method="POST" class="inline-block mb-2">
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                                        <input type="hidden" name="ext_action" value="edit_item">
                                                        <input type="hidden" name="item_id"
                                                            value="<?php echo (int) $item['id']; ?>">
                                                        <input type="number" name="quantity" min="1"
                                                            value="<?php echo (int) $item['quantity']; ?>"
                                                            class="w-20 border rounded px-2 py-1 text-sm mb-1">
                                                        <button type="submit"
                                                            class="w-full px-2 py-1 bg-[#17B890] text-white text-xs rounded hover:bg-[#14a380]">Сохранить</button>
                                                    </form>
                                                    <form method="POST" onsubmit="return confirm('Удалить позицию?');"
                                                        class="inline-block">
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                                        <input type="hidden" name="ext_action" value="delete_item">
                                                        <input type="hidden" name="item_id"
                                                            value="<?php echo (int) $item['id']; ?>">
                                                        <button type="submit"
                                                            class="w-full px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">Удалить</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($custom_items)): ?>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Пользовательские позиции</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b-2 border-gray-200">
                                            <th class="text-left py-3 px-4 font-medium text-gray-600">Название</th>
                                            <th class="text-left py-3 px-4 font-medium text-gray-600">Описание</th>
                                            <th class="text-center py-3 px-4 font-medium text-gray-600">Кол-во</th>
                                            <th class="text-right py-3 px-4 font-medium text-gray-600">Доход</th>
                                            <th class="text-right py-3 px-4 font-medium text-gray-600">Расход</th>
                                            <th class="text-center py-3 px-4 font-medium text-gray-600">Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($custom_items as $item): ?>
                                            <tr class="border-b border-gray-100 align-top">
                                                <td class="py-4 px-4 font-semibold">
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                </td>
                                                <td class="py-4 px-4 text-gray-600">
                                                    <?php echo htmlspecialchars($item['item_description'] ?: '-'); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center font-medium">
                                                    <?php echo (int) $item['quantity']; ?>
                                                </td>
                                                <td class="py-4 px-4 text-right font-bold text-[#118568]">
                                                    <?php echo number_format($item['price'], 0, '', ' '); ?> ₽
                                                </td>
                                                <td class="py-4 px-4 text-right font-bold text-red-600">
                                                    <?php echo number_format($item['expense_amount'], 0, '', ' '); ?> ₽
                                                </td>
                                                <td class="py-4 px-4 text-center">
                                                    <form method="POST" class="inline-block mb-2 text-left w-40">
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                                        <input type="hidden" name="ext_action" value="edit_item">
                                                        <input type="hidden" name="item_id"
                                                            value="<?php echo (int) $item['id']; ?>">
                                                        <input type="text" name="item_name"
                                                            value="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                            class="border rounded px-2 py-1 text-xs mb-1"
                                                            placeholder="Название">
                                                        <input type="number" name="quantity" min="1"
                                                            value="<?php echo (int) $item['quantity']; ?>"
                                                            class="border rounded px-2 py-1 text-xs mb-1 w-full"
                                                            placeholder="Кол-во">
                                                        <?php $unit_price = $item['quantity'] > 0 ? ($item['price'] / $item['quantity']) : 0; ?>
                                                        <input type="number" step="0.01" min="0.01" name="unit_price"
                                                            value="<?php echo number_format($unit_price, 2, '.', ''); ?>"
                                                            class="border rounded px-2 py-1 text-xs mb-1 w-full"
                                                            placeholder="Цена/ед">
                                                        <?php $expense_unit = $item['quantity'] > 0 ? ($item['expense_amount'] / $item['quantity']) : 0; ?>
                                                        <input type="number" step="0.01" min="0" name="expense_unit"
                                                            value="<?php echo number_format($expense_unit, 2, '.', ''); ?>"
                                                            class="border rounded px-2 py-1 text-xs mb-2 w-full"
                                                            placeholder="Расход/ед">
                                                        <textarea name="item_description" rows="2"
                                                            class="border rounded px-2 py-1 text-xs mb-2 w-full"
                                                            placeholder="Описание"><?php echo htmlspecialchars($item['item_description'] ?: ''); ?></textarea>
                                                        <button type="submit"
                                                            class="w-full px-2 py-1 bg-[#17B890] text-white text-xs rounded hover:bg-[#14a380]">Сохранить</button>
                                                    </form>
                                                    <form method="POST" onsubmit="return confirm('Удалить позицию?');"
                                                        class="inline-block">
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                                        <input type="hidden" name="ext_action" value="delete_item">
                                                        <input type="hidden" name="item_id"
                                                            value="<?php echo (int) $item['id']; ?>">
                                                        <button type="submit"
                                                            class="w-40 px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">Удалить</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($order_items)): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-600">В заказе нет позиций</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Расходы -->
                <?php if (!empty($expenses)): ?>
                    <div class="bg-white rounded-3xl shadow-xl p-8">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">Расходы по заказу</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b-2 border-gray-200">
                                        <th class="text-left py-3 px-4 font-medium text-gray-600">Дата</th>
                                        <th class="text-left py-3 px-4 font-medium text-gray-600">Описание</th>
                                        <th class="text-left py-3 px-4 font-medium text-gray-600">Категория</th>
                                        <th class="text-right py-3 px-4 font-medium text-gray-600">Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr class="border-b border-gray-100">
                                            <td class="py-4 px-4">
                                                <?php echo date('d.m.Y H:i', strtotime($expense['expense_date'])); ?>
                                            </td>
                                            <td class="py-4 px-4">
                                                <?php echo htmlspecialchars($expense['description'] ?: '-'); ?>
                                            </td>
                                            <td class="py-4 px-4">
                                                <?php echo htmlspecialchars($expense['category_name'] ?: '-'); ?>
                                            </td>
                                            <td class="py-4 px-4 text-right font-bold text-red-600">
                                                <?php echo number_format($expense['amount'], 0, '', ' '); ?> ₽
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Боковая панель -->
            <div class="space-y-6">

                <!-- Финансовая сводка -->
                <div class="bg-white rounded-3xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Финансы</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Доход:</span>
                            <span
                                class="font-bold text-[#118568]"><?php echo number_format($order['total_price'], 0, '', ' '); ?>
                                ₽</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Общий расход:</span>
                            <span
                                class="font-bold text-red-600"><?php echo number_format($order['total_expense'] ?: 0, 0, '', ' '); ?>
                                ₽</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Оценочный расход:</span>
                            <span
                                class="font-medium text-gray-700"><?php echo number_format($order['estimated_expense'] ?: 0, 0, '', ' '); ?>
                                ₽</span>
                        </div>
                        <?php if ($total_custom_expense > 0): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Польз. расходы:</span>
                                <span
                                    class="font-medium text-red-600"><?php echo number_format($total_custom_expense, 0, '', ' '); ?>
                                    ₽</span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Налог:</span>
                            <span
                                class="font-medium text-gray-700"><?php echo number_format($order['tax_amount'] ?: 0, 0, '', ' '); ?>
                                ₽</span>
                        </div>
                        <hr class="border-gray-200">
                        <div class="flex justify-between text-lg">
                            <span class="font-medium">Прибыль:</span>
                            <span
                                class="font-bold <?php echo ($order['profit'] ?: 0) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo number_format($order['profit'] ?: 0, 0, '', ' '); ?> ₽
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Управление статусом -->
                <div class="bg-white rounded-3xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Управление</h3>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_status">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Статус оплаты</label>
                            <select name="status"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                                <option value="unpaid" <?php echo $order['status'] === 'unpaid' ? 'selected' : ''; ?>>Не
                                    оплачен</option>
                                <option value="partial" <?php echo $order['status'] === 'partial' ? 'selected' : ''; ?>>
                                    Частично</option>
                                <option value="paid" <?php echo $order['status'] === 'paid' ? 'selected' : ''; ?>>Оплачен
                                </option>
                            </select>
                        </div>
                        <button type="submit"
                            class="w-full py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition font-medium">
                            Обновить статус
                        </button>
                    </form>
                </div>

                <!-- Статистика -->
                <div class="bg-white rounded-3xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Статистика</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Всего позиций:</span>
                            <span class="font-medium"><?php echo count($order_items); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Из каталога:</span>
                            <span class="font-medium"><?php echo count($catalog_items); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Пользовательских:</span>
                            <span class="font-medium"><?php echo count($custom_items); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('toggle-ext-add');
        const panel = document.getElementById('ext-add-panel');
        if (toggleBtn && panel) {
            toggleBtn.addEventListener('click', () => {
                panel.classList.toggle('hidden');
            });
        }
        const tabs = document.querySelectorAll('.ext-tab');
        tabs.forEach(btn => {
            btn.addEventListener('click', () => {
                tabs.forEach(b => b.classList.remove('bg-[#118568]', 'text-white'));
                tabs.forEach(b => b.classList.add('bg-gray-200', 'text-gray-800'));
                btn.classList.remove('bg-gray-200', 'text-gray-800');
                btn.classList.add('bg-[#118568]', 'text-white');
                const target = btn.dataset.tab;
                document.querySelectorAll('.ext-content').forEach(c => c.classList.add('hidden'));
                const el = document.getElementById(target);
                if (el) el.classList.remove('hidden');
            });
        });
        // Поиск по товарам
        const searchInput = document.getElementById('ext-search');
        const select = document.getElementById('ext-select');
        if (searchInput && select) {
            searchInput.addEventListener('input', () => {
                const term = searchInput.value.toLowerCase();
                Array.from(select.options).forEach(opt => {
                    if (!opt.value) return; // пропустить placeholder
                    const match = opt.text.toLowerCase().includes(term);
                    opt.style.display = match ? '' : 'none';
                });
            });
        }
    });
</script>

<?php include_once('../../includes/footer.php'); ?>