<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Обработчик изменения статуса заказа
if (isset($_GET['change_status'])) {
  $id = intval($_GET['order_id']);
  $status = htmlspecialchars($_GET['change_status']);

  // Проверяем, что ID заказа существует
  $stmt = $pdo->prepare("SELECT * FROM orders1 WHERE id = ?");
  $stmt->execute([$id]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($order) {
      // Обновляем статус заказа
      $stmt = $pdo->prepare("UPDATE orders1 SET status = ? WHERE id = ?");
      $success = $stmt->execute([$status, $id]);

      if ($success) {
          $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Статус заказа успешно обновлен!'];
      } else {
          $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при обновлении статуса заказа.'];
      }
  } else {
      $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заказ не найден.'];
  }

  header("Location: /admin/orders");
  exit();
}

// Получение списка заказов
$stmt = $pdo->query("
  SELECT o.id AS order_id, o.status, o.quantity, o.total_price, cp.name AS product_name, ca.attribute_value, u.name AS user_name
  FROM orders1 o
  JOIN calculator_products cp ON o.product_id = cp.id
  JOIN calculator_attributes ca ON o.attribute_id = ca.id
  JOIN users u ON o.user_id = u.id
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Управление заказами -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление заказами</h1>

    <!-- Таблица заказов -->
    <section class="overflow-x-auto">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Текущие заказы</h2>
      <table class="min-w-full bg-white border border-gray-200">
  <thead class="bg-gray-100">
    <tr>
      <th class="py-2 px-4 text-left">ID</th>
      <th class="py-2 px-4 text-left">Пользователь</th>
      <th class="py-2 px-4 text-left">Товар</th>
      <th class="py-2 px-4 text-left">Количество</th>
      <th class="py-2 px-4 text-left">Стоимость</th>
      <th class="py-2 px-4 text-left">Статус</th>
      <th class="py-2 px-4 text-left">Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($orders as $order): ?>
    <tr class="border-t border-gray-200">
      <td class="py-2 px-4"><?php echo htmlspecialchars($order['order_id']); ?></td>
      <td class="py-2 px-4"><?php echo htmlspecialchars($order['user_name']); ?></td>
      <td class="py-2 px-4"><?php echo htmlspecialchars($order['product_name']); ?></td>
      <td class="py-2 px-4"><?php echo htmlspecialchars($order['quantity']); ?></td>
      <td class="py-2 px-4"><?php echo htmlspecialchars($order['total_price']); ?> ₽</td>
      <td class="py-2 px-4"><?php echo htmlspecialchars($order['status']); ?></td>
      <td class="py-2 px-4 space-x-2">
        <a href="?change_status=pending&order_id=<?php echo $order['order_id']; ?>" class="text-blue-600 hover:text-blue-800">В обработке</a>
        <a href="?change_status=completed&order_id=<?php echo $order['order_id']; ?>" class="text-green-600 hover:text-green-800">Выполнен</a>
        <a href="?change_status=canceled&order_id=<?php echo $order['order_id']; ?>" class="text-red-600 hover:text-red-800">Отменен</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
    </section>
  </main>

  <!-- Футер -->
  <?php include_once('../includes/footer.php'); ?>

</body>
</html>