<?php
session_start();
$pageTitle = "Личный кабинет";

// Подключение к базе данных
include_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: /login");
  exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Обработка обновления данных профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = $_POST['full_name'];
  $phone = $_POST['phone'];
  $shipping_address = $_POST['shipping_address'];

  // Обновление данных пользователя
  $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, shipping_address = ? WHERE id = ?");
  $stmt->execute([$full_name, $phone, $shipping_address, $user_id]);

  $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Данные успешно обновлены.'];
  header("Location: /client/dashboard");
  exit();
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?> - BZK Print</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.05);
      }
    }

    .animate-slide-left {
      animation: slideInLeft 0.6s ease-out forwards;
      opacity: 0;
    }

    .animate-slide-right {
      animation: slideInRight 0.6s ease-out forwards;
      opacity: 0;
    }

    .animate-fade-up {
      animation: fadeInUp 0.6s ease-out forwards;
      opacity: 0;
    }

    .hover-lift {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .hover-lift:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .glass-effect {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .input-focus {
      transition: all 0.3s ease;
    }

    .input-focus:focus {
      transform: scale(1.01);
      box-shadow: 0 0 0 3px rgba(23, 184, 144, 0.1);
    }

    .nav-item {
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .nav-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .nav-item:hover::before {
      left: 100%;
    }

    .success-pulse {
      animation: pulse 2s infinite;
    }

    .form-floating {
      position: relative;
    }

    .form-floating input:focus+label,
    .form-floating input:not(:placeholder-shown)+label {
      transform: translateY(-1.5rem) scale(0.8);
      color: #118568;
    }

    .form-floating label {
      position: absolute;
      top: 0.75rem;
      left: 1rem;
      transition: all 0.2s ease;
      pointer-events: none;
      color: #6b7280;
    }
  </style>
</head>

<body class="font-sans bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] min-h-screen">

  <?php include_once('../includes/header.php'); ?>

  <main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
    <div class="container mx-auto px-4 max-w-6xl">
      <!-- Breadcrumbs и навигация с анимацией -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 animate-fade-up"
        style="animation-delay: 0.1s">
        <div class="w-full md:w-auto">
          <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
        </div>
        <div class="w-full md:w-auto">
          <div class="flex gap-2">
            <?php echo backButton(); ?>
            <a href="/logout"
              class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 text-sm font-medium">
              <i class="fas fa-sign-out-alt mr-2"></i>Выйти
            </a>
          </div>
        </div>
      </div>

      <!-- Заголовок с улучшенным дизайном -->
      <div class="text-center mb-12 animate-fade-up" style="animation-delay: 0.2s">
        <div
          class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-[#118568] to-[#17B890] rounded-full mb-6 shadow-lg">
          <i class="fas fa-user text-white text-2xl"></i>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Личный кабинет</h1>
        <p class="text-xl text-gray-700 max-w-2xl mx-auto mb-2">
          Управляйте вашим профилем и заказами в одном месте
        </p>
        <p class="text-sm text-gray-600 mb-6">Добро пожаловать, <?php echo htmlspecialchars($user['name']); ?>!</p>
        <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Левая колонка - Профиль с улучшенным дизайном -->
        <div class="lg:col-span-2 animate-slide-left" style="animation-delay: 0.3s">
          <div class="bg-white rounded-3xl shadow-2xl overflow-hidden hover-lift">
            <div class="p-8 border-b border-gray-200 bg-gradient-to-r from-[#118568] to-[#17B890]">
              <h2 class="text-2xl font-bold text-white flex items-center">
                <i class="fas fa-user-circle mr-3 text-3xl"></i>
                Профиль пользователя
              </h2>
              <p class="text-blue-100 mt-2">Основная информация о вашем аккаунте</p>
            </div>

            <form action="" method="POST" class="p-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-floating">
                  <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['name']); ?>"
                    placeholder="Введите ваше имя" id="full_name"
                    class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 input-focus bg-white">
                  <label for="full_name" class="text-gray-700 font-medium">ФИО</label>
                </div>

                <div class="form-floating">
                  <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled
                    id="email"
                    class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl bg-gray-100 cursor-not-allowed">
                  <label for="email" class="text-gray-500 font-medium">Email адрес</label>
                </div>

                <div class="form-floating">
                  <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                    placeholder="+7 (___) ___-____" id="phone"
                    class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 input-focus bg-white">
                  <label for="phone" class="text-gray-700 font-medium">Номер телефона</label>
                </div>

                <div class="form-floating">
                  <input type="text" value="<?php echo date('d.m.Y', strtotime($user['created_at'])); ?>" disabled
                    id="created_at"
                    class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl bg-gray-100 cursor-not-allowed">
                  <label for="created_at" class="text-gray-500 font-medium">Дата регистрации</label>
                </div>
              </div>

              <div class="mt-6">
                <div class="flex items-center justify-between mb-3">
                  <label class="block text-gray-700 font-medium">Telegram ID</label>
                  <?php if ($user['telegram_chat_id']): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium success-pulse">
                      <i class="fas fa-check-circle mr-1"></i>Подключен
                    </span>
                  <?php else: ?>
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                      <i class="fas fa-exclamation-circle mr-1"></i>Не подключен
                    </span>
                  <?php endif; ?>
                </div>
                <input type="text"
                  value="<?php echo $user['telegram_chat_id'] ? htmlspecialchars($user['telegram_chat_id']) : 'Не подключен'; ?>"
                  disabled class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl bg-gray-100 cursor-not-allowed">
                <div
                  class="mt-3 p-4 <?php echo $user['telegram_chat_id'] ? 'bg-green-50 border border-green-200' : 'bg-blue-50 border border-blue-200'; ?> rounded-xl">
                  <?php if (!$user['telegram_chat_id']): ?>
                    <p class="text-sm text-blue-700 font-medium mb-2">
                      <i class="fas fa-info-circle mr-2"></i>Инструкция по подключению:
                    </p>
                    <ol class="text-sm text-blue-600 space-y-1 ml-4">
                      <li>1. Найдите в Telegram бота нашей компании</li>
                      <li>2. Отправьте команду: <code
                          class="bg-blue-100 px-2 py-1 rounded">/connect <?php echo htmlspecialchars($user['email']); ?></code>
                      </li>
                      <li>3. Обновите страницу после получения подтверждения</li>
                    </ol>
                  <?php else: ?>
                    <p class="text-sm text-green-700">
                      <i class="fas fa-check-circle mr-2"></i>Telegram успешно подключен. Вы будете получать уведомления о
                      заказах.
                    </p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mt-6">
                <label class="block text-gray-700 font-medium mb-3">Адрес доставки</label>
                <textarea name="shipping_address"
                  placeholder="Введите полный адрес доставки с указанием города, улицы, дома и квартиры"
                  class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 resize-none input-focus bg-white"
                  rows="4"><?php echo htmlspecialchars($user['shipping_address']); ?></textarea>
              </div>

              <div class="mt-10 pt-6 border-t border-gray-200">
                <button type="submit"
                  class="w-full bg-gradient-to-r from-[#118568] to-[#17B890] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#118568] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center group">
                  <i class="fas fa-save mr-3 group-hover:animate-bounce"></i>
                  Сохранить изменения
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Правая колонка - Меню с улучшенным дизайном -->
        <div class="lg:col-span-1 animate-slide-right" style="animation-delay: 0.4s">
          <div class="bg-white rounded-3xl shadow-2xl p-8 sticky top-8 hover-lift">
            <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
              <i class="fas fa-compass mr-3 text-[#17B890] text-xl"></i>
              Навигация
            </h3>

            <div class="space-y-4">
              <a href="/client/orders"
                class="nav-item flex items-center p-5 bg-gradient-to-r from-[#DEE5E5] to-[#9DC5BB] rounded-xl hover:from-[#9DC5BB] hover:to-[#5E807F] transition-all duration-300 group shadow-md hover:shadow-lg">
                <div
                  class="w-14 h-14 bg-gradient-to-br from-[#118568] to-[#17B890] rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300 shadow-lg">
                  <i class="fas fa-clipboard-list text-white text-lg"></i>
                </div>
                <div>
                  <h4 class="font-bold text-gray-800 text-lg">История заказов</h4>
                  <p class="text-sm text-gray-600">Просмотр всех ваших заказов</p>
                </div>
                <i class="fas fa-chevron-right text-gray-400 ml-auto group-hover:text-gray-600 transition-colors"></i>
              </a>

              <a href="/client/settings"
                class="nav-item flex items-center p-5 bg-gradient-to-r from-[#9DC5BB] to-[#5E807F] rounded-xl hover:from-[#5E807F] hover:to-[#118568] transition-all duration-300 group shadow-md hover:shadow-lg">
                <div
                  class="w-14 h-14 bg-gradient-to-br from-[#17B890] to-[#9DC5BB] rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300 shadow-lg">
                  <i class="fas fa-user-cog text-white text-lg"></i>
                </div>
                <div>
                  <h4 class="font-bold text-gray-800 text-lg">Настройки</h4>
                  <p class="text-sm text-gray-700">Безопасность и пароль</p>
                </div>
                <i class="fas fa-chevron-right text-gray-400 ml-auto group-hover:text-gray-600 transition-colors"></i>
              </a>

              <a href="/logout"
                class="nav-item flex items-center p-5 bg-gradient-to-r from-red-50 to-red-100 rounded-xl hover:from-red-100 hover:to-red-200 transition-all duration-300 group shadow-md hover:shadow-lg">
                <div
                  class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300 shadow-lg">
                  <i class="fas fa-sign-out-alt text-white text-lg"></i>
                </div>
                <div>
                  <h4 class="font-bold text-gray-800 text-lg">Выйти</h4>
                  <p class="text-sm text-gray-600">Завершить сеанс</p>
                </div>
                <i class="fas fa-chevron-right text-gray-400 ml-auto group-hover:text-gray-600 transition-colors"></i>
              </a>
            </div>

            <!-- Улучшенная статистика -->
            <div class="mt-8 pt-6 border-t border-gray-200">
              <h3 class="font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-chart-line mr-2 text-[#118568]"></i>
                Ваша активность
              </h3>
              <div class="grid grid-cols-2 gap-4">
                <div
                  class="text-center p-4 bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] rounded-xl shadow-md hover:shadow-lg transition-shadow">
                  <div class="text-3xl font-bold text-[#118568] mb-1">
                    <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    echo $stmt->fetchColumn();
                    ?>
                  </div>
                  <div class="text-sm text-gray-600 font-medium">Заказов</div>
                  <div class="mt-2 h-1 bg-gray-200 rounded-full">
                    <div class="h-1 bg-[#118568] rounded-full" style="width: 75%"></div>
                  </div>
                </div>
                <div
                  class="text-center p-4 bg-gradient-to-br from-[#9DC5BB] to-[#5E807F] rounded-xl shadow-md hover:shadow-lg transition-shadow">
                  <div class="text-3xl font-bold text-[#17B890] mb-1">
                    <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND shipping_address != ''");
                    $stmt->execute([$user_id]);
                    echo $stmt->fetchColumn() > 0 ? '✓' : '✗';
                    ?>
                  </div>
                  <div class="text-sm text-gray-700 font-medium">Адрес</div>
                  <div class="mt-2 h-1 bg-gray-200 rounded-full">
                    <div class="h-1 bg-[#17B890] rounded-full"
                      style="width: <?php echo strlen($user['shipping_address']) > 0 ? '100' : '20'; ?>%"></div>
                  </div>
                </div>
              </div>

              <!-- Дополнительная информация -->
              <div class="mt-6 p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl border border-blue-200">
                <h4 class="font-semibold text-blue-800 mb-2 flex items-center">
                  <i class="fas fa-lightbulb mr-2"></i>Совет
                </h4>
                <p class="text-sm text-blue-700">
                  Заполните адрес доставки для быстрого оформления заказов
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include_once('../includes/footer.php'); ?>

  <script>
    // Добавляем интерактивность
    document.addEventListener('DOMContentLoaded', function () {
      // Автоматическое форматирование телефона
      const phoneInput = document.querySelector('input[name="phone"]');
      if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
          let value = e.target.value.replace(/\D/g, '');
          if (value.length > 0) {
            if (value.length <= 1) {
              value = '+7 (' + value;
            } else if (value.length <= 4) {
              value = '+7 (' + value.substring(1);
            } else if (value.length <= 7) {
              value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4);
            } else if (value.length <= 9) {
              value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7);
            } else {
              value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 11);
            }
          }
          e.target.value = value;
        });
      }

      // Анимация успешного сохранения
      const form = document.querySelector('form');
      if (form) {
        form.addEventListener('submit', function () {
          const button = form.querySelector('button[type="submit"]');
          const icon = button.querySelector('i');
          icon.className = 'fas fa-spinner fa-spin mr-3';
          button.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i>Сохранение...';
          button.disabled = true;
        });
      }

      // Прогресс заполнения профиля
      function updateProfileProgress() {
        const fields = ['full_name', 'phone', 'shipping_address'];
        let filled = 0;
        fields.forEach(field => {
          const input = document.querySelector(`[name="${field}"]`);
          if (input && input.value.trim()) {
            filled++;
          }
        });

        const progress = (filled / fields.length) * 100;
        console.log(`Profile completion: ${progress}%`);
      }

      updateProfileProgress();

      // Обновление прогресса при изменении полей
      document.querySelectorAll('input, textarea').forEach(input => {
        input.addEventListener('input', updateProfileProgress);
      });
    });
  </script>

</body>

</html>