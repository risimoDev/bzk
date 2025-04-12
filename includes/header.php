<?php
session_start();

// Получение уведомлений из сессии
$notifications = $_SESSION['notifications'] ?? [];
unset($_SESSION['notifications']); // Очищаем уведомления после отображения
?>

<!-- Вывод уведомлений -->
<?php foreach ($notifications as $notification): ?>
<div class="notification <?php echo htmlspecialchars($notification['type']); ?> show">
  <?php echo htmlspecialchars($notification['message']); ?>
</div>
<?php endforeach; ?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle ?? 'Типография'); ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            emerald: '#118568',
            litegreen: '#17B890',
            dirtgreen: '#5E807F',
            litedirtgreen: '#9DC5BB',
            litegray: '#DEE5E5',
          }
        }
      }
    }
  </script>
</head>
<body class="font-sans bg-litegray">

  <!-- Вывод уведомлений -->
  <?php foreach ($notifications as $notification): ?>
  <div class="notification <?php echo htmlspecialchars($notification['type']); ?> show">
    <?php echo htmlspecialchars($notification['message']); ?>
  </div>
  <?php endforeach; ?>

  <script>
    // Автоматически скрываем уведомления через 5 секунд
    document.addEventListener('DOMContentLoaded', () => {
      const notifications = document.querySelectorAll('.notification');
      notifications.forEach(notification => {
        setTimeout(() => {
          notification.classList.remove('show');
          setTimeout(() => {
            notification.remove();
          }, 300); // Задержка для завершения анимации
        }, 5000);
      });
    });
  </script>

  <!-- Шапка -->
  <header class="bg-white shadow-md py-4">
  <div class="container mx-auto px-4 flex justify-between items-center">
    <!-- Логотип -->
    <a href="/" class="text-2xl font-bold text-litegreen">Типография</a>

    <!-- Меню для десктопа -->
    <nav class="hidden md:flex items-center space-x-4">
      <a href="/catalog" class="text-gray-700 hover:text-litegreen transition duration-300">Каталог</a>
      <a href="/about" class="text-gray-700 hover:text-litegreen transition duration-300">О нас</a>
      <a href="/contacts" class="text-gray-700 hover:text-litegreen transition duration-300">Контакты</a>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/client/dashboard" class="text-gray-700 hover:text-litegreen transition duration-300">Личный кабинет</a>
        <a href="/logout" class="text-gray-700 hover:text-red-600 transition duration-300">Выйти</a>
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
          <a href="/admin" class="px-4 py-2 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300">Админ-панель</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="/login" class="text-gray-700 hover:text-litegreen transition duration-300">Вход</a>
        <a href="/register" class="text-gray-700 hover:text-litegreen transition duration-300">Регистрация</a>
      <?php endif; ?>
    </nav>

    <!-- Гамбургер-меню для мобильных -->
    <button id="menu-toggle" class="md:hidden text-gray-700 hover:text-blue-600 focus:outline-none">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
      </svg>
    </button>

    <!-- Мобильное меню -->
    <nav id="mobile-menu" class="hidden md:hidden absolute top-16 right-4 bg-white p-4 rounded-lg shadow-md w-48">
      <a href="/catalog" class="block text-gray-700 hover:text-litegreen transition duration-300 mb-2">Каталог</a>
      <a href="/about" class="block text-gray-700 hover:text-litegreen transition duration-300 mb-2">О нас</a>
      <a href="/contacts" class="block text-gray-700 hover:text-litegreen transition duration-300 mb-2">Контакты</a>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/client/dashboard" class="block text-gray-700 hover:text-litegreen transition duration-300 mb-2">Личный кабинет</a>
        <a href="/logout" class="block text-gray-700 hover:text-red-600 transition duration-300 mb-2">Выйти</a>
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
          <a href="/admin" class="block px-4 py-2 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300">Админ-панель</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="/login" class="block text-gray-700 hover:text-litegreen transition duration-300 mb-2">Вход</a>
        <a href="/register" class="block text-gray-700 hover:text-litegreen transition duration-300 mb-2">Регистрация</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<script>
  // JavaScript для переключения мобильного меню
  document.getElementById('menu-toggle').addEventListener('click', function () {
    const mobileMenu = document.getElementById('mobile-menu');
    mobileMenu.classList.toggle('hidden');
  });
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
    if (!localStorage.getItem('cookiesAccepted')) {
      showNotification('Этот сайт использует куки для улучшения работы.', 'info');
      localStorage.setItem('cookiesAccepted', 'true'); // Сохраняем согласие пользователя
    }
  });
</script>