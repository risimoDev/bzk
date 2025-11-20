<?php
session_start();
$pageTitle = "История заказов";

// Подключение к базе данных
include_once('../includes/db.php');
include_once('../includes/security.php');
include_once('../includes/common.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: /login");
  exit();
}

$user_id = $_SESSION['user_id'];

// Получение параметров фильтрации
$filter_status = $_GET['status'] ?? 'all';
$filter_sort = $_GET['sort'] ?? 'date_desc';

// Формирование SQL-запроса с учетом фильтров
$sql = "SELECT o.id, o.total_price, o.status, o.created_at FROM orders o WHERE o.user_id = ?";
$params = [$user_id];

if ($filter_status !== 'all') {
  $sql .= " AND o.status = ?";
  $params[] = $filter_status;
}

// Добавление сортировки
switch ($filter_sort) {
  case 'date_asc':
    $sql .= " ORDER BY o.created_at ASC";
    break;
  case 'price_desc':
    $sql .= " ORDER BY o.total_price DESC";
    break;
  case 'price_asc':
    $sql .= " ORDER BY o.total_price ASC";
    break;
  case 'date_desc':
  default:
    $sql .= " ORDER BY o.created_at DESC";
    break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Массив для перевода статусов
$statuses = [
  'pending' => 'В ожидании',
  'processing' => 'В обработке',
  'in_work' => 'В работе',
  'delayed' => 'Задерживается',
  'shipped' => 'Отправлен',
  'delivered' => 'Доставлен',
  'cancelled' => 'Отменен',
  'completed' => 'Полностью готов'
];

$status_colors = [
  'pending' => 'bg-yellow-100 text-yellow-800',
  'processing' => 'bg-blue-100 text-blue-800',
  'in_work' => 'bg-orange-100 text-orange-800',
  'delayed' => 'bg-red-200 text-red-800',
  'shipped' => 'bg-purple-100 text-purple-800',
  'delivered' => 'bg-indigo-100 text-indigo-800',
  'cancelled' => 'bg-red-100 text-red-800',
  'completed' => 'bg-green-100 text-green-800'
];

// Получение уникальных статусов для фильтра
$stmt_statuses = $pdo->prepare("SELECT DISTINCT status FROM orders WHERE user_id = ?");
$stmt_statuses->execute([$user_id]);
$available_statuses = $stmt_statuses->fetchAll(PDO::FETCH_COLUMN);
?>

<?php include_once('../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <?php echo backButton(); ?>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">История заказов</h1>
      <p class="text-xl text-gray-700">Все ваши заказы в одном месте</p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <?php if (empty($orders)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#5E807F]" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">У вас пока нет заказов</h2>
        <p class="text-gray-600 mb-8">Совершите свой первый заказ прямо сейчас</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/catalog"
            class="px-6 py-3 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Перейти в каталог
          </a>
          <a href="/client/dashboard"
            class="px-6 py-3 bg-[#DEE5E5] text-[#118568] rounded-xl hover:bg-[#9DC5BB] transition-colors duration-300 font-bold text-lg">
            Вернуться в кабинет
          </a>
        </div>
      </div>
    <?php else: ?>
      <!-- Фильтры и сортировка -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
          <h2 class="text-2xl font-bold text-gray-800">Фильтры и сортировка</h2>
          <div class="text-gray-600">
            Найдено: <?php echo count($orders); ?>
            <?php echo count($orders) == 1 ? 'заказ' : (count($orders) < 5 ? 'заказа' : 'заказов'); ?>
          </div>
        </div>

        <form method="GET" class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Статус заказа</label>
            <select id="status" name="status" onchange="this.form.submit()"
              class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
              <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Все статусы</option>
              <?php foreach ($available_statuses as $status): ?>
                <option value="<?php echo $status; ?>" <?php echo $filter_status === $status ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($statuses[$status] ?? $status); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Сортировка</label>
            <select id="sort" name="sort" onchange="this.form.submit()"
              class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
              <option value="date_desc" <?php echo $filter_sort === 'date_desc' ? 'selected' : ''; ?>>Дата (новые первые)
              </option>
              <option value="date_asc" <?php echo $filter_sort === 'date_asc' ? 'selected' : ''; ?>>Дата (старые первые)
              </option>
              <option value="price_desc" <?php echo $filter_sort === 'price_desc' ? 'selected' : ''; ?>>Цена (высокая)
              </option>
              <option value="price_asc" <?php echo $filter_sort === 'price_asc' ? 'selected' : ''; ?>>Цена (низкая)</option>
            </select>
          </div>

          <?php if ($filter_status !== 'all' || $filter_sort !== 'date_desc'): ?>
            <div class="flex items-end">
              <a href="/client/orders"
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-300 text-sm font-medium">
                Сбросить фильтры
              </a>
            </div>
          <?php endif; ?>
        </form>
      </div>

      <!-- Список заказов -->
      <?php
      // Подготовка данных для responsive_table
      $columns = [
        'order' => ['title' => 'Заказ', 'allow_html' => true],
        'date' => ['title' => 'Дата создания', 'allow_html' => true],
        'status' => ['title' => 'Статус', 'allow_html' => true],
        'amount' => ['title' => 'Сумма', 'allow_html' => true],
        'actions' => ['title' => 'Действия', 'allow_html' => true]
      ];

      $table_data = [];
      foreach ($orders as $order) {
        $table_data[] = [
          'order' => '<div class="flex items-center"><div class="w-12 h-12 bg-gradient-to-br from-[#118568] to-[#17B890] rounded-xl flex items-center justify-center mr-4"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg></div><div><div class="font-bold text-gray-800 text-lg">Заказ #' . e($order['id']) . '</div><div class="text-gray-500 text-sm">' . date('d.m.Y H:i', strtotime($order['created_at'])) . '</div></div></div>',
          'date' => '<div class="text-gray-600">' . date('d.m.Y H:i', strtotime($order['created_at'])) . '</div>',
          'status' => '<span class="px-3 py-1 rounded-full text-sm font-medium ' . ($status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800') . '">' . e($statuses[$order['status']] ?? 'Неизвестно') . '</span>',
          'amount' => '<div class="text-2xl font-bold text-[#118568]">' . number_format($order['total_price'], 0, '', ' ') . ' <span class="text-base">руб.</span></div>',
          'actions' => '<a href="/client/order_details.php?id=' . $order['id'] . '" class="px-4 py-2 bg-gradient-to-r from-[#118568] to-[#17B890] text-white rounded-lg hover:from-[#0f755a] hover:to-[#14a380] transition-all duration-300 font-medium text-center whitespace-nowrap inline-block w-full text-center">Подробнее</a>'
        ];
      }

      echo responsive_table($columns, $table_data, [
        'default_view' => 'cards', // Show cards on mobile by default
        'table_classes' => 'w-full bg-white',
        'card_classes' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6',
        'show_toggle' => true
      ]);
      ?>

      <!-- Статистика заказов -->
      <div class="bg-white rounded-3xl shadow-2xl p-6 mt-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Статистика заказов</h2>

        <?php
        $status_counts = [];
        foreach ($orders as $order) {
          $status_counts[$order['status']] = ($status_counts[$order['status']] ?? 0) + 1;
        }
        ?>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <div class="bg-gray-50 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-[#118568] mb-2"><?php echo count($orders); ?></div>
            <div class="text-gray-600 text-sm">Всего заказов</div>
          </div>

          <div class="bg-gray-50 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-green-600 mb-2"><?php echo $status_counts['completed'] ?? 0; ?></div>
            <div class="text-gray-600 text-sm">Завершено</div>
          </div>

          <div class="bg-gray-50 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-blue-600 mb-2"><?php echo $status_counts['processing'] ?? 0; ?></div>
            <div class="text-gray-600 text-sm">В обработке</div>
          </div>

          <div class="bg-gray-50 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-orange-600 mb-2"><?php echo $status_counts['in_work'] ?? 0; ?></div>
            <div class="text-gray-600 text-sm">В работе</div>
          </div>

          <div class="bg-gray-50 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-red-700 mb-2"><?php echo $status_counts['delayed'] ?? 0; ?></div>
            <div class="text-gray-600 text-sm">Задерживается</div>
          </div>

          <div class="bg-gray-50 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-yellow-600 mb-2"><?php echo $status_counts['pending'] ?? 0; ?></div>
            <div class="text-gray-600 text-sm">В ожидании</div>
          </div>

          <div class="bg-gray-50 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-purple-600 mb-2"><?php echo $status_counts['shipped'] ?? 0; ?></div>
            <div class="text-gray-600 text-sm">Отправлено</div>
          </div>

          <div class="bg-gray-50 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-red-600 mb-2"><?php echo $status_counts['cancelled'] ?? 0; ?></div>
            <div class="text-gray-600 text-sm">Отменено</div>
          </div>
        </div>

        <!-- Общая сумма заказов -->
        <div class="mt-6 pt-4 border-t border-[#DEE5E5]">
          <div class="flex justify-between items-center">
            <span class="text-gray-700 font-medium">Общая сумма всех заказов:</span>
            <span class="text-2xl font-bold text-[#118568]">
              <?php
              $total_spent = array_sum(array_column($orders, 'total_price'));
              echo number_format($total_spent, 0, '', ' ');
              ?> руб.
            </span>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>