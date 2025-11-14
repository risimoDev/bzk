<?php
session_start();
$pageTitle = "Статистика сайта";

// Проверка доступа
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

include_once('../includes/db.php');

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

// --- Улучшено: Расширенная статистика ---
// Количество пользователей
$stmt_users = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_users->execute([$start_date, $end_date]);
$total_users = $stmt_users->fetchColumn();

// Количество заказов
$stmt_orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_orders->execute([$start_date, $end_date]);
// Количество заказов (внутренние + внешние)
$stmt_orders_int = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_orders_int->execute([$start_date, $end_date]);
$count_int = (int) $stmt_orders_int->fetchColumn();
$stmt_orders_ext = $pdo->prepare("SELECT COUNT(*) FROM external_orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_orders_ext->execute([$start_date, $end_date]);
$count_ext = (int) $stmt_orders_ext->fetchColumn();
$total_orders = $count_int + $count_ext;

// Сообщений через форму
$stmt_messages = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_messages->execute([$start_date, $end_date]);
$total_messages = $stmt_messages->fetchColumn();

// Количество товаров
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Количество категорий
$total_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Количество партнеров
$total_partners = $pdo->query("SELECT COUNT(*) FROM partners")->fetchColumn();

// Общий доход
$stmt_income = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_income->execute([$start_date, $end_date]);
// Общий доход (внутренние + внешние)
$stmt_income_int = $pdo->prepare("SELECT COALESCE(SUM(total_price),0) FROM orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_income_int->execute([$start_date, $end_date]);
$income_int = (float) $stmt_income_int->fetchColumn();
$stmt_income_ext = $pdo->prepare("SELECT COALESCE(SUM(total_price),0) FROM external_orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_income_ext->execute([$start_date, $end_date]);
$income_ext = (float) $stmt_income_ext->fetchColumn();
$total_income = $income_int + $income_ext;

// Общий расход
$stmt_expense = $pdo->prepare("SELECT COALESCE(SUM(total_expense), 0) FROM orders_accounting WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
$stmt_expense->execute([$start_date, $end_date]);
$total_expense = $stmt_expense->fetchColumn();

// Чистая прибыль
$total_profit = $total_income - $total_expense;

// Заказы по статусам
$stmt_status = $pdo->prepare("
    SELECT status, COUNT(*) as cnt 
    FROM orders 
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY status
");
$stmt_status->execute([$start_date, $end_date]);
// Заказы по статусам (агрегируем внутренние и внешние production_status)
$orders_by_status = [];
$stmt_status_int = $pdo->prepare("SELECT status, COUNT(*) cnt FROM orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY status");
$stmt_status_int->execute([$start_date, $end_date]);
foreach ($stmt_status_int->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $orders_by_status[$r['status']] = (int) $r['cnt'];
}
$stmt_status_ext = $pdo->prepare("SELECT production_status AS status, COUNT(*) cnt FROM external_orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY production_status");
$stmt_status_ext->execute([$start_date, $end_date]);
foreach ($stmt_status_ext->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $orders_by_status[$r['status']] = ($orders_by_status[$r['status']] ?? 0) + (int) $r['cnt'];
}

// Заказы по месяцам (график)
$stmt_orders_monthly = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt 
    FROM orders 
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY month 
    ORDER BY month ASC
");
$stmt_orders_monthly->execute([$start_date, $end_date]);
// Заказы по месяцам (график объединённый)
$stats_int = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c FROM orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY m ORDER BY m ASC");
$stats_int->execute([$start_date, $end_date]);
$int_map = [];
foreach ($stats_int->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $int_map[$r['m']] = (int) $r['c'];
}
$stats_ext = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c FROM external_orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY m ORDER BY m ASC");
$stats_ext->execute([$start_date, $end_date]);
$ext_map = [];
foreach ($stats_ext->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $ext_map[$r['m']] = (int) $r['c'];
}
$all_months = array_unique(array_merge(array_keys($int_map), array_keys($ext_map)));
sort($all_months);
$orders_stats = [];
foreach ($all_months as $m) {
  $orders_stats[] = ['month' => $m, 'cnt' => ($int_map[$m] ?? 0) + ($ext_map[$m] ?? 0)];
}

// Пользователи по месяцам
$stmt_users_monthly = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt 
    FROM users 
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY month 
    ORDER BY month ASC
");
$stmt_users_monthly->execute([$start_date, $end_date]);
$users_stats = $stmt_users_monthly->fetchAll(PDO::FETCH_ASSOC);

// Преобразуем в JSON для JS
$orders_chart_labels = json_encode(array_column($orders_stats, 'month'));
$orders_chart_data = json_encode(array_column($orders_stats, 'cnt'));

$users_chart_labels = json_encode(array_column($users_stats, 'month'));
$users_chart_data = json_encode(array_column($users_stats, 'cnt'));

// 🔹 Яндекс.Метрика (заглушка, пока нет токена)
$yandex_visits = 0;
$yandex_views = 0;
$yandex_users = 0;

// --- Добавлено: Статистика по бухгалтерии ---
// Расходы по категориям
$stmt_expenses_by_category = $pdo->prepare("
    SELECT ec.name as category_name, COALESCE(SUM(oe.amount), 0) as total_expense
    FROM order_expenses oe
    JOIN orders_accounting oa ON oe.order_accounting_id = oa.id
    LEFT JOIN expenses_categories ec ON oe.category_id = ec.id
    WHERE oa.created_at >= ? AND oa.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY ec.id, ec.name
    ORDER BY total_expense DESC
    LIMIT 5
");
$stmt_expenses_by_category->execute([$start_date, $end_date]);
$top_expenses_by_category = $stmt_expenses_by_category->fetchAll(PDO::FETCH_ASSOC);

// Топ 5 заказов по доходу
$stmt_top_orders = $pdo->prepare("
    SELECT o.id, o.total_price, u.name as client_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY o.total_price DESC
    LIMIT 5
");
$stmt_top_orders->execute([$start_date, $end_date]);
// Топ 5 заказов по доходу (объединённый)
$stmt_top = $pdo->prepare("SELECT id, total_price, client_name, src FROM (
  SELECT o.id, o.total_price, u.name AS client_name, 'internal' AS src
  FROM orders o LEFT JOIN users u ON o.user_id = u.id
  WHERE o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
  UNION ALL
  SELECT eo.id, eo.total_price, eo.client_name, 'external' AS src
  FROM external_orders eo
  WHERE eo.created_at >= ? AND eo.created_at < DATE_ADD(?, INTERVAL 1 DAY)
) t ORDER BY total_price DESC LIMIT 5");
$stmt_top->execute([$start_date, $end_date, $start_date, $end_date]);
$top_orders = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

// Топ 5 товаров по количеству заказов
$stmt_top_products = $pdo->prepare("
    SELECT p.name, COUNT(oi.product_id) as order_count, SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY oi.product_id, p.name
    ORDER BY order_count DESC
    LIMIT 5
");
$stmt_top_products->execute([$start_date, $end_date]);
$top_products = $stmt_top_products->fetchAll(PDO::FETCH_ASSOC);
// --- Конец добавленного кода ---
?>

<?php include_once('../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin"
          class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">
          Админ-панель
        </a>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Статистика сайта</h1>
      <p class="text-xl text-gray-700">Полная аналитика и финансовые показатели</p>
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
          <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Дата от</label>
          <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#118568] focus:ring-[#17B890] sm:text-sm">
        </div>
        <div>
          <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">Дата до</label>
          <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#118568] focus:ring-[#17B890] sm:text-sm">
        </div>
        <div class="flex items-end">
          <button type="submit"
            class="px-4 py-2 bg-[#118568] text-white rounded-md hover:bg-[#0f755a] transition-colors duration-300">
            Применить
          </button>
        </div>
      </form>
    </div>

    <!-- Основная статистика -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo $total_users; ?></div>
        <div class="text-gray-600">Пользователей</div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#17B890] mb-2"><?php echo $total_orders; ?></div>
        <div class="text-gray-600">Заказов</div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#5E807F] mb-2"><?php echo $total_messages; ?></div>
        <div class="text-gray-600">Сообщений</div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#9DC5BB] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#9DC5BB] mb-2"><?php echo $total_products; ?></div>
        <div class="text-gray-600">Товаров</div>
      </div>
    </div>

    <!-- Финансовая статистика 
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo number_format($total_income, 2, '.', ' '); ?> ₽</div>
        <div class="text-gray-600">Общий доход</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-red-500 mb-2"><?php echo number_format($total_expense, 2, '.', ' '); ?> ₽</div>
        <div class="text-gray-600">Общий расход</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 <?php echo $total_profit >= 0 ? 'bg-[#17B890]' : 'bg-red-500'; ?> rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3-3m0 0l3 3m-3-3v8m0-13a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-3xl font-bold <?php echo $total_profit >= 0 ? 'text-[#17B890]' : 'text-red-500'; ?> mb-2"><?php echo number_format($total_profit, 2, '.', ' '); ?> ₽</div>
        <div class="text-gray-600">Чистая прибыль</div>
      </div>
    </div>

    <!-- Статистика заказов по статусам -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Заказы по статусам</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php
        $status_names = [
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
        ?>
        <?php foreach ($status_names as $status_key => $status_name): ?>
          <div class="text-center p-4 bg-gray-50 rounded-xl hover:bg-white hover:shadow-lg transition-all duration-300">
            <div class="text-2xl font-bold <?php echo $status_colors[$status_key]; ?> mb-2">
              <?php echo $orders_by_status[$status_key] ?? 0; ?>
            </div>
            <div class="text-sm text-gray-600"><?php echo $status_name; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Графики -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-3">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Заказы по месяцам</h2>
      <div class="relative h-72">
        <canvas id="ordersChart"></canvas>
      </div>
    </div>

    <!-- График пользователей -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Регистрация пользователей</h2>
      <div class="relative h-72">
        <canvas id="usersChart"></canvas>
      </div>
    </div>

    <!-- Расходы по категориям -->
    <?php if (!empty($top_expenses_by_category)): ?>
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Топ 5 расходов по категориям</h2>
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
              <?php foreach ($top_expenses_by_category as $expense): ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?php echo htmlspecialchars($expense['category_name'] ?? 'Без категории'); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php echo number_format($expense['total_expense'], 0, '', ' '); ?> ₽
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- Топ 5 заказов по доходу -->
    <?php if (!empty($top_orders)): ?>
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Топ 5 заказов по доходу</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Клиент</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Сумма</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($top_orders as $order): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <a href="<?php echo $order['src'] === 'external' ? '/admin/order/external_details.php?id=' : '/admin/order/details?id='; ?><?php echo $order['id']; ?>"
                      class="text-[#118568] hover:underline">
                      #<?php echo htmlspecialchars($order['id']); ?>
                    </a>
                    <div class="text-xs text-gray-500 mt-1">Источник:
                      <?php echo $order['src'] === 'external' ? 'Внешний' : 'Сайт'; ?></div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php echo htmlspecialchars($order['client_name'] ?? 'Не указан'); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-[#118568]">
                    <?php echo number_format($order['total_price'], 0, '', ' '); ?> ₽
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- Топ 5 товаров по количеству заказов -->
    <?php if (!empty($top_products)): ?>
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Топ 5 товаров по количеству заказов</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Товар</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Кол-во заказов</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Общее кол-во</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($top_products as $product): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?php echo htmlspecialchars($product['name']); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php echo $product['order_count']; ?> шт.
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-[#118568]">
                    <?php echo $product['total_quantity']; ?> ед.
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- Яндекс.Метрика -->
    <div class="bg-white rounded-2xl shadow-xl p-6">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Яндекс.Метрика</h2>
      <p class="text-gray-600 mb-6">Для подключения нужно указать API-токен.</p>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-[#DEE5E5] rounded-xl p-4 text-center">
          <h3 class="text-gray-600 mb-2">Визиты</h3>
          <p class="text-2xl font-bold text-[#118568]"><?php echo $yandex_visits; ?></p>
        </div>
        <div class="bg-[#9DC5BB] rounded-xl p-4 text-center">
          <h3 class="text-gray-600 mb-2">Просмотры</h3>
          <p class="text-2xl font-bold text-[#17B890]"><?php echo $yandex_views; ?></p>
        </div>
        <div class="bg-[#5E807F] rounded-xl p-4 text-center">
          <h3 class="text-white mb-2">Пользователи</h3>
          <p class="text-2xl font-bold text-white"><?php echo $yandex_users; ?></p>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Подключение Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Инициализация графиков
    var ordersCtx = document.getElementById('ordersChart').getContext('2d');
    var ordersChart = new Chart(ordersCtx, {
      type: 'line',
      data: {
        labels: <?php echo $orders_chart_labels; ?>,
        datasets: [{
          label: 'Заказы',
          data: <?php echo $orders_chart_data; ?>,
          borderColor: '#118568',
          backgroundColor: 'rgba(17,133,104,0.2)',
          tension: 0.3,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            },
            ticks: {
              precision: 0
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      }
    });

    var usersCtx = document.getElementById('usersChart').getContext('2d');
    var usersChart = new Chart(usersCtx, {
      type: 'line',
      data: {
        labels: <?php echo $users_chart_labels; ?>,
        datasets: [{
          label: 'Регистрации',
          data: <?php echo $users_chart_data; ?>,
          borderColor: '#17B890',
          backgroundColor: 'rgba(23,184,144,0.2)',
          tension: 0.3,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            },
            ticks: {
              precision: 0
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      }
    });
  });
</script>

<?php include_once('../includes/footer.php'); ?>