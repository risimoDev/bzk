<?php
session_start();
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
        $new_orders_count = (int)$stmt->fetchColumn();
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
  </style>
</head>

<body class="font-sans bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] min-h-screen">

  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Главная страница админ-панели -->
  <main class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <div class="flex gap-2">
          <?php echo backButton(); ?>
          <a href="/logout"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300 text-sm">
            Выйти
          </a>
        </div>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Админ-панель</h1>
      <p class="text-xl text-gray-700">Добро пожаловать,
        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Администратор'); ?>!
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Сводка -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 stat-card">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total_orders, 0, '', ' '); ?></p>
            <p class="text-gray-600">Всего заказов</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 stat-card">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($pending_orders, 0, '', ' '); ?></p>
            <p class="text-gray-600">В обработке</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 stat-card">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($completed_orders, 0, '', ' '); ?></p>
            <p class="text-gray-600">Выполнено</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 stat-card">
        <div class="flex items-center">
          <div class="w-12 h-12 bg-[#9DC5BB] rounded-full flex items-center justify-center mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
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
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Статистика заказов</h2>
        <div class="h-64">
          <canvas id="ordersChart"></canvas>
        </div>
      </div>

      <!-- Последние заказы -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
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

    <!-- Популярные товары и дополнительная статистика -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
      <!-- Популярные товары -->
      <div class="bg-white rounded-2xl shadow-xl p-6 lg:col-span-2">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Популярные товары</h2>
        <div class="space-y-4">
          <?php foreach ($popular_products as $product): ?>
            <div class="flex items-center p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors duration-300">
              <div class="w-12 h-12 bg-[#17B890] rounded-lg flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
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

      <!-- Дополнительная статистика -->
      <div class="space-y-8">
        <div class="bg-white rounded-2xl shadow-xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Общая статистика</h2>
          <div class="space-y-4">
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span class="text-gray-600">Пользователей</span>
              <span class="font-bold text-[#118568]"><?php echo number_format($total_users, 0, '', ' '); ?></span>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span class="text-gray-600">Товаров</span>
              <span class="font-bold text-[#17B890]"><?php echo number_format($total_products, 0, '', ' '); ?></span>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span class="text-gray-600">Отменено</span>
              <span class="font-bold text-red-500"><?php echo number_format($canceled_orders, 0, '', ' '); ?></span>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span class="text-gray-600">Отправлено</span>
              <span class="font-bold text-purple-500"><?php echo number_format($shipped_orders, 0, '', ' '); ?></span>
            </div>
          </div>
        </div>

        <!-- Быстрые действия -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Быстрые действия</h2>
          <div class="grid grid-cols-2 gap-3">
            <a href="/admin/orders?status=pending"
              class="p-3 bg-yellow-50 text-yellow-700 rounded-lg text-center hover:bg-yellow-100 transition-colors duration-300 text-sm">
              Новые заказы
            </a>
            <a href="/admin/products"
              class="p-3 bg-green-50 text-green-700 rounded-lg text-center hover:bg-green-100 transition-colors duration-300 text-sm">
              + Товар
            </a>
            <a href="/admin/users"
              class="p-3 bg-blue-50 text-blue-700 rounded-lg text-center hover:bg-blue-100 transition-colors duration-300 text-sm">
              Пользователи
            </a>
            <a href="/admin/reports"
              class="p-3 bg-purple-50 text-purple-700 rounded-lg text-center hover:bg-purple-100 transition-colors duration-300 text-sm">
              Отчеты
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Быстрые ссылки -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Управление системой</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

        <a href="/admin/orders"
          class="relative block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#118568] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Заказы</h3>
          </div>
          <p class="text-gray-600 text-sm">Управление заказами и статусами</p>
            <!-- бейдж -->
        <span id="orders-badge" class="absolute top-3 right-3 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none rounded-full bg-red-500 text-white"
              style="<?php echo $new_orders_count > 0 ? '' : 'display:none;'; ?>">
          <?php echo $new_orders_count > 0 ? $new_orders_count : ''; ?>
        </span>
        </a>

        <a href="/admin/products"
          class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#17B890] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Товары</h3>
          </div>
          <p class="text-gray-600 text-sm">Добавление и редактирование товаров</p>
        </a>

        <a href="/admin/users"
          class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#5E807F] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Пользователи</h3>
          </div>
          <p class="text-gray-600 text-sm">Управление пользователями и ролями</p>
        </a>

        <a href="/admin/discounts"
          class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#9DC5BB] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Скидки</h3>
          </div>
          <p class="text-gray-600 text-sm">Настройка скидочных систем</p>
        </a>

        <a href="/admin/partners"
          class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#118568] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Партнеры</h3>
          </div>
          <p class="text-gray-600 text-sm">Управление логотипами партнеров на главном экране</p>
        </a>

        <a href="/admin/buhgalt/index"
          class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#17B890] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Бухгалтерия</h3>
          </div>
          <p class="text-gray-600 text-sm">Финансовые отчеты и учет</p>
        </a>

        <a href="/admin/tax_settings"
          class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#17B890] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Налоги</h3>
          </div>
          <p class="text-gray-600 text-sm">Настройка налога</p>
        </a>

        <a href="/admin/materials"
          class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#17B890] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Материалы</h3>
          </div>
          <p class="text-gray-600 text-sm">Добавление и редактирование расходников</p>
        </a>

        <a href="/admin/order/add_external"
          class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#118568] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Новый заказ</h3>
          </div>
          <p class="text-gray-600 text-sm">Добавление стороннего заказа вручную</p>
        </a>

        <a href="/admin/contacts/messages"
          class="relative block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#17B890] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 8h10M7 12h4m1 8a9 9 0 100-18 9 9 0 000 18z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Сообщения</h3>
          </div>
          <p class="text-gray-600 text-sm">Обратная связь с сайта</p>

          <?php if ($new_messages > 0): ?>
            <span class="absolute top-2 right-2 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-full shadow">
              <?php echo $new_messages; ?>
            </span>
          <?php endif; ?>
        </a>

        <a href="/admin/statistic"
           class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#118568] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 11V3H5a2 2 0 00-2 2v6h8zM13 13h8v6a2 2 0 01-2 2h-6v-8zM3 13h8v8H5a2 2 0 01-2-2v-6zM13 3h6a2 2 0 012 2v6h-8V3z"/>
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Статистика</h3>
          </div>
          <p class="text-gray-600 text-sm">Графики, заказы, пользователи и Метрика</p>
        </a>

        <a href="/admin/seo/home"
           class="block p-5 bg-gray-50 rounded-xl hover:bg-[#DEE5E5] transition-colors duration-300 quick-link">
          <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-[#9DC5BB] rounded-lg flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 .895-4 2s1.79 2 4 2 4 .895 4 2-1.79 2-4 2M12 4v2m0 12v2m8-10h2m-2 4h2m-18-4h2m-2 4h2"/>
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">SEO-настройки</h3>
          </div>
          <p class="text-gray-600 text-sm">Заголовки и мета-описания страниц</p>
        </a>

      </div>
    </div>

    <!-- Системная информация -->
    <div class="bg-white rounded-2xl shadow-xl p-6">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Системная информация</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-gray-50 rounded-lg">
          <div class="text-sm text-gray-600 mb-1">Роль пользователя</div>
          <div class="font-bold text-gray-800">
            <?php
            $roles = ['admin' => 'Администратор', 'manager' => 'Менеджер'];
            echo $roles[$_SESSION['role']] ?? $_SESSION['role'];
            ?>
          </div>
        </div>
        <div class="p-4 bg-gray-50 rounded-lg">
          <div class="text-sm text-gray-600 mb-1">Время на сервере</div>
          <div class="font-bold text-gray-800"><?php echo date('d.m.Y H:i:s'); ?></div>
        </div>
        <div class="p-4 bg-gray-50 rounded-lg">
          <div class="text-sm text-gray-600 mb-1">Версия системы</div>
          <div class="font-bold text-gray-800">v1.7.0</div>
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
(function() {
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
  document.addEventListener('click', function(e) {
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
        }).catch(()=>{});
      }
    } catch (err) {
      // ignore
    }
    // навигация дальше по ссылке произойдёт как обычно
  });

})();
</script>

</body>

</html>