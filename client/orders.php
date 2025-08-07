<?php
session_start();
$pageTitle = "История заказов";
include_once('../includes/header.php');

// Подключение к базе данных
include_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT o.id, o.total_price, o.status, o.created_at 
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Массив для перевода статусов
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
  'shipped' => 'bg-indigo-100 text-indigo-800',
  'delivered' => 'bg-green-100 text-green-800',
  'cancelled' => 'bg-red-100 text-red-800',
  'completed' => 'bg-green-100 text-green-800'
];
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
  <div class="container mx-auto px-4 max-w-6xl">
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
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">История заказов</h1>
      <p class="text-xl text-gray-700 max-w-2xl mx-auto">
        <?php echo empty($orders) ? 'У вас пока нет заказов' : 'Все ваши заказы в одном месте'; ?>
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <?php if (empty($orders)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="text-8xl mb-6">📦</div>
        <h2 class="text-3xl font-bold text-gray-800 mb-4">У вас пока нет заказов</h2>
        <p class="text-gray-600 mb-8 text-lg">Совершите свой первый заказ прямо сейчас</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/catalog" class="px-8 py-4 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Перейти в каталог
          </a>
          <a href="/client/dashboard" class="px-8 py-4 bg-[#DEE5E5] text-[#118568] rounded-xl hover:bg-[#9DC5BB] transition-all duration-300 font-bold text-lg">
            Вернуться в кабинет
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-gray-200">
          <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
              <h2 class="text-2xl font-bold text-gray-800">Ваши заказы</h2>
              <p class="text-gray-600">Всего заказов: <?php echo count($orders); ?></p>
            </div>
            <div class="flex items-center bg-[#DEE5E5] rounded-lg px-4 py-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#118568] mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              <span class="font-medium text-gray-800">Фильтр активен</span>
            </div>
          </div>
        </div>

        <div class="divide-y divide-gray-100">
          <?php foreach ($orders as $order): ?>
            <div class="p-6 hover:bg-gray-50 transition-colors duration-300">
              <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div class="flex items-center">
                  <div class="w-12 h-12 bg-[#17B890] rounded-xl flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-lg font-bold text-gray-800">Заказ #<?php echo htmlspecialchars($order['id']); ?></h3>
                    <p class="text-gray-600 text-sm">
                      <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                    </p>
                  </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                  <div class="flex items-center">
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                      <?php echo htmlspecialchars($statuses[$order['status']] ?? 'Неизвестно'); ?>
                    </span>
                  </div>

                  <div class="text-right">
                    <div class="text-xl font-bold text-[#118568]">
                      <?php echo number_format($order['total_price'], 0, '', ' '); ?> <span class="text-base">руб.</span>
                    </div>
                  </div>

                  <a href="/client/order/details?id=<?php echo $order['id']; ?>" 
                     class="px-4 py-2 bg-[#DEE5E5] text-[#118568] rounded-lg hover:bg-[#9DC5BB] transition-colors duration-300 font-medium text-center whitespace-nowrap">
                    Подробнее
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Статистика заказов -->
        <div class="p-6 bg-gray-50 border-t border-gray-200">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            $status_counts = [];
            foreach ($orders as $order) {
                $status_counts[$order['status']] = ($status_counts[$order['status']] ?? 0) + 1;
            }
            ?>
            <div class="text-center p-3 bg-white rounded-lg shadow">
              <div class="text-lg font-bold text-[#118568]"><?php echo count($orders); ?></div>
              <div class="text-xs text-gray-600">Всего</div>
            </div>
            <div class="text-center p-3 bg-white rounded-lg shadow">
              <div class="text-lg font-bold text-green-600"><?php echo $status_counts['completed'] ?? 0; ?></div>
              <div class="text-xs text-gray-600">Завершено</div>
            </div>
            <div class="text-center p-3 bg-white rounded-lg shadow">
              <div class="text-lg font-bold text-blue-600"><?php echo $status_counts['processing'] ?? 0; ?></div>
              <div class="text-xs text-gray-600">В работе</div>
            </div>
            <div class="text-center p-3 bg-white rounded-lg shadow">
              <div class="text-lg font-bold text-yellow-600"><?php echo $status_counts['pending'] ?? 0; ?></div>
              <div class="text-xs text-gray-600">Ожидание</div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>