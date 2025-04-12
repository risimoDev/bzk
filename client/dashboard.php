<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Получение данных пользователя
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Получение истории заказов
$order_stmt = $pdo->prepare("SELECT * FROM orders WHERE email = ?");
$order_stmt->execute([$user['email']]);
$orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);
?>



  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Личный кабинет -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Добро пожаловать, <?php echo htmlspecialchars($user['name']); ?>!</h1>

    <!-- Контактные данные -->
    <section class="mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Контактные данные</h2>
      <div class="bg-white p-6 rounded-lg shadow-md">
        <p class="text-gray-700 mb-2"><strong>Имя:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
        <p class="text-gray-700 mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p class="text-gray-700 mb-2"><strong>Телефон:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Не указан'); ?></p>
        <a href="/client/edit-profile" class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-300">
          Редактировать профиль
        </a>
      </div>
    </section>

    <!-- История заказов -->
    <section>
      <h2 class="text-2xl font-bold text-gray-800 mb-4">История заказов</h2>
      <table class="min-w-full bg-white border border-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="py-2 px-4 text-left">ID</th>
            <th class="py-2 px-4 text-left">Тип продукции</th>
            <th class="py-2 px-4 text-left">Стоимость</th>
            <th class="py-2 px-4 text-left">Дата</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
          <tr class="border-t border-gray-200">
            <td class="py-2 px-4"><?php echo htmlspecialchars($order['id']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($order['product_type']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($order['total_price']); ?> ₽</td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($order['created_at']); ?></td>
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