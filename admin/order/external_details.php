<?php
session_start();
$pageTitle = "Детали внешнего заказа";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';
require_once '../buhgalt/functions.php';

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

// Получение позиций заказа
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
        <a href="/admin/order/external_orders.php" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition text-sm font-medium">
          ← К списку
        </a>
        <?php if ($order['accounting_id']): ?>
          <a href="/admin/buhgalt/order_accounting.php?external_id=<?php echo $order_id; ?>" class="px-5 py-2.5 bg-[#17B890] text-white rounded-xl hover:bg-[#15a081] transition text-sm font-medium">
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
      <div class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
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
                switch($order['status']) {
                  case 'paid': echo 'bg-green-100 text-green-800'; break;
                  case 'partial': echo 'bg-yellow-100 text-yellow-800'; break;
                  default: echo 'bg-red-100 text-red-800'; break;
                }
              ?>">
              <?php 
                switch($order['status']) {
                  case 'paid': echo 'Оплачен'; break;
                  case 'partial': echo 'Частично'; break;
                  default: echo 'Не оплачен'; break;
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
          </div>
        </div>

        <!-- Позиции заказа -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Позиции заказа</h2>
          
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
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($catalog_items as $item): ?>
                      <tr class="border-b border-gray-100">
                        <td class="py-4 px-4">
                          <div>
                            <p class="font-semibold"><?php echo htmlspecialchars($item['product_name'] ?: 'Товар удален'); ?></p>
                            <?php if ($item['product_base_price']): ?>
                              <p class="text-sm text-gray-600">Базовая цена: <?php echo number_format($item['product_base_price'], 0, '', ' '); ?> ₽</p>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td class="py-4 px-4 text-center font-medium"><?php echo $item['quantity']; ?></td>
                        <td class="py-4 px-4 text-right font-bold text-[#118568]"><?php echo number_format($item['price'], 0, '', ' '); ?> ₽</td>
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
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($custom_items as $item): ?>
                      <tr class="border-b border-gray-100">
                        <td class="py-4 px-4 font-semibold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td class="py-4 px-4 text-gray-600"><?php echo htmlspecialchars($item['item_description'] ?: '-'); ?></td>
                        <td class="py-4 px-4 text-center font-medium"><?php echo $item['quantity']; ?></td>
                        <td class="py-4 px-4 text-right font-bold text-[#118568]"><?php echo number_format($item['price'], 0, '', ' '); ?> ₽</td>
                        <td class="py-4 px-4 text-right font-bold text-red-600"><?php echo number_format($item['expense_amount'], 0, '', ' '); ?> ₽</td>
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
                      <td class="py-4 px-4"><?php echo date('d.m.Y H:i', strtotime($expense['expense_date'])); ?></td>
                      <td class="py-4 px-4"><?php echo htmlspecialchars($expense['description'] ?: '-'); ?></td>
                      <td class="py-4 px-4"><?php echo htmlspecialchars($expense['category_name'] ?: '-'); ?></td>
                      <td class="py-4 px-4 text-right font-bold text-red-600"><?php echo number_format($expense['amount'], 0, '', ' '); ?> ₽</td>
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
              <span class="font-bold text-[#118568]"><?php echo number_format($order['total_price'], 0, '', ' '); ?> ₽</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Общий расход:</span>
              <span class="font-bold text-red-600"><?php echo number_format($order['total_expense'] ?: 0, 0, '', ' '); ?> ₽</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Оценочный расход:</span>
              <span class="font-medium text-gray-700"><?php echo number_format($order['estimated_expense'] ?: 0, 0, '', ' '); ?> ₽</span>
            </div>
            <?php if ($total_custom_expense > 0): ?>
              <div class="flex justify-between">
                <span class="text-gray-600">Польз. расходы:</span>
                <span class="font-medium text-red-600"><?php echo number_format($total_custom_expense, 0, '', ' '); ?> ₽</span>
              </div>
            <?php endif; ?>
            <div class="flex justify-between">
              <span class="text-gray-600">Налог:</span>
              <span class="font-medium text-gray-700"><?php echo number_format($order['tax_amount'] ?: 0, 0, '', ' '); ?> ₽</span>
            </div>
            <hr class="border-gray-200">
            <div class="flex justify-between text-lg">
              <span class="font-medium">Прибыль:</span>
              <span class="font-bold <?php echo ($order['profit'] ?: 0) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
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
              <select name="status" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                <option value="unpaid" <?php echo $order['status'] === 'unpaid' ? 'selected' : ''; ?>>Не оплачен</option>
                <option value="partial" <?php echo $order['status'] === 'partial' ? 'selected' : ''; ?>>Частично</option>
                <option value="paid" <?php echo $order['status'] === 'paid' ? 'selected' : ''; ?>>Оплачен</option>
              </select>
            </div>
            <button type="submit" class="w-full py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition font-medium">
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

<?php include_once('../../includes/footer.php'); ?>