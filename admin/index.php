<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Получение статистики
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders1")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders1 WHERE status = 'pending'")->fetchColumn();
$completed_orders = $pdo->query("SELECT COUNT(*) FROM orders1 WHERE status = 'completed'")->fetchColumn();
$canceled_orders = $pdo->query("SELECT COUNT(*) FROM orders1 WHERE status = 'canceled'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Админ-панель | Типография</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans bg-gray-100">

  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Главная страница админ-панели -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Добро пожаловать в админ-панель!</h1>

    <!-- Блоки статистики -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
      <div class="bg-white p-6 rounded-lg shadow-md text-center">
        <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($total_orders); ?></p>
        <p class="text-gray-600">Всего заказов</p>
      </div>
      <div class="bg-white p-6 rounded-lg shadow-md text-center">
        <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($pending_orders); ?></p>
        <p class="text-gray-600">Заказы в обработке</p>
      </div>
      <div class="bg-white p-6 rounded-lg shadow-md text-center">
        <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($completed_orders); ?></p>
        <p class="text-gray-600">Выполненные заказы</p>
      </div>
    </section>

    <!-- Быстрые ссылки -->
    <section>
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Быстрые ссылки</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <a href="/admin/orders" class="block bg-white p-6 rounded-lg shadow-md text-center hover:bg-gray-50 transition duration-300">
          <p class="text-xl font-bold text-gray-800">Управление заказами</p>
          <p class="text-gray-600">Просмотр и редактирование заказов</p>
        </a>
        <a href="/admin/products" class="block bg-white p-6 rounded-lg shadow-md text-center hover:bg-gray-50 transition duration-300">
          <p class="text-xl font-bold text-gray-800">Управление товарами</p>
          <p class="text-gray-600">Добавление и редактирование товаров</p>
        </a>
        <a href="/admin/users" class="block bg-white p-6 rounded-lg shadow-md text-center hover:bg-gray-50 transition duration-300">
          <p class="text-xl font-bold text-gray-800">Управление пользователями</p>
          <p class="text-gray-600">Просмотр и изменение ролей</p>
        </a>
        <a href="/admin/edit-product" class="block bg-white p-6 rounded-lg shadow-md text-center hover:bg-gray-50 transition duration-300">
          <p class="text-xl font-bold text-gray-800">Редактирование товаров</p>
          <p class="text-gray-600">Изменение товаров</p>
        </a>
      </div>
    </section>
  </main>

  <!-- Футер -->
  <?php include_once('../includes/footer.php'); ?>

</body>
</html>