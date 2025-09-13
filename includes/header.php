<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}
include_once __DIR__ . '/db.php';


$current_page = basename($_SERVER['SCRIPT_NAME'], ".php"); // например "contacts"
$stmt = $pdo->prepare("SELECT * FROM seo_settings WHERE page = ?");
$stmt->execute([$current_page]);
$seo = $stmt->fetch(PDO::FETCH_ASSOC);

$meta_title = $seo['title'] ?? "Типография BZK-PRINT";
$meta_description = $seo['description'] ?? "Описание по умолчанию";
$meta_keywords = $seo['keywords'] ?? "";
$og_title = $seo['og_title'] ?? $meta_title;
$og_description = $seo['og_description'] ?? $meta_description;
$og_image = $seo['og_image'] ?? "/assets/img/default-og.png";
// Получение уведомлений из сессии
$notifications = $_SESSION['notifications'] ?? [];
unset($_SESSION['notifications']); // Очищаем уведомления после отображения
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
include_once __DIR__ . '/session_check.php';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($meta_title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
  <meta name="keywords" content="<?= htmlspecialchars($meta_keywords) ?>">

  <!-- Open Graph -->
  <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars("https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">

  
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/output.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="
https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js
"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />
  <script src="
https://cdn.jsdelivr.net/npm/inputmask@5.0.9/dist/jquery.inputmask.min.js
"></script>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  
  <style>
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 1rem;
      border-radius: 0.5rem;
      color: white;
      z-index: 9999;
      transform: translateX(120%);
      transition: transform 0.3s ease-in-out;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      max-width: 300px;
    }

    .notification.show {
      transform: translateX(0);
    }

    .notification.success {
      background-color: #10B981;
      /* green-500 */
    }

    .notification.error {
      background-color: #EF4444;
      /* red-500 */
    }

    .notification.info {
      background-color: #3B82F6;
      /* blue-500 */
    }

    .notification.warning {
      background-color: #F59E0B;
      /* amber-500 */
    }

    /* Стили для мобильного меню - новый подход */
    #mobile-menu {
      display: none;
    }

    #mobile-menu.open {
      display: block;
    }

    /* Цвет стрелок */
    .swiper-button-next::after,
    .swiper-button-prev::after {
      color: #118568; /* зелёный */
      font-size: 24px; /* размер иконки */
    }

    /* При наведении */
    .swiper-button-next:hover::after,
    .swiper-button-prev:hover::after {
      color: #0f755a; /* более тёмный оттенок */
    }
    /* Базовый цвет точек */
    .swiper-pagination-bullet {
      background-color: #DEE5E5;
      opacity: 1; /* чтобы не были полупрозрачными */
    }

    /* Активная точка */
    .swiper-pagination-bullet-active {
      background-color: #118568 !important; /* зелёный */
    }
  </style>
  <!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=104056668', 'ym');

    ym(104056668, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/104056668" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
</head>

<body class="font-sans bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB]">
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
  <?php
  // Генерация breadcrumbs
  function generateBreadcrumbs($pageTitle)
  {
    $baseUrl = "/";
    $currentPage = htmlspecialchars($pageTitle);
    $homeText = "Главная";
    $separator = '<span class="mx-2 text-gray-400">/</span>';
    // Определяем текущую страницу и формируем breadcrumbs
    $breadcrumbs = '
    <nav class="flex items-center text-gray-600 text-sm" aria-label="Breadcrumb">
        <a href="' . $baseUrl . '" class="hover:text-litegreen transition duration-300">' . $homeText . '</a>
        ' . ($currentPage ? $separator . '<span class="text-gray-800 font-medium">' . $currentPage . '</span>' : '') . '
    </nav>
    ';
    return $breadcrumbs;
  }
  // Кнопка "Назад"
  function backButton()
  {
    return '
    <button onclick="history.back()" class="flex items-center text-gray-600 hover:text-litegreen transition duration-300 px-4 py-2 rounded-lg hover:bg-litegray">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Назад
    </button>
    ';
  }
  ?>
  <!-- Шапка -->
  <header class="bg-white shadow-lg sticky top-0 z-50">
    <div class="container mx-auto px-4">
      <div class="flex justify-between items-center py-4">
        <!-- Логотип -->
        <a href="/" class="flex items-center">
          <!-- SVG логотип -->
          <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="114px" height="76px" version="1.1"
            style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd"
            viewBox="0 0 4000 3000" style type="text/css">
            </style>
            <path class="bg-litegreen"
              d="M2508.72 1844.48c0,-12.72 85.93,-87.94 102.05,-103.45 7.98,-7.69 13.56,-13.7 21.41,-21.54 15.06,-15.06 27.9,-27.86 42.94,-42.94l171.01 -163.34c4.97,7.42 10.57,14.86 16.63,23.24l544.69 774.28 -898.73 0 0 -466.25zm-1696.26 469.31l-493.85 0 0 -711.63 536.79 0c197.58,0 356.75,39.61 435.63,199.33 37.14,75.22 40.88,180.89 17.74,266.31 -6.66,24.59 -19.93,53.96 -32.52,74.84 -28.33,46.94 -56.44,79.44 -103.92,107.73 -53.76,32.03 -136.55,57.91 -206.53,57.26 -21.56,-0.2 -29.15,3.2 -48.95,3.2 -36.53,0.01 -65.53,2.96 -104.39,2.96zm1420.19 -1297.49l0 1294.43 -733.1 0c7.28,-13.76 16.69,-26.58 24.39,-40.03 27.04,-47.28 41.94,-79.38 58.82,-134.42 22.15,-72.23 18.96,-99.03 26.78,-166.49 3.3,-28.46 -6.83,-110.17 -15.59,-143.21 -7.51,-28.33 -1.09,-21.02 28.31,-58.76 128.42,-164.81 278,-337.53 406.22,-501.73 10.13,-12.97 18.4,-23.27 28.63,-35.78 19.93,-24.4 38.02,-46.05 57.64,-71.18 10.13,-12.97 19.36,-23.2 28.95,-35.48 12.4,-15.87 78.98,-100.67 88.95,-107.35zm1429.4 -288.34l36.81 0 0 1490.75c-9.08,-6.66 -31.87,-41.5 -41.19,-53.91 -124.14,-165.18 -246.65,-338.31 -370.72,-503.48l-236.57 -321.69c-4.04,-5.59 -7.18,-9.12 -11.01,-13.53l114.22 -112.76c7.85,-7.27 12.06,-8.88 20.04,-16.78l348.02 -339.06c8.38,-8.29 13.27,-10.27 21.6,-18.28l76.76 -76.61c8.68,-7.26 11.57,-8.55 19.82,-16.99 5.35,-5.47 13.74,-17.66 22.22,-17.66zm-2901.74 628.81l-441.7 0 0 -625.74 466.25 0c166.6,0 339.21,11.07 411.07,150.25 37.02,71.72 39.83,104.31 39.83,187.16 0,102.78 -56.32,193 -143.1,237.26 -40.99,20.9 -92.77,37.08 -148.38,41.79 -59.53,5.03 -118.66,9.28 -183.97,9.28zm1748.41 150.31l0 -779.12 779.11 0c-3.61,13.51 -83.09,87.69 -94.32,98.92l-684.79 680.2zm-1091.99 -779.12l625.74 0c33.97,0 56.13,3.07 88.96,3.07l-45.3 49.79c-9.08,9.15 -11.95,15.33 -21.38,24.63 -4.67,4.61 -7.97,7.34 -12.17,12.37l-55.34 64.28c-5.36,5.77 -5.2,6.54 -9.55,11.93 -97.11,120.25 -192.26,234.9 -288.76,358.45l-234.45 293.15c-7.6,9.68 -14.16,19.8 -23.21,25.86l-46.85 -35.97c-32.83,-23.7 -72.76,-43.27 -110.46,-58.25 -16.58,-6.59 -55.4,-18.49 -60.47,-25.41 32.37,-7.54 131.18,-81.61 158.6,-114.39 27.49,-32.88 35.64,-42.16 58.22,-79.82 108.07,-180.24 75.88,-381.15 -23.58,-529.69zm582.8 -242.32l-1956.98 0 0 2073.54c554.27,0 1099.03,-3.07 1653.31,-3.07l2248.38 0 0 -2070.47c-38.48,0 -67.38,-3.07 -107.35,-3.07 -37.86,0 -75.75,-0.16 -113.6,-0.1 -20.05,0.03 -29.55,3.31 -52.02,3.19l-975.44 -0.02c-118.64,0 -230.79,-3.07 -346.62,-3.07 -116.99,0 -229.96,3.07 -349.68,3.07z" />
            <path class="bg-litegreen"
              d="M361.55 304.66l-217.78 0 0 -70.54 233.12 0c30.03,0 24.54,27.74 24.54,46.01 0,21.02 -19.03,24.53 -39.88,24.53zm-21.46 -119.62l-196.32 0 0 -67.49 226.99 0c33.85,0 27.6,14.72 27.6,49.08 0,17.86 -38.68,18.41 -58.27,18.41zm-291.41 171.77l340.48 0c109.86,0 101.22,-45.41 101.22,-95.09 0,-19.1 -7.93,-26.89 -19.2,-36.01 -10.27,-8.31 -26.05,-14.79 -42.14,-16.13 0.66,-0.42 1.8,-1.57 2.16,-0.91l6.86 -2.34c55.11,-15.88 49.26,-27.63 49.26,-85.71 0,-40.87 -41.45,-52.14 -82.82,-52.14l-355.82 0 0 288.33z" />
            <path class="bg-litegreen"
              d="M1297.1 2801.51l-226.98 0 0 -70.55 220.85 0c41.02,0 33.74,10.63 33.74,55.21 0,12.03 -15.17,15.34 -27.61,15.34zm63.72 147.23l-29.36 0c-5.02,-0.82 -9.62,-2.91 -17.37,-7.78l-93.84 -56.46c-78.55,-47.01 -42.57,-36.98 -150.13,-36.98l0 101.22 -85.89 0 -0.32 0c-6.81,-0.07 -8.88,-2.24 -8.88,-9.2l0 -254.59 377.29 0c34.86,0 67.48,16.83 67.48,52.14 0,106.67 6.54,110.43 -141.1,110.43 5,6.82 52.29,30.14 68.49,38.87 8.52,4.58 13.8,6.87 22.51,11.23 9.25,4.62 15.48,7.36 24.55,12.25l58.38 30.57c5.95,4.16 4.41,1.03 7.04,8.3l-1.58 0 -1.58 0 -1.58 0 -1.58 0c-30.84,-0.01 -61.7,-0.12 -92.53,0z" />
            <path class="bg-litegreen"
              d="M3441.2 74.61l0 273c0,7.06 2.13,9.2 9.2,9.2l88.95 0 0 -125.76 169.73 87.93c15.33,7.65 28.4,13.31 42.51,21.9 16.3,9.93 22.99,16.19 48.36,16.06 44.01,-0.23 88.02,-0.13 132.03,-0.13 -6.99,-9.53 -60.01,-33.07 -73.62,-39.87l-151.34 -75.65c-12.28,-6.09 -67.64,-34.17 -72.58,-40.92 14.31,-3.33 172.41,-84.67 199.38,-98.15 16.56,-8.28 54.9,-23.83 64.41,-36.81l-1.85 0 -121.52 0c-16.24,1.3 -27.85,8.96 -39.06,15.47l-196.45 104.16 0.18 -95.27c0.27,-16.85 2.9,-22.62 -9.94,-24.36l-79.19 0 -0.33 0c-6.8,0.07 -8.87,2.24 -8.87,9.2z" />
            <path class="bg-litegreen"
              d="M2524.05 2948.74l85.89 0 0 -159.5c0,-10.58 -2.27,-11.88 -3.07,-21.47l83.8 45.02c28.79,16.12 57.53,30.87 85.04,46.86 13.53,7.86 27.44,14.54 41.83,22.59l62.37 32.72c8.28,4.3 13.11,8.06 21.39,12.35 29.53,15.28 32.94,20.11 43.68,21.43l51.45 0 0.32 0c6.81,-0.07 8.88,-2.24 8.88,-9.2l0 -254.59 -88.95 0 0 113.49c0,25.97 3.06,39.03 3.06,64.41 -86.09,-45.55 -205.4,-115.73 -290.37,-157.46 -52.66,-25.87 -13.49,-20.44 -105.32,-20.44l0 263.79z" />
            <path class="bg-litegreen"
              d="M1775.61 117.55l251.53 0c-6.38,9.54 -55.12,42.05 -67.92,51.72l-138.91 100.34c-8.63,6.52 -63.18,47.96 -72.3,50.39l0 33.74 435.56 0 0 -55.21 -282.19 0c6.15,-8.4 223.28,-160.52 249.76,-179.68 35.79,-25.88 32.43,-14.96 32.43,-53.44l-407.96 0 0 52.14z" />
            <path class="bg-litegreen"
              d="M358.49 2798.44l-220.85 0 0 -67.48 217.78 0c14.55,0 22.16,3.43 33.74,6.13 9.73,41.74 5.78,61.35 -30.67,61.35zm-315.94 141.1l0 0.32c0.07,6.71 2.18,8.81 8.88,8.88l0.32 0 85.89 0 0 -104.29 276.06 0c81.81,0 73.62,-46.97 73.62,-98.16 0,-45.62 -28.45,-61.34 -73.62,-61.34l-371.15 0 0 254.59z" />
            <polygon class="bg-litegreen"
              points="3471.87,2734.02 3658.98,2734.02 3658.98,2948.74 3757.14,2948.74 3757.14,2734.02 3944.24,2734.02 3944.24,2684.95 3471.87,2684.95 " />
            <polygon class="bg-litegreen" points="1941.25,2945.67 2036.34,2945.67 2036.34,2681.88 1941.25,2681.88 " />
            </g>
            </g>
          </svg>
        </a>
        <!-- Меню для десктопа -->
        <nav class="hidden lg:flex items-center space-x-1">
          <a href="/catalog"
            class="px-4 py-2 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300 font-medium">Каталог</a>
          <a href="/payment_delivery"
            class="px-4 py-2 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300 font-medium">Доставка
            и оплата</a>
          <a href="/requirements"
            class="px-4 py-2 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300 font-medium">Требования</a>
          <a href="/contacts"
            class="px-4 py-2 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300 font-medium">Контакты</a>
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/client/dashboard"
              class="px-4 py-2 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300 font-medium">Кабинет</a>
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
              <a href="/admin"
                class="px-4 py-2 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300 font-medium">Админ</a>
            <?php endif; ?>
          <?php endif; ?>
        </nav>
        <!-- Иконки корзины, избранного и пользователя -->
        <div class="flex items-center space-x-4">
          <!-- Иконка корзины -->
          <div class="relative">
            <a href="/cart"
              class="text-gray-700 hover:text-litegreen transition duration-300 p-2 rounded-full hover:bg-litegray flex flex-col items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0h2v2H7V15z" />
                <!-- Альтернативная иконка корзины (закомментирована)
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                 -->
              </svg>
              <span class="text-xs mt-1">Корзина</span>
            </a>
            <?php if ($cart_count > 0): ?>
              <span
                class="absolute -top-1 -right-1 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">
                <?php echo min(99, $cart_count); ?>
              </span>
            <?php endif; ?>
          </div>
          <?php if (isset($_SESSION['user_id'])): ?>
            <div class="relative group hidden lg:block">
              <button
                class="flex flex-col items-center text-gray-700 hover:text-litegreen transition duration-300 p-2 rounded-full hover:bg-litegray">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="text-xs mt-1">Кабинет</span> <!-- Добавлена подпись -->
              </button>
              <div
                class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0 z-50">
                <a href="/client/dashboard" class="block px-4 py-2 text-gray-800 hover:bg-litegray">Личный кабинет</a>
                <a href="/client/orders" class="block px-4 py-2 text-gray-800 hover:bg-litegray">Мои заказы</a>
                <a href="/client/settings" class="block px-4 py-2 text-gray-800 hover:bg-litegray">Настройки</a>
                <div class="border-t border-gray-200 my-1"></div>
                <a href="/logout" class="block px-4 py-2 text-red-600 hover:bg-red-50">Выйти</a>
              </div>
            </div>
          <?php else: ?>
            <!-- Перемещаем кнопки входа и регистрации вправо -->
            <div class="hidden lg:flex items-center space-x-2">
              <a href="/login"
                class="px-4 py-2 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300 font-medium text-sm">
                Вход
              </a>
              <a href="/register"
                class="px-4 py-2 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300 font-medium text-sm">
                Регистрация
              </a>
            </div>
          <?php endif; ?>
          <!-- Гамбургер-меню для мобильных -->
          <button id="menu-toggle"
            class="lg:hidden text-gray-700 hover:text-litegreen focus:outline-none p-2 rounded-full hover:bg-litegray">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
            </svg>
          </button>
        </div>
      </div>
    </div>
    <!-- Мобильное меню - новый подход -->
    <nav id="mobile-menu" class="lg:hidden bg-white border-t border-gray-200">
      <div class="px-4 py-3">
        <div class="space-y-2 pb-3">
          <a href="/catalog"
            class="flex items-center px-4 py-3 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="#1f1f1f" viewBox="0 -960 960 960">
              <path
                d="M280-160q-50 0-85-35t-35-85H60l18-80h113q17-19 40-29.5t49-10.5q26 0 49 10.5t40 29.5h167l84-360H182l4-17q6-28 27.5-45.5T264-800h456l-37 160h117l120 160-40 200h-80q0 50-35 85t-85 35q-50 0-85-35t-35-85H400q0 50-35 85t-85 35Zm357-280h193l4-21-74-99h-95l-28 120Zm-19-273 2-7-84 360 2-7 34-146 46-200ZM20-427l20-80h220l-20 80H20Zm80-146 20-80h260l-20 80H100Zm180 333q17 0 28.5-11.5T320-280q0-17-11.5-28.5T280-320q-17 0-28.5 11.5T240-280q0 17 11.5 28.5T280-240Zm400 0q17 0 28.5-11.5T720-280q0-17-11.5-28.5T680-320q-17 0-28.5 11.5T640-280q0 17 11.5 28.5T680-240Z" />
            </svg>
            Доставка и оплата
          </a>
          <a href="/catalog"
            class="flex items-center px-4 py-3 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
            </svg>
            Каталог
          </a>
          <a href="/requirements"
            class="flex items-center px-4 py-3 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Требования
          </a>
          <a href="/contacts"
            class="flex items-center px-4 py-3 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            Контакты
          </a>
          <?php if (isset($_SESSION['user_id'])): ?>
            <div class="border-t border-gray-200 my-2"></div>
            <a href="/client/dashboard"
              class="flex items-center px-4 py-3 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Личный кабинет
            </a>
            <a href="/client/orders"
              class="flex items-center px-4 py-3 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h2a2 2 0 000-4M9 9a2 2 0 010 4h2a2 2 0 010-4m0 4a2 2 0 012 2v3m2 4H9.83a3 3 0 01-2.12-.88l-1.88-1.88A3 3 0 015 14.17V12" />
              </svg>
              Мои заказы
            </a>
            <a href="/client/settings"
              class="flex items-center px-4 py-3 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              Настройки
            </a>
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
              <a href="/admin"
                class="flex items-center px-4 py-3 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300 mt-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Админ-панель
              </a>
            <?php endif; ?>
            <div class="border-t border-gray-200 my-2"></div>
            <a href="/logout"
              class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Выйти
            </a>
          <?php else: ?>
            <div class="border-t border-gray-200 my-2"></div>
            <a href="/login"
              class="flex items-center px-4 py-3 text-gray-700 hover:text-litegreen hover:bg-litegray rounded-lg transition duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
              </svg>
              Вход
            </a>
            <a href="/register"
              class="flex items-center px-4 py-3 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
              </svg>
              Регистрация
            </a>
          <?php endif; ?>
        </div>
        <!-- Иконки в мобильном меню (без избранного) -->
        <div class="flex items-center justify-center pt-3 border-t border-gray-200 mt-2">
          <a href="/cart"
            class="flex flex-col items-center text-gray-700 hover:text-litegreen transition duration-300 p-2">
            <div class="relative">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0h2v2H7V15z" />
              </svg>
              <?php if ($cart_count > 0): ?>
                <span
                  class="absolute -top-2 -right-2 inline-flex items-center justify-center w-4 h-4 text-xs font-bold text-white bg-red-500 rounded-full">
                  <?php echo min(9, $cart_count); ?>
                </span>
              <?php endif; ?>
            </div>
            <span class="text-xs mt-1">Корзина</span>
          </a>
        </div>
      </div>
    </nav>
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
      // Мобильное меню - новый подход
      const menuToggle = document.getElementById('menu-toggle');
      const mobileMenu = document.getElementById('mobile-menu');
      if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', function () {
          mobileMenu.classList.toggle('open');
        });
        // Закрываем меню при клике вне его
        document.addEventListener('click', function (event) {
          // Проверяем, является ли цель переключателем меню
          if (event.target !== menuToggle && !menuToggle.contains(event.target)) {
            // Проверяем, является ли цель самим меню или его потомком
            if (mobileMenu.classList.contains('open') && !mobileMenu.contains(event.target)) {
              mobileMenu.classList.remove('open');
            }
          }
        });
      }
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