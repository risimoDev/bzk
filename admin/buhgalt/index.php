<?php
// admin/buhgalt/index.php
session_start();
$pageTitle = "Бухгалтерия";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
  header("Location: /login");
  exit();
}

include_once('../../includes/header.php');
include_once('../../includes/db.php');
include_once('functions.php');

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
  $notifications = $_SESSION['notifications'];
  unset($_SESSION['notifications']);
}

// Получение фильтров
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Если даты не заданы, показываем за текущий месяц
if (!$start_date) {
  $start_date = date('Y-m-01');
}
if (!$end_date) {
  $end_date = date('Y-m-t'); // Последний день текущего месяца
}
$end_date_inclusive = date('Y-m-d 23:59:59', strtotime($end_date));

// Статистика
$total_income = get_total_income($pdo, $start_date, $end_date);
$order_expenses = get_total_expense($pdo, $start_date, $end_date);             // из order_expenses
$general_expenses = get_total_general_expenses($pdo, $start_date, $end_date);    // из general_expenses
$total_expense = $order_expenses + $general_expenses;

// Сумма налогов за период
$stmt = $pdo->prepare("
    SELECT SUM(tax_amount) AS total_tax 
    FROM orders_accounting 
    WHERE created_at BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date_inclusive]);
$total_tax = $stmt->fetchColumn() ?? 0;

// Итоговый расход с налогом (для отображения в карточке «Общий расход» оставить без налогов или с — на твой вкус)
// Ниже оставим в карточке «Общий расход» сумму БЕЗ налогов, а налоги — отдельной карточкой (как у тебя уже сделано).
// Прибыль учитывает налог:
$total_profit = calculate_profit($total_income, $total_expense, $total_tax);

// Расходы по категориям (оба источника)
$expenses_by_category = get_expenses_by_category($pdo, $start_date, $end_date);

// Список общих расходов (таблица ниже)
$stmtGE = $pdo->prepare("
    SELECT 
        ge.id,
        ge.amount,
        ge.description,
        ge.expense_date,
        ec.name AS category_name
    FROM general_expenses ge
    LEFT JOIN expenses_categories ec ON ge.category_id = ec.id
    WHERE ge.expense_date BETWEEN ? AND ?
    ORDER BY ge.expense_date DESC, ge.id DESC
");
$stmtGE->execute([$start_date, $end_date_inclusive]);
$general_expense_list = $stmtGE->fetchAll(PDO::FETCH_ASSOC);

// Пагинация для заказов
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10;
$total_orders_count = get_total_orders_count($pdo, $start_date, $end_date);
$total_pages = ceil($total_orders_count / $limit);

$orders = get_orders_with_finances($pdo, $start_date, $end_date, $page, $limit);
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Крошки и кнопки -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex flex-wrap gap-2">
        <?php echo backButton(); ?>
        <a href="add_general_expense"
          class="px-4 py-2 bg-[#5E807F] text-white rounded-lg hover:bg-[#4a6665] transition-colors duration-300 text-sm">
          + Общий расход
        </a>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Бухгалтерия</h1>
      <p class="text-xl text-gray-700">Финансовая сводка и управление</p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div
        class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Фильтры -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Фильтры</h2>
      <form method="GET" class="flex flex-col md:flex-row gap-4">
        <div>
          <label for="start_date" class="block text-sm font-medium text-gray-700">Дата от</label>
          <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#118568] focus:ring-[#17B890] sm:text-sm">
        </div>
        <div>
          <label for="end_date" class="block text-sm font-medium text-gray-700">Дата до</label>
          <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#118568] focus:ring-[#17B890] sm:text-sm">
        </div>
        <div class="flex items-end">
          <button type="submit"
            class="px-4 py-2 bg-[#17B890] text-white rounded-md hover:bg-[#14a380] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#17B890]">
            Применить
          </button>
        </div>
      </form>
    </div>

    <!-- Сводка -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-2xl font-bold text-[#118568] mb-2"><?php echo number_format($total_income, 2, '.', ' '); ?> ₽
        </div>
        <div class="text-gray-600">Общий доход</div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-2xl font-bold text-red-500 mb-2"><?php echo number_format($total_expense, 2, '.', ' '); ?> ₽
        </div>
        <div class="text-gray-600">Общий расход (без налогов)</div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-2xl font-bold text-yellow-600 mb-2"><?php echo number_format($total_tax, 2, '.', ' '); ?> ₽
        </div>
        <div class="text-gray-600">Налоги</div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div
          class="w-12 h-12 <?php echo $total_profit >= 0 ? 'bg-[#17B890]' : 'bg-red-500'; ?> rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 11l3-3m0 0l3 3m-3-3v8m0-13a9 9 0 110 18 9 9 0 010-18z" />
          </svg>
        </div>
        <div class="text-2xl font-bold <?php echo $total_profit >= 0 ? 'text-[#17B890]' : 'text-red-500'; ?> mb-2">
          <?php echo number_format($total_profit, 2, '.', ' '); ?> ₽
        </div>
        <div class="text-gray-600">Чистая прибыль</div>
      </div>
    </div>

    <!-- Расходы по категориям -->
    <?php if (!empty($expenses_by_category)): ?>
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-2xl font-bold text-gray-800">Расходы по категориям</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Категория</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Сумма</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($expenses_by_category as $expense): ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?php echo htmlspecialchars($expense['category_name'] ?? 'Без категории'); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php echo number_format($expense['total_expense'], 2, '.', ' '); ?> ₽
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- Общие расходы компании (детализация) -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-800">Общие расходы компании</h2>
        <a href="add_general_expense"
          class="px-3 py-2 bg-[#5E807F] text-white text-sm rounded-lg hover:bg-[#4a6665] transition-colors duration-300">
          + Добавить общий расход
        </a>
      </div>

      <?php if (empty($general_expense_list)): ?>
        <div class="text-gray-500">За выбранный период общих расходов не найдено.</div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Категория</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Комментарий
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($general_expense_list as $ge): ?>
                <tr class="hover:bg-[#f8fafa] transition-colors duration-300">
                  <td class="px-6 py-4 text-sm text-gray-700">
                    <?php echo date('d.m.Y H:i', strtotime($ge['expense_date'])); ?>
                  </td>
                  <td class="px-6 py-4 text-sm font-medium text-gray-900">
                    <?php echo htmlspecialchars($ge['category_name'] ?? 'Без категории'); ?>
                  </td>
                  <td class="px-6 py-4 text-sm font-semibold text-red-500">
                    <?php echo number_format($ge['amount'], 2, '.', ' '); ?> ₽
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-600">
                    <?php echo htmlspecialchars($ge['description'] ?? ''); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Таблица заказов -->
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="p-6 border-b border-[#DEE5E5] flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Заказы и Финансы</h2>
        <div class="text-gray-600 text-sm">
          Найдено: <?php echo $total_orders_count; ?> записей
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-[#118568] text-white">
            <tr>
              <th class="py-4 px-6 text-left">ID</th>
              <th class="py-4 px-6 text-left">Источник</th>
              <th class="py-4 px-6 text-left">Клиент</th>
              <th class="py-4 px-6 text-left">Дата</th>
              <th class="py-4 px-6 text-left">Доход</th>
              <th class="py-4 px-6 text-left">Расход</th>
              <th class="py-4 px-6 text-left">Налог</th>
              <th class="py-4 px-6 text-left">Прибыль</th>
              <th class="py-4 px-6 text-left">Статус</th>
              <th class="py-4 px-6 text-left">Действия</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#DEE5E5]">
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="10" class="py-8 px-6 text-center text-gray-500">
                  Нет записей по выбранным критериям.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $order): ?>
                <tr class="hover:bg-[#f8fafa] transition-colors duration-300">
                  <td class="py-4 px-6 font-medium">#<?php echo htmlspecialchars($order['id']); ?></td>
                  <td class="py-4 px-6">
                    <span
                      class="px-2 py-1 text-xs rounded-full <?php echo $order['source'] === 'site' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                      <?php echo $order['source'] === 'site' ? 'Сайт' : 'Внешний'; ?>
                    </span>
                    <?php if ($order['source'] === 'site' && $order['order_id']): ?>
                      <div class="text-xs text-gray-500">Заказ #<?php echo $order['order_id']; ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-6">
                    <?php if ($order['source'] === 'site' && $order['client_name_from_order']): ?>
                      <?php echo htmlspecialchars($order['client_name_from_order']); ?>
                    <?php elseif ($order['client_name']): ?>
                      <?php echo htmlspecialchars($order['client_name']); ?>
                    <?php else: ?>
                      <span class="text-gray-500">Не указан</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-6 text-gray-600 text-sm">
                    <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                  </td>
                  <td class="py-4 px-6 font-bold text-[#118568]"><?php echo number_format($order['income'], 2, '.', ' '); ?>
                    ₽</td>
                  <td class="py-4 px-6 font-medium text-red-500">
                    <?php echo number_format($order['total_expense'], 2, '.', ' '); ?> ₽
                  </td>
                  <td class="py-4 px-6 font-bold"><?php echo number_format($order['tax_amount'], 2, '.', ' '); ?> ₽</td>
                  <td
                    class="py-4 px-6 font-bold <?php echo (calculate_profit($order['income'], $order['total_expense'], $order['tax_amount']) >= 0) ? 'text-[#17B890]' : 'text-red-500'; ?>">
                    <?php echo number_format(calculate_profit($order['income'], $order['total_expense'], $order['tax_amount']), 2, '.', ' '); ?>
                    ₽
                  </td>
                  <td class="py-4 px-6">
                    <span class="px-2 py-1 text-xs rounded-full 
                    <?php
                    switch ($order['status']) {
                      case 'paid':
                        echo 'bg-green-100 text-green-800';
                        break;
                      case 'partial':
                        echo 'bg-yellow-100 text-yellow-800';
                        break;
                      case 'unpaid':
                        echo 'bg-red-100 text-red-800';
                        break;
                      default:
                        echo 'bg-gray-100 text-gray-800';
                    }
                    ?>">
                      <?php
                      $status_names = [
                        'unpaid' => 'Не оплачен',
                        'partial' => 'Частично',
                        'paid' => 'Оплачен'
                      ];
                      echo $status_names[$order['status']] ?? $order['status'];
                      ?>
                    </span>
                  </td>
                  <td class="py-4 px-6">
                    <div class="flex flex-wrap gap-1">
                      <a href="add_expense.php?order_accounting_id=<?php echo $order['id']; ?>"
                        class="px-2 py-1 bg-[#5E807F] text-white text-xs rounded hover:bg-[#4a6665] transition-colors duration-300"
                        title="Добавить расход">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24"
                          stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                      </a>
                      <a href="add_payment.php?order_accounting_id=<?php echo $order['id']; ?>"
                        class="px-2 py-1 bg-[#17B890] text-white text-xs rounded hover:bg-[#14a380] transition-colors duration-300"
                        title="Добавить платеж">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24"
                          stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Пагинация -->
    <?php if ($total_pages > 1): ?>
      <div class="flex justify-center mt-8">
        <div class="bg-white rounded-2xl shadow-lg px-4 py-2 flex items-center space-x-1">
          <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"
              class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5] transition-colors duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </a>
          <?php endif; ?>

          <?php
          $start_page = max(1, $page - 2);
          $end_page = min($total_pages, $page + 2);
          for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?page=<?php echo $i; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"
              class="w-8 h-8 flex items-center justify-center rounded-lg <?php echo $i == $page ? 'bg-[#118568] text-white' : 'hover:bg-[#DEE5E5]'; ?> transition-colors duration-300 text-sm">
              <?php echo $i; ?>
            </a>
          <?php endfor; ?>

          <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"
              class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5] transition-colors duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main><?php include_once('../../includes/footer.php'); ?>