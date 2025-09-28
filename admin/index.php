<?php
require_once __DIR__ . '/../includes/session.php';
$pageTitle = "Админ-панель";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Получение статистики
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$completed_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
$canceled_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();
$shipped_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'")->fetchColumn();
$delivered_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
$processing_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();

// Получение статистики по пользователям
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'completed'")->fetchColumn() ?: 0;

// Получение последних заказов
$stmt = $pdo->prepare("SELECT o.*, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Получение популярных товаров
$stmt = $pdo->prepare("SELECT p.name, COUNT(oi.product_id) as order_count FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY oi.product_id, p.name ORDER BY order_count DESC LIMIT 5");
$stmt->execute();
$popular_products = $stmt->fetchAll();

// Получение данных для графика (заказы по месяцам)
$stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM orders GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month DESC LIMIT 6");
$stmt->execute();
$monthly_orders = array_reverse($stmt->fetchAll());

// Количество непрочитанных сообщений
$new_messages = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();

$new_orders_count = 0;
if (isset($pdo)) {
  try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE is_new = 1");
    $stmt->execute();
    $new_orders_count = (int) $stmt->fetchColumn();
  } catch (Exception $e) {
    $new_orders_count = 0;
  }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  .admin-gradient {
    background: linear-gradient(135deg, #118568 0%, #17B890 100%);
  }

  .stat-card {
    transition: all 0.3s ease;
  }

  .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  }

  .quick-link {
    transition: all 0.3s ease;
  }

  .quick-link:hover {
    transform: scale(1.02);
  }

  .management-section {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  }

  .management-category {
    border-bottom: 1px solid #e5e7eb;
  }

  .management-category:last-child {
    border-bottom: none;
  }

  .category-button {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 2px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
  }

  .category-button:hover {
    background: linear-gradient(135deg, #118568 0%, #17B890 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(17, 133, 104, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
    border-color: #118568;
  }

  .category-button:hover .category-icon {
    background: rgba(255, 255, 255, 0.2) !important;
    transform: scale(1.1);
  }

  .category-button:hover h4,
  .category-button:hover p {
    color: white !important;
  }

  .category-icon {
    transition: all 0.3s ease;
  }
</style>
</head>

<body class="font-sans bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] min-h-screen">

  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Главная страница админ-панели -->
  <main class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Breadcrumbs и навигация -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 animate-fade-in">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <div class="flex gap-2">
          <?php echo backButton(); ?>
          <a href="/logout"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300 text-sm">
            <i class="fas fa-sign-out-alt mr-2"></i>Выйти
          </a>
        </div>
      </div>
    </div>

    <!-- Заголовок -->
    <div class="text-center mb-12 animate-fade-in">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Админ-панель</h1>
      <p class="text-xl text-gray-700">Добро пожаловать,
        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Администратор'); ?>!
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Поиск и фильтры -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-8 animate-fade-in">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
          <svg class="w-6 h-6 mr-2 text-[#118568]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
          Поиск и фильтры
        </h2>

        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
          <!-- Поиск по заказам -->
          <div class="relative">
            <input type="text" id="global-search" placeholder="Поиск по заказам, клиентам..."
              class="w-full sm:w-80 px-4 py-2 pl-10 pr-4 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none"
              stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>

          <!-- Фильтр по статусу -->
          <select id="status-filter"
            class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
            <option value="all">Все статусы</option>
            <option value="pending">В ожидании</option>
            <option value="processing">В обработке</option>
            <option value="shipped">Отправлен</option>
            <option value="delivered">Доставлен</option>
            <option value="completed">Выполнен</option>
            <option value="cancelled">Отменен</option>
          </select>

          <!-- Фильтр по дате -->
          <select id="date-filter"
            class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
            <option value="all">Все даты</option>
            <option value="today">Сегодня</option>
            <option value="week">На этой неделе</option>
            <option value="month">В этом месяце</option>
          </select>
        </div>
      </div>

      <!-- Результаты поиска -->
      <div id="search-results" class="mt-6 hidden">
        <div class="border-t border-gray-200 pt-6">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Результаты поиска</h3>
          <div id="search-content" class="space-y-4">
            <!-- Результаты будут загружены динамически -->
          </div>
        </div>
      </div>
    </div>

    <!-- Workflow визуализация статусов заказов -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-8 animate-fade-in">
      <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
        <svg class="w-6 h-6 mr-2 text-[#118568]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002-2h2a2 2 0 002 2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
          </path>
        </svg>
        Workflow статусов заказов
      </h2>

      <!-- Визуальная линейка workflow -->
      <div class="relative overflow-x-auto">
        <div class="flex items-center justify-between min-w-full">
          <!-- В ожидании -->
          <div class="flex-1 text-center relative">
            <div
              class="relative inline-flex items-center justify-center w-12 h-12 bg-yellow-100 text-yellow-600 rounded-full border-4 border-yellow-300 shadow-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div class="mt-2">
              <div class="font-bold text-lg text-yellow-600"><?php echo $pending_orders; ?></div>
              <div class="text-sm text-gray-600">В ожидании</div>
            </div>
          </div>

          <!-- Стрелка -->
          <div class="px-4">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </div>

          <!-- В обработке -->
          <div class="flex-1 text-center relative">
            <div
              class="relative inline-flex items-center justify-center w-12 h-12 bg-blue-100 text-blue-600 rounded-full border-4 border-blue-300 shadow-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4">
                </path>
              </svg>
            </div>
            <div class="mt-2">
              <div class="font-bold text-lg text-blue-600"><?php echo $processing_orders; ?></div>
              <div class="text-sm text-gray-600">В обработке</div>
            </div>
          </div>

          <!-- Стрелка -->
          <div class="px-4">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </div>

          <!-- Отправлен -->
          <div class="flex-1 text-center relative">
            <div
              class="relative inline-flex items-center justify-center w-12 h-12 bg-purple-100 text-purple-600 rounded-full border-4 border-purple-300 shadow-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
              </svg>
            </div>
            <div class="mt-2">
              <div class="font-bold text-lg text-purple-600"><?php echo $shipped_orders; ?></div>
              <div class="text-sm text-gray-600">Отправлен</div>
            </div>
          </div>

          <!-- Стрелка -->
          <div class="px-4">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </div>

          <!-- Доставлен -->
          <div class="flex-1 text-center relative">
            <div
              class="relative inline-flex items-center justify-center w-12 h-12 bg-indigo-100 text-indigo-600 rounded-full border-4 border-indigo-300 shadow-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13h-2v2H7v-2">
                </path>
              </svg>
            </div>
            <div class="mt-2">
              <div class="font-bold text-lg text-indigo-600"><?php echo $delivered_orders; ?></div>
              <div class="text-sm text-gray-600">Доставлен</div>
            </div>
          </div>

          <!-- Стрелка -->
          <div class="px-4">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </div>

          <!-- Выполнен -->
          <div class="flex-1 text-center relative">
            <div
              class="relative inline-flex items-center justify-center w-12 h-12 bg-green-100 text-green-600 rounded-full border-4 border-green-300 shadow-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div class="mt-2">
              <div class="font-bold text-lg text-green-600"><?php echo $completed_orders; ?></div>
              <div class="text-sm text-gray-600">Выполнен</div>
            </div>
          </div>
        </div>

        <!-- Отмененные заказы (отдельно) -->
        <div class="mt-6 pt-4 border-t border-gray-200">
          <div class="text-center">
            <div
              class="inline-flex items-center justify-center w-10 h-10 bg-red-100 text-red-600 rounded-full border-4 border-red-300 shadow-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </div>
            <div class="mt-2">
              <div class="font-bold text-lg text-red-600"><?php echo $canceled_orders; ?></div>
              <div class="text-sm text-gray-600">Отменено</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 stat-card border-l-4 border-[#118568] animate-slide-in"
        style="animation-delay: 0.1s">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-shopping-cart text-white text-xl"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total_orders, 0, '', ' '); ?></p>
            <p class="text-gray-600">Всего заказов</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 stat-card border-l-4 border-[#17B890] animate-slide-in"
        style="animation-delay: 0.2s">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-clock text-white text-xl"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($pending_orders, 0, '', ' '); ?></p>
            <p class="text-gray-600">В обработке</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 stat-card border-l-4 border-[#5E807F] animate-slide-in"
        style="animation-delay: 0.3s">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-check-circle text-white text-xl"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($completed_orders, 0, '', ' '); ?></p>
            <p class="text-gray-600">Выполнено</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 stat-card border-l-4 border-[#9DC5BB] animate-slide-in"
        style="animation-delay: 0.4s">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-[#9DC5BB] rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-ruble-sign text-white text-xl"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total_revenue, 0, '', ' '); ?> ₽</p>
            <p class="text-gray-600">Выручка</p>
          </div>
        </div>
      </div>
    </div>

    <!-- График и последние заказы -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
      <!-- График заказов -->
      <div class="bg-white rounded-2xl shadow-xl p-6 hover-lift animate-slide-in" style="animation-delay: 0.5s">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Статистика заказов</h2>
        <div class="h-64">
          <canvas id="ordersChart"></canvas>
        </div>
      </div>

      <!-- Последние заказы -->
      <div class="bg-white rounded-2xl shadow-xl p-6 hover-lift animate-slide-in" style="animation-delay: 0.6s">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-2xl font-bold text-gray-800">Последние заказы</h2>
          <a href="/admin/orders" class="text-[#118568] hover:text-[#0f755a] font-medium text-sm">Все заказы →</a>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="text-left py-2 px-2 text-gray-600 font-medium text-sm">Заказ</th>
                <th class="text-left py-2 px-2 text-gray-600 font-medium text-sm">Клиент</th>
                <th class="text-left py-2 px-2 text-gray-600 font-medium text-sm">Статус</th>
                <th class="text-left py-2 px-2 text-gray-600 font-medium text-sm">Сумма</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_orders as $order): ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                  <td class="py-2 px-2">
                    <div class="font-medium text-sm">#<?php echo $order['id']; ?></div>
                    <div class="text-xs text-gray-500"><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></div>
                  </td>
                  <td class="py-2 px-2 text-sm"><?php echo htmlspecialchars($order['user_name']); ?></td>
                  <td class="py-2 px-2">
                    <span class="px-2 py-1 text-xs rounded-full 
                  <?php
                  switch ($order['status']) {
                    case 'pending':
                      echo 'bg-yellow-100 text-yellow-800';
                      break;
                    case 'processing':
                      echo 'bg-blue-100 text-blue-800';
                      break;
                    case 'completed':
                      echo 'bg-green-100 text-green-800';
                      break;
                    case 'cancelled':
                      echo 'bg-red-100 text-red-800';
                      break;
                    case 'shipped':
                      echo 'bg-purple-100 text-purple-800';
                      break;
                    case 'delivered':
                      echo 'bg-indigo-100 text-indigo-800';
                      break;
                    default:
                      echo 'bg-gray-100 text-gray-800';
                  }
                  ?>">
                      <?php
                      $status_names = [
                        'pending' => 'В ожидании',
                        'processing' => 'Обрабатывается',
                        'completed' => 'Завершен',
                        'cancelled' => 'Отменен',
                        'shipped' => 'Отправлен',
                        'delivered' => 'Доставлен'
                      ];
                      echo $status_names[$order['status']] ?? $order['status'];
                      ?>
                    </span>
                  </td>
                  <td class="py-2 px-2 font-medium text-sm">
                    <?php echo number_format($order['total_price'], 0, '', ' '); ?> ₽
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Популярные товары с улучшенным дизайном -->
    <div class="glass-effect rounded-2xl shadow-xl p-6 lg:col-span-2 animate-slide-in" style="animation-delay: 0.7s">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Популярные товары</h2>
      <div class="space-y-4">
        <?php foreach ($popular_products as $product): ?>
          <div class="flex items-center p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors duration-300">
            <div class="w-12 h-12 bg-[#17B890] rounded-lg flex items-center justify-center mr-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h6a2 2 0 002-2V8m-9 4h4" />
              </svg>
            </div>
            <div class="flex-grow">
              <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($product['name']); ?></h3>
              <p class="text-sm text-gray-600">Заказан <?php echo $product['order_count']; ?> раз</p>
            </div>
            <div class="w-24 bg-gray-200 rounded-full h-2">
              <div class="bg-[#118568] h-2 rounded-full"
                style="width: <?php echo min(100, $product['order_count'] * 10); ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Управление системой - Организованные категории -->
    <div class="management-section p-8 mb-8 animate-slide-in" style="animation-delay: 0.7s">
      <h2 class="text-2xl font-bold text-gray-800 mb-8 text-center">Управление системой</h2>

      <!-- Основные операции -->
      <div class="management-category p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
          <i class="fas fa-star text-[#118568] mr-2"></i>
          Основные операции
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <a href="/admin/orders" class="category-button relative p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#118568] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-clipboard-list text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Заказы</h4>
            <p class="text-sm text-gray-600">Управление заказами</p>
            <?php if ($new_orders_count > 0): ?>
              <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                <?php echo $new_orders_count; ?>
              </span>
            <?php endif; ?>
          </a>

          <a href="/admin/products" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#17B890] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-boxes text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Товары</h4>
            <p class="text-sm text-gray-600">Каталог продукции</p>
          </a>

          <a href="/admin/users" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#5E807F] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-users text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Клиенты</h4>
            <p class="text-sm text-gray-600">База клиентов</p>
          </a>

          <a href="/admin/buhgalt/index" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#9DC5BB] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-calculator text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Бухгалтерия</h4>
            <p class="text-sm text-gray-600">Финансовые отчеты</p>
          </a>
        </div>
      </div>

      <!-- Заказы и производство -->
      <div class="management-category p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
          <i class="fas fa-cogs text-[#17B890] mr-2"></i>
          Заказы и производство
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <a href="/admin/order/add_external" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#118568] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-plus-circle text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Новый заказ</h4>
            <p class="text-sm text-gray-600">Добавить заказ вручную</p>
          </a>

          <a href="/admin/order/external_orders" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#17B890] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-external-link-alt text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Внешние заказы</h4>
            <p class="text-sm text-gray-600">Управление заказами</p>
          </a>

          <a href="/admin/materials" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#5E807F] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-toolbox text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Материалы</h4>
            <p class="text-sm text-gray-600">Расходники и материалы</p>
          </a>
        </div>
      </div>

      <!-- Коммуникации -->
      <div class="management-category p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
          <i class="fas fa-comments text-[#5E807F] mr-2"></i>
          Коммуникации
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <a href="/admin/messaging" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#9DC5BB] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-mail-bulk text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Рассылки</h4>
            <p class="text-sm text-gray-600">Массовые рассылки</p>
          </a>

          <a href="/admin/messaging/create" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#118568] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-pen-fancy text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Новая рассылка</h4>
            <p class="text-sm text-gray-600">Создать рассылку</p>
          </a>

          <a href="/admin/contacts/messages" class="category-button relative p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#17B890] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-envelope-open text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Сообщения</h4>
            <p class="text-sm text-gray-600">Обратная связь</p>
            <?php if ($new_messages > 0): ?>
              <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                <?php echo $new_messages; ?>
              </span>
            <?php endif; ?>
          </a>

          <a href="/admin/notifications" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#9DC5BB] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-bell text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Уведомления</h4>
            <p class="text-sm text-gray-600">Системные уведомления</p>
          </a>

          <a href="/admin/telegram_setup" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#5E807F] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fab fa-telegram-plane text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Telegram</h4>
            <p class="text-sm text-gray-600">Настройки уведомлений</p>
          </a>
        </div>
      </div>

      <!-- Дополнительные инструменты -->
      <div class="management-category p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
          <i class="fas fa-tools text-[#9DC5BB] mr-2"></i>
          Дополнительные инструменты
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <a href="/admin/tasks" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#17B890] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-tasks text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Задачи</h4>
            <p class="text-sm text-gray-600">Управление задачами</p>
          </a>

          <a href="/admin/discounts" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#9DC5BB] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-tags text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Скидки</h4>
            <p class="text-sm text-gray-600">Система скидок</p>
          </a>

          <a href="/admin/partners" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#118568] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-handshake text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Партнеры</h4>
            <p class="text-sm text-gray-600">Логотипы партнеров</p>
          </a>

          <a href="/admin/suppliers" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#5E807F] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-truck text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Поставщики</h4>
            <p class="text-sm text-gray-600">Управление поставщиками</p>
          </a>

          <a href="/admin/statistic" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#5E807F] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-chart-line text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">Статистика</h4>
            <p class="text-sm text-gray-600">Аналитика и отчеты</p>
          </a>

          <a href="/admin/seo/home" class="category-button p-5 rounded-xl block text-center">
            <div class="category-icon w-14 h-14 bg-[#17B890] rounded-xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-search-plus text-white text-xl"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-1">SEO</h4>
            <p class="text-sm text-gray-600">SEO-настройки</p>
          </a>
        </div>
      </div>
    </div>

    <!-- Системная информация с улучшенным дизайном -->
    <div class="glass-effect rounded-2xl shadow-xl p-8 animate-slide-in" style="animation-delay: 0.8s">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Системная информация</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
          class="p-6 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200 hover:shadow-md transition-all duration-300">
          <div class="flex items-center mb-3">
            <div
              class="w-10 h-10 bg-gradient-to-br from-[#118568] to-[#17B890] rounded-lg flex items-center justify-center mr-3">
              <i class="fas fa-user-shield text-white text-sm"></i>
            </div>
            <div class="text-sm text-gray-600">Роль пользователя</div>
          </div>
          <div class="font-bold text-gray-800 text-lg">
            <?php
            $roles = ['admin' => 'Администратор', 'manager' => 'Менеджер'];
            echo $roles[$_SESSION['role']] ?? $_SESSION['role'];
            ?>
          </div>
        </div>
        <div
          class="p-6 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200 hover:shadow-md transition-all duration-300">
          <div class="flex items-center mb-3">
            <div
              class="w-10 h-10 bg-gradient-to-br from-[#17B890] to-[#9DC5BB] rounded-lg flex items-center justify-center mr-3">
              <i class="fas fa-clock text-white text-sm"></i>
            </div>
            <div class="text-sm text-gray-600">Время на сервере</div>
          </div>
          <div class="font-bold text-gray-800 text-lg"><?php echo date('d.m.Y H:i:s'); ?></div>
        </div>
        <div
          class="p-6 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200 hover:shadow-md transition-all duration-300">
          <div class="flex items-center mb-3">
            <div
              class="w-10 h-10 bg-gradient-to-br from-[#5E807F] to-[#118568] rounded-lg flex items-center justify-center mr-3">
              <i class="fas fa-code-branch text-white text-sm"></i>
            </div>
            <div class="text-sm text-gray-600">Версия системы</div>
          </div>
          <div class="font-bold text-gray-800 text-lg">v1.7.0</div>
        </div>
      </div>
    </div>
  </main>

  <!-- Футер -->
  <?php include_once('../includes/footer.php'); ?>
  <script>
    // Инициализация графика
    document.addEventListener('DOMContentLoaded', function () {
      const ctx = document.getElementById('ordersChart').getContext('2d');

      // Данные для графика
      const months = <?php echo json_encode(array_column($monthly_orders, 'month')); ?>;
      const counts = <?php echo json_encode(array_column($monthly_orders, 'count')); ?>;

      // Преобразование месяцев в русские названия
      const monthNames = months.map(month => {
        const date = new Date(month + '-01');
        return date.toLocaleDateString('ru-RU', { month: 'short', year: '2-digit' });
      });

      const chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: monthNames,
          datasets: [{
            label: 'Количество заказов',
            data: counts,
            borderColor: '#118568',
            backgroundColor: 'rgba(17, 133, 104, 0.1)',
            borderWidth: 2,
            pointBackgroundColor: '#17B890',
            pointRadius: 3,
            pointHoverRadius: 5,
            fill: true,
            tension: 0.3
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
                precision: 0,
                font: {
                  size: 10
                }
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                font: {
                  size: 10
                }
              }
            }
          }
        }
      });
    });
  </script>
  <script>
    (function () {
      const badgeSelector = '#orders-badge';
      const linkSelector = '#admin-orders-link';
      const fetchUrl = '/ajax/new_orders_count.php';
      const markSeenUrl = '/ajax/mark_orders_seen.php';

      async function fetchNewCount() {
        try {
          const res = await fetch(fetchUrl, { credentials: 'same-origin' });
          if (!res.ok) return;
          const data = await res.json();
          updateBadge(data.count || 0);
        } catch (e) {
          console.error('fetchNewCount error', e);
        }
      }

      function updateBadge(count) {
        const link = document.querySelector(linkSelector);
        if (!link) return;
        let badge = document.querySelector(badgeSelector);
        if (count > 0) {
          if (!badge) {
            badge = document.createElement('span');
            badge.id = 'orders-badge';
            badge.className = 'absolute top-3 right-3 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none rounded-full bg-red-500 text-white';
            link.appendChild(badge);
          }
          badge.textContent = count;
          badge.style.display = '';
        } else {
          if (badge) badge.style.display = 'none';
        }
      }

      // Периодический опрос (каждые 15 сек)
      let pollInterval = 15000;
      setInterval(() => {
        if (!document.hidden) fetchNewCount();
      }, pollInterval);

      // Обновить при загрузке
      document.addEventListener('DOMContentLoaded', fetchNewCount);

      // При клике на ссылку пометим заказы как прочитанные.
      document.addEventListener('click', function (e) {
        const target = e.target.closest(linkSelector);
        if (!target) return;

        // Используем sendBeacon, чтобы запрос успел уйти даже при навигации
        try {
          const params = new URLSearchParams();
          params.append('all', '1');
          if (navigator.sendBeacon) {
            navigator.sendBeacon(markSeenUrl, params);
          } else {
            // fallback: fire-and-forget fetch but non-blocking
            fetch(markSeenUrl, {
              method: 'POST',
              credentials: 'same-origin',
              body: params
            }).catch(() => { });
          }
        } catch (err) {
          // ignore
        }
        // навигация дальше по ссылке произойдёт как обычно
      });

    })();

    // Поиск и фильтрация
    document.addEventListener('DOMContentLoaded', function () {
      const searchInput = document.getElementById('global-search');
      const statusFilter = document.getElementById('status-filter');
      const dateFilter = document.getElementById('date-filter');
      const searchResults = document.getElementById('search-results');
      const searchContent = document.getElementById('search-content');

      let searchTimeout;

      function performSearch() {
        const query = searchInput.value.trim();
        const status = statusFilter.value;
        const date = dateFilter.value;

        if (query.length < 2 && status === 'all' && date === 'all') {
          searchResults.classList.add('hidden');
          return;
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          fetch('/admin/ajax/search.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              query: query,
              status: status,
              date: date
            })
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                displaySearchResults(data.results);
                searchResults.classList.remove('hidden');
              }
            })
            .catch(error => {
              console.error('Ошибка поиска:', error);
            });
        }, 300);
      }

      function displaySearchResults(results) {
        if (results.length === 0) {
          searchContent.innerHTML = '<p class="text-gray-500 text-center py-4">Ничего не найдено</p>';
          return;
        }

        let html = '';
        results.forEach(result => {
          const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'processing': 'bg-blue-100 text-blue-800',
            'shipped': 'bg-purple-100 text-purple-800',
            'delivered': 'bg-indigo-100 text-indigo-800',
            'cancelled': 'bg-red-100 text-red-800',
            'completed': 'bg-green-100 text-green-800'
          };

          const statusNames = {
            'pending': 'В ожидании',
            'processing': 'В обработке',
            'shipped': 'Отправлен',
            'delivered': 'Доставлен',
            'cancelled': 'Отменен',
            'completed': 'Выполнен'
          };

          html += `
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
              <div class="flex items-center space-x-4">
                <div class="w-10 h-10 bg-[#118568] rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002-2h2a2 2 0 002 2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                  </svg>
                </div>
                <div>
                  <div class="font-medium text-gray-900">Заказ #${result.id}</div>
                  <div class="text-sm text-gray-500">${result.user_name} - ${result.created_at}</div>
                </div>
              </div>
              <div class="flex items-center space-x-4">
                <span class="px-2 py-1 text-xs rounded-full ${statusColors[result.status] || 'bg-gray-100 text-gray-800'}">
                  ${statusNames[result.status] || result.status}
                </span>
                <div class="font-bold text-[#118568]">${parseInt(result.total_price).toLocaleString()} ₽</div>
                <a href="/admin/order/details?id=${result.id}" class="px-3 py-1 bg-[#118568] text-white text-xs rounded hover:bg-[#0f755a] transition">
                  Детали
                </a>
              </div>
            </div>
          `;
        });

        searchContent.innerHTML = html;
      }

      searchInput.addEventListener('input', performSearch);
      statusFilter.addEventListener('change', performSearch);
      dateFilter.addEventListener('change', performSearch);

      // Очистка поиска при клике вне области
      document.addEventListener('click', function (e) {
        if (!e.target.closest('#search-results') && !e.target.closest('.relative')) {
          if (searchInput.value.trim() === '') {
            searchResults.classList.add('hidden');
          }
        }
      });
    });
  </script>

</body>

</html>