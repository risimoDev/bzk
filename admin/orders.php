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
  } else {
      // Добавляем уведомление об ошибке
      $_SESSION['notifications'][] = [
          'type' => 'error',
          'message' => 'Не удалось изменить статус заказа.'
      ];
  }
}

// Получение всех заказов
$stmt = $pdo->query("
  SELECT o.id, o.user_id, o.total_price, o.status, o.created_at, u.name AS user_name, u.email 
  FROM orders o
  LEFT JOIN users u ON o.user_id = u.id
  ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <!-- Вставка breadcrumbs и кнопки "Назад" -->
<div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <!-- Breadcrumbs -->
    <div>
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
    </div>

    <!-- Кнопка "Назад" -->
    <div>
        <?php echo backButton(); ?>
    </div>
</div>
<h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление заказами</h1>

<!-- Список заказов -->
<div class="grid grid-cols-1 gap-6">
  <?php foreach ($orders as $order): ?>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h2 class="text-xl font-bold text-gray-800">Заказ #<?php echo htmlspecialchars($order['id']); ?></h2>
      <p class="text-gray-600">Пользователь: <?php echo htmlspecialchars($order['user_name']); ?> (<?php echo htmlspecialchars($order['email']); ?>)</p>
      <p class="text-gray-600">Дата создания: <?php echo htmlspecialchars($order['created_at']); ?></p>
      <p class="text-gray-600">Общая стоимость: <?php echo htmlspecialchars($order['total_price']); ?> руб.</p>
      <p class="text-gray-600">Статус: <?php echo htmlspecialchars($statuses[$order['status']] ?? 'Неизвестно'); ?></p>
      
      <!-- Кнопки изменения статуса -->
      <form action="" method="POST" class="mt-4 space-x-2">
        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
        <button type="submit" name="update_status" value="pending" class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition duration-300">В ожидании</button>
        <button type="submit" name="update_status" value="processing" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition duration-300">В обработке</button>
        <button type="submit" name="update_status" value="shipped" class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 transition duration-300">Отправлен</button>
        <button type="submit" name="update_status" value="delivered" class="px-3 py-1 bg-emerald text-white rounded hover:bg-litegreen transition duration-300">Доставлен</button>
        <button type="submit" name="update_status" value="completed" class="px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600 transition duration-300">Готов</button>
        <button type="submit" name="update_status" value="cancelled" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition duration-300">Отменен</button>
      </form>

      <!-- Ссылка на детали заказа -->
      <a href="/admin/order/details?id=<?php echo $order['id']; ?>" class="block mt-4 text-blue-600 hover:text-blue-800">Подробнее</a>
    </div>
  <?php endforeach; ?>
</div>
</main>

<?php include_once('../includes/footer.php'); ?>