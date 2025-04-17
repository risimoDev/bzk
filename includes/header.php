<?php
ob_start();
// Получение уведомлений из сессии
$notifications = $_SESSION['notifications'] ?? [];
unset($_SESSION['notifications']); // Очищаем уведомления после отображения
?>

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
<?php if (!empty($notifications)): ?>
  <?php foreach ($notifications as $notification): ?>
    <?php
    $type = htmlspecialchars($notification['type'] ?? 'info');
    $message = htmlspecialchars($notification['message'] ?? '');
    if (!empty($message)) {
        echo '<div class="notification ' . $type . ' show">' . $message . '</div>';
    }
    ?>
  <?php endforeach; ?>
<?php endif; ?>
  <!-- Шапка -->
  <header class="bg-white shadow-md py-4">
  <div class="container mx-auto px-4 flex justify-between items-center">
    <!-- Логотип -->
    <a href="/" class="text-2xl font-bold text-litegreen">
    <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="30mm" height="20mm" version="1.1" style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd"
viewBox="0 0 4000 3000" style type="text/css">
  </style>
   <path class="bg-litegreen" d="M2508.72 1844.48c0,-12.72 85.93,-87.94 102.05,-103.45 7.98,-7.69 13.56,-13.7 21.41,-21.54 15.06,-15.06 27.9,-27.86 42.94,-42.94l171.01 -163.34c4.97,7.42 10.57,14.86 16.63,23.24l544.69 774.28 -898.73 0 0 -466.25zm-1696.26 469.31l-493.85 0 0 -711.63 536.79 0c197.58,0 356.75,39.61 435.63,199.33 37.14,75.22 40.88,180.89 17.74,266.31 -6.66,24.59 -19.93,53.96 -32.52,74.84 -28.33,46.94 -56.44,79.44 -103.92,107.73 -53.76,32.03 -136.55,57.91 -206.53,57.26 -21.56,-0.2 -29.15,3.2 -48.95,3.2 -36.53,0.01 -65.53,2.96 -104.39,2.96zm1420.19 -1297.49l0 1294.43 -733.1 0c7.28,-13.76 16.69,-26.58 24.39,-40.03 27.04,-47.28 41.94,-79.38 58.82,-134.42 22.15,-72.23 18.96,-99.03 26.78,-166.49 3.3,-28.46 -6.83,-110.17 -15.59,-143.21 -7.51,-28.33 -1.09,-21.02 28.31,-58.76 128.42,-164.81 278,-337.53 406.22,-501.73 10.13,-12.97 18.4,-23.27 28.63,-35.78 19.93,-24.4 38.02,-46.05 57.64,-71.18 10.13,-12.97 19.36,-23.2 28.95,-35.48 12.4,-15.87 78.98,-100.67 88.95,-107.35zm1429.4 -288.34l36.81 0 0 1490.75c-9.08,-6.66 -31.87,-41.5 -41.19,-53.91 -124.14,-165.18 -246.65,-338.31 -370.72,-503.48l-236.57 -321.69c-4.04,-5.59 -7.18,-9.12 -11.01,-13.53l114.22 -112.76c7.85,-7.27 12.06,-8.88 20.04,-16.78l348.02 -339.06c8.38,-8.29 13.27,-10.27 21.6,-18.28l76.76 -76.61c8.68,-7.26 11.57,-8.55 19.82,-16.99 5.35,-5.47 13.74,-17.66 22.22,-17.66zm-2901.74 628.81l-441.7 0 0 -625.74 466.25 0c166.6,0 339.21,11.07 411.07,150.25 37.02,71.72 39.83,104.31 39.83,187.16 0,102.78 -56.32,193 -143.1,237.26 -40.99,20.9 -92.77,37.08 -148.38,41.79 -59.53,5.03 -118.66,9.28 -183.97,9.28zm1748.41 150.31l0 -779.12 779.11 0c-3.61,13.51 -83.09,87.69 -94.32,98.92l-684.79 680.2zm-1091.99 -779.12l625.74 0c33.97,0 56.13,3.07 88.96,3.07l-45.3 49.79c-9.08,9.15 -11.95,15.33 -21.38,24.63 -4.67,4.61 -7.97,7.34 -12.17,12.37l-55.34 64.28c-5.36,5.77 -5.2,6.54 -9.55,11.93 -97.11,120.25 -192.26,234.9 -288.76,358.45l-234.45 293.15c-7.6,9.68 -14.16,19.8 -23.21,25.86l-46.85 -35.97c-32.83,-23.7 -72.76,-43.27 -110.46,-58.25 -16.58,-6.59 -55.4,-18.49 -60.47,-25.41 32.37,-7.54 131.18,-81.61 158.6,-114.39 27.49,-32.88 35.64,-42.16 58.22,-79.82 108.07,-180.24 75.88,-381.15 -23.58,-529.69zm582.8 -242.32l-1956.98 0 0 2073.54c554.27,0 1099.03,-3.07 1653.31,-3.07l2248.38 0 0 -2070.47c-38.48,0 -67.38,-3.07 -107.35,-3.07 -37.86,0 -75.75,-0.16 -113.6,-0.1 -20.05,0.03 -29.55,3.31 -52.02,3.19l-975.44 -0.02c-118.64,0 -230.79,-3.07 -346.62,-3.07 -116.99,0 -229.96,3.07 -349.68,3.07z"/>
   <path class="bg-litegreen" d="M361.55 304.66l-217.78 0 0 -70.54 233.12 0c30.03,0 24.54,27.74 24.54,46.01 0,21.02 -19.03,24.53 -39.88,24.53zm-21.46 -119.62l-196.32 0 0 -67.49 226.99 0c33.85,0 27.6,14.72 27.6,49.08 0,17.86 -38.68,18.41 -58.27,18.41zm-291.41 171.77l340.48 0c109.86,0 101.22,-45.41 101.22,-95.09 0,-19.1 -7.93,-26.89 -19.2,-36.01 -10.27,-8.31 -26.05,-14.79 -42.14,-16.13 0.66,-0.42 1.8,-1.57 2.16,-0.91l6.86 -2.34c55.11,-15.88 49.26,-27.63 49.26,-85.71 0,-40.87 -41.45,-52.14 -82.82,-52.14l-355.82 0 0 288.33z"/>
   <path class="bg-litegreen" d="M1297.1 2801.51l-226.98 0 0 -70.55 220.85 0c41.02,0 33.74,10.63 33.74,55.21 0,12.03 -15.17,15.34 -27.61,15.34zm63.72 147.23l-29.36 0c-5.02,-0.82 -9.62,-2.91 -17.37,-7.78l-93.84 -56.46c-78.55,-47.01 -42.57,-36.98 -150.13,-36.98l0 101.22 -85.89 0 -0.32 0c-6.81,-0.07 -8.88,-2.24 -8.88,-9.2l0 -254.59 377.29 0c34.86,0 67.48,16.83 67.48,52.14 0,106.67 6.54,110.43 -141.1,110.43 5,6.82 52.29,30.14 68.49,38.87 8.52,4.58 13.8,6.87 22.51,11.23 9.25,4.62 15.48,7.36 24.55,12.25l58.38 30.57c5.95,4.16 4.41,1.03 7.04,8.3l-1.58 0 -1.58 0 -1.58 0 -1.58 0c-30.84,-0.01 -61.7,-0.12 -92.53,0z"/>
   <path class="bg-litegreen" d="M3441.2 74.61l0 273c0,7.06 2.13,9.2 9.2,9.2l88.95 0 0 -125.76 169.73 87.93c15.33,7.65 28.4,13.31 42.51,21.9 16.3,9.93 22.99,16.19 48.36,16.06 44.01,-0.23 88.02,-0.13 132.03,-0.13 -6.99,-9.53 -60.01,-33.07 -73.62,-39.87l-151.34 -75.65c-12.28,-6.09 -67.64,-34.17 -72.58,-40.92 14.31,-3.33 172.41,-84.67 199.38,-98.15 16.56,-8.28 54.9,-23.83 64.41,-36.81l-1.85 0 -121.52 0c-16.24,1.3 -27.85,8.96 -39.06,15.47l-196.45 104.16 0.18 -95.27c0.27,-16.85 2.9,-22.62 -9.94,-24.36l-79.19 0 -0.33 0c-6.8,0.07 -8.87,2.24 -8.87,9.2z"/>
   <path class="bg-litegreen" d="M2524.05 2948.74l85.89 0 0 -159.5c0,-10.58 -2.27,-11.88 -3.07,-21.47l83.8 45.02c28.79,16.12 57.53,30.87 85.04,46.86 13.53,7.86 27.44,14.54 41.83,22.59l62.37 32.72c8.28,4.3 13.11,8.06 21.39,12.35 29.53,15.28 32.94,20.11 43.68,21.43l51.45 0 0.32 0c6.81,-0.07 8.88,-2.24 8.88,-9.2l0 -254.59 -88.95 0 0 113.49c0,25.97 3.06,39.03 3.06,64.41 -86.09,-45.55 -205.4,-115.73 -290.37,-157.46 -52.66,-25.87 -13.49,-20.44 -105.32,-20.44l0 263.79z"/>
   <path class="bg-litegreen" d="M1775.61 117.55l251.53 0c-6.38,9.54 -55.12,42.05 -67.92,51.72l-138.91 100.34c-8.63,6.52 -63.18,47.96 -72.3,50.39l0 33.74 435.56 0 0 -55.21 -282.19 0c6.15,-8.4 223.28,-160.52 249.76,-179.68 35.79,-25.88 32.43,-14.96 32.43,-53.44l-407.96 0 0 52.14z"/>
   <path class="bg-litegreen" d="M358.49 2798.44l-220.85 0 0 -67.48 217.78 0c14.55,0 22.16,3.43 33.74,6.13 9.73,41.74 5.78,61.35 -30.67,61.35zm-315.94 141.1l0 0.32c0.07,6.71 2.18,8.81 8.88,8.88l0.32 0 85.89 0 0 -104.29 276.06 0c81.81,0 73.62,-46.97 73.62,-98.16 0,-45.62 -28.45,-61.34 -73.62,-61.34l-371.15 0 0 254.59z"/>
   <polygon class="bg-litegreen" points="3471.87,2734.02 3658.98,2734.02 3658.98,2948.74 3757.14,2948.74 3757.14,2734.02 3944.24,2734.02 3944.24,2684.95 3471.87,2684.95 "/>
   <polygon class="bg-litegreen" points="1941.25,2945.67 2036.34,2945.67 2036.34,2681.88 1941.25,2681.88 "/>
  </g>
 </g>
</svg>
    </a>

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

  function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type} show`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => notification.remove(), 300);
    }, 5000);
  }
</script>