<?php
session_start();
$pageTitle = "Личный кабинет";
include_once('../includes/header.php');

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

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
  <div class="container mx-auto px-4 max-w-6xl">
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
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Личный кабинет</h1>
      <p class="text-xl text-gray-700 max-w-2xl mx-auto">
        Управляйте вашим профилем и заказами в одном месте
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Левая колонка - Профиль -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
          <div class="p-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Профиль пользователя
            </h2>
            <p class="text-gray-600 mt-1">Основная информация о вашем аккаунте</p>
          </div>
          
          <form action="" method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-gray-700 font-medium mb-2">ФИО</label>
                <input type="text" name="full_name" 
                       value="<?php echo htmlspecialchars($user['name']); ?>" 
                       placeholder="Введите ваше имя" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
              </div>

              <div>
                <label class="block text-gray-700 font-medium mb-2">Email адрес</label>
                <input type="email" name="email" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" 
                       disabled 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-100 cursor-not-allowed">
              </div>

              <div>
                <label class="block text-gray-700 font-medium mb-2">Номер телефона</label>
                <input type="tel" name="phone" 
                       value="<?php echo htmlspecialchars($user['phone']); ?>" 
                       placeholder="+7 (___) ___-____" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
              </div>

              <div>
                <label class="block text-gray-700 font-medium mb-2">Дата регистрации</label>
                <input type="text" 
                       value="<?php echo date('d.m.Y', strtotime($user['created_at'])); ?>" 
                       disabled 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-100 cursor-not-allowed">
              </div>
            </div>

            <div class="mt-6">
              <label class="block text-gray-700 font-medium mb-2">Адрес доставки</label>
              <textarea name="shipping_address" 
                        placeholder="Введите полный адрес доставки" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 resize-none"
                        rows="4"><?php echo htmlspecialchars($user['shipping_address']); ?></textarea>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-200">
              <button type="submit" 
                      class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Сохранить изменения
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Правая колонка - Меню -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-3xl shadow-2xl p-6 sticky top-8">
          <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#17B890]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
            </svg>
            Навигация
          </h3>

          <div class="space-y-4">
            <a href="/client/orders" 
               class="flex items-center p-4 bg-[#DEE5E5] rounded-xl hover:bg-[#9DC5BB] transition-all duration-300 group">
              <div class="w-12 h-12 bg-[#118568] rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h2a2 2 0 000-4M9 9a2 2 0 010 4h2a2 2 0 010-4m0 4a2 2 0 012 2v3m2 4H9.83a3 3 0 01-2.12-.88l-1.88-1.88A3 3 0 015 14.17V12" />
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-gray-800">История заказов</h4>
                <p class="text-sm text-gray-600">Просмотр всех заказов</p>
              </div>
            </a>

            <a href="/client/settings" 
               class="flex items-center p-4 bg-[#9DC5BB] rounded-xl hover:bg-[#5E807F] transition-all duration-300 group">
              <div class="w-12 h-12 bg-[#17B890] rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-gray-800">Настройки аккаунта</h4>
                <p class="text-sm text-gray-700">Безопасность и пароль</p>
              </div>
            </a>

            <a href="/logout" 
               class="flex items-center p-4 bg-red-50 rounded-xl hover:bg-red-100 transition-all duration-300 group">
              <div class="w-12 h-12 bg-red-500 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-gray-800">Выйти из аккаунта</h4>
                <p class="text-sm text-gray-600">Завершить сеанс</p>
              </div>
            </a>
          </div>

          <!-- Статистика -->
          <div class="mt-8 pt-6 border-t border-gray-200">
            <h3 class="font-bold text-gray-800 mb-4">Ваша активность</h3>
            <div class="grid grid-cols-2 gap-4">
              <div class="text-center p-3 bg-[#DEE5E5] rounded-lg">
                <div class="text-2xl font-bold text-[#118568]">
                  <?php 
                  $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                  $stmt->execute([$user_id]);
                  echo $stmt->fetchColumn();
                  ?>
                </div>
                <div class="text-xs text-gray-600">Заказов</div>
              </div>
              <div class="text-center p-3 bg-[#9DC5BB] rounded-lg">
                <div class="text-2xl font-bold text-[#17B890]">
                  <?php 
                  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND shipping_address != ''");
                  $stmt->execute([$user_id]);
                  echo $stmt->fetchColumn() > 0 ? '✓' : '✗';
                  ?>
                </div>
                <div class="text-xs text-gray-700">Адрес</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>