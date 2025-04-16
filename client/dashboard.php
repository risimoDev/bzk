<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Получение информации о пользователе
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Получение заказов пользователя
$stmt = $pdo->prepare("
    SELECT o.id AS order_id, o.status, o.quantity, o.total_price, cp.name AS product_name, ca.attribute_value
    FROM orders1 o
    JOIN calculator_products cp ON o.product_id = cp.id
    JOIN calculator_attributes ca ON o.attribute_id = ca.id
    WHERE o.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
  <?php include_once('../includes/header.php'); ?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-gray-800 mb-6">Личный кабинет</h1>

  <!-- Информация о пользователе -->
  <section class="bg-white p-6 rounded-lg shadow-md mb-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Профиль</h2>
    <p class="text-gray-700"><strong>Имя:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
    <p class="text-gray-700"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
  </section>

  <!-- Заказы пользователя -->
  <section>
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Мои заказы</h2>
    <table class="min-w-full bg-white border border-gray-200">
      <thead class="bg-gray-100">
        <tr>
          <th class="py-2 px-4 text-left">ID</th>
          <th class="py-2 px-4 text-left">Товар</th>
          <th class="py-2 px-4 text-left">Характеристики</th>
          <th class="py-2 px-4 text-left">Количество</th>
          <th class="py-2 px-4 text-left">Стоимость</th>
          <th class="py-2 px-4 text-left">Статус</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
        <tr class="border-t border-gray-200">
          <td class="py-2 px-4"><?php echo htmlspecialchars($order['order_id']); ?></td>
          <td class="py-2 px-4"><?php echo htmlspecialchars($order['product_name']); ?></td>
          <td class="py-2 px-4"><?php echo htmlspecialchars($order['attribute_value']); ?></td>
          <td class="py-2 px-4"><?php echo htmlspecialchars($order['quantity']); ?></td>
          <td class="py-2 px-4"><?php echo htmlspecialchars($order['total_price']); ?> ₽</td>
          <td class="py-2 px-4"><?php echo htmlspecialchars($order['status']); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>

<?php include_once('../includes/footer.php'); ?>