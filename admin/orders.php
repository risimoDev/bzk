<?php
session_start();
$pageTitle = "Управление заказами";
include_once('../includes/header.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

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

// Обработка изменения статуса заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
  $order_id = $_POST['order_id'] ?? null;
  $status = $_POST['update_status'] ?? null;

  if ($order_id && $status) {
      // Защита от SQL-инъекций: используем подготовленные выражения
      $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
      $stmt->execute([$status, $order_id]);

      // Добавляем уведомление об успешном изменении статуса
      $_SESSION['notifications'][] = [
          'type' => 'success',
          'message' => 'Статус заказа успешно изменен.'
      ];
      
      // Перенаправляем для избежания повторной отправки формы
      header("Location: " . $_SERVER['REQUEST_URI']);
      exit();
  } else {
      // Добавляем уведомление об ошибке
      $_SESSION['notifications'][] = [
          'type' => 'error',
          'message' => 'Не удалось изменить статус заказа.'
      ];
  }
}

// Получение всех заказов с пагинацией
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Получение общего количества заказов
$total_orders_stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $total_orders_stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Получение заказов для текущей страницы
$stmt = $pdo->prepare("
  SELECT o.id, o.user_id, o.total_price, o.status, o.created_at, u.name AS user_name, u.email 
  FROM orders o
  LEFT JOIN users u ON o.user_id = u.id
  ORDER BY o.created_at DESC
  LIMIT :limit OFFSET :offset
");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

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
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление заказами</h1>
      <p class="text-xl text-gray-700">Всего заказов: <?php echo $total_orders; ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php if (isset($_SESSION['notifications'])): ?>
      <?php foreach ($_SESSION['notifications'] as $notification): ?>
        <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
          <?php echo htmlspecialchars($notification['message']); ?>
        </div>
      <?php endforeach; ?>
      <?php unset($_SESSION['notifications']); ?>
    <?php endif; ?>

    <!-- Статистика заказов -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
      <?php 
      $status_stats = [];
      foreach ($statuses as $status_key => $status_name) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
        $stmt->execute([$status_key]);
        $count = $stmt->fetchColumn();
        $status_stats[$status_key] = $count;
      }
      ?>
      <?php foreach ($statuses as $status_key => $status_name): ?>
        <div class="bg-white rounded-2xl shadow-lg p-4 text-center hover:shadow-xl transition-shadow duration-300">
          <div class="text-2xl font-bold <?php echo $status_key === 'cancelled' ? 'text-red-500' : ($status_key === 'completed' ? 'text-green-500' : 'text-[#118568]'); ?>">
            <?php echo $status_stats[$status_key]; ?>
          </div>
          <div class="text-sm text-gray-600"><?php echo $status_name; ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Список заказов -->
    <?php if (empty($orders)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Заказы не найдены</h2>
        <p class="text-gray-600 mb-8">Пока нет ни одного заказа</p>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-3xl shadow-2xl overflow-hidden mb-8">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-[#118568] text-white">
              <tr>
                <th class="py-4 px-6 text-left">Заказ</th>
                <th class="py-4 px-6 text-left">Клиент</th>
                <th class="py-4 px-6 text-left">Дата</th>
                <th class="py-4 px-6 text-left">Сумма</th>
                <th class="py-4 px-6 text-left">Статус</th>
                <th class="py-4 px-6 text-left">Действия</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#DEE5E5]">
              <?php foreach ($orders as $order): ?>
                <tr class="hover:bg-[#f8fafa] transition-colors duration-300">
                  <td class="py-4 px-6">
                    <div class="font-bold text-gray-800">#<?php echo htmlspecialchars($order['id']); ?></div>
                    <div class="text-sm text-gray-500"><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-medium"><?php echo htmlspecialchars($order['user_name'] ?? 'Гость'); ?></div>
                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                  </td>
                  <td class="py-4 px-6 text-gray-600">
                    <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                  </td>
                  <td class="py-4 px-6 font-bold text-[#118568]">
                    <?php echo number_format($order['total_price'], 0, '', ' '); ?> ₽
                  </td>
                  <td class="py-4 px-6">
                    <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                      <?php echo htmlspecialchars($statuses[$order['status']] ?? 'Неизвестно'); ?>
                    </span>
                  </td>
                  <td class="py-4 px-6">
                    <div class="flex flex-col sm:flex-row gap-2">
                      <!-- Кнопки изменения статуса -->
                      <div class="flex flex-wrap gap-1 mb-2 sm:mb-0">
                        <form action="" method="POST" class="inline">
                          <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                          <select name="update_status" onchange="this.form.submit()" class="text-xs rounded border-gray-300 focus:border-[#118568] focus:ring-[#17B890]">
                            <?php foreach ($statuses as $status_key => $status_name): ?>
                              <option value="<?php echo $status_key; ?>" <?php echo $order['status'] === $status_key ? 'selected' : ''; ?>>
                                <?php echo $status_name; ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </form>
                      </div>
                      
                      <!-- Ссылка на детали -->
                      <a href="/admin/order/details?id=<?php echo $order['id']; ?>" 
                         class="px-3 py-1 bg-[#17B890] text-white text-xs rounded hover:bg-[#14a380] transition-colors duration-300 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Детали
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Пагинация -->
      <?php if ($total_pages > 1): ?>
        <div class="flex justify-center">
          <div class="bg-white rounded-2xl shadow-lg px-6 py-4 flex items-center space-x-2">
            <?php if ($page > 1): ?>
              <a href="?page=<?php echo $page - 1; ?>" 
                 class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5] transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
              <a href="?page=<?php echo $i; ?>" 
                 class="w-10 h-10 flex items-center justify-center rounded-lg <?php echo $i == $page ? 'bg-[#118568] text-white' : 'hover:bg-[#DEE5E5]'; ?> transition-colors duration-300">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
              <a href="?page=<?php echo $page + 1; ?>" 
                 class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5] transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>