<?php
session_start();
$pageTitle = "Вход";
// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Проверка данных пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Добавляем уведомление
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Вы успешно вошли!'];

        header("Location: " . ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager' ? '/admin' : '/client/dashboard'));
        exit();
    } else {
        // Добавляем уведомление об ошибке
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный email или пароль.'];
        header("Location: /login");
        exit();
    }
}
?>

  <!-- Шапка -->
  <?php include_once __DIR__ . '/includes/header.php'; ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
  <div class="container mx-auto px-4 max-w-4xl">
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

    <div class="flex flex-col lg:flex-row gap-12 items-center">
      <!-- Левая колонка с информацией -->
      <div class="w-full lg:w-1/2 text-center lg:text-left">
        <h1 class="text-4xl font-bold text-gray-800 mb-6">Добро пожаловать обратно!</h1>
        <p class="text-xl text-gray-700 mb-8 leading-relaxed">
          Войдите в свой аккаунт, чтобы получить доступ к персональным услугам и истории заказов.
        </p>
        
        <div class="space-y-6 mb-8">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">Персональные скидки</h3>
              <p class="text-gray-600 text-sm">Специальные предложения для постоянных клиентов</p>
            </div>
          </div>
          
          <div class="flex items-center">
            <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">История заказов</h3>
              <p class="text-gray-600 text-sm">Отслеживайте все ваши заказы в одном месте</p>
            </div>
          </div>
          
          <div class="flex items-center">
            <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mr-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">Безопасность данных</h3>
              <p class="text-gray-600 text-sm">Ваши персональные данные надежно защищены</p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-2xl p-6 shadow-lg">
          <p class="text-gray-700 mb-4">Еще не зарегистрированы?</p>
          <a href="/register" class="lg:pl-2 inline-block w-full bg-[#DEE5E5] text-[#118568] py-3 rounded-lg hover:bg-[#9DC5BB] transition-all duration-300 font-medium">
            Создать аккаунт
          </a>
        </div>
      </div>

      <!-- Правая колонка с формой -->
      <div class="w-full lg:w-1/2">
        <div class="bg-white rounded-3xl shadow-2xl p-8 transform transition-all duration-300 hover:shadow-3xl">
          <div class="text-center mb-8">
            <div class="w-16 h-16 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Вход в аккаунт</h2>
            <p class="text-gray-600">Введите свои учетные данные</p>
            <div class="w-12 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
          </div>

          <?php if (isset($error_message)): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
              <?php echo $error_message; ?>
            </div>
          <?php endif; ?>

          <form action="" method="POST" class="space-y-6">
            <div>
              <label for="email" class="block text-gray-700 font-medium mb-2">Email адрес</label>
              <div class="relative">
                <input type="email" id="email" name="email" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       required>
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
            </div>

            <div>
              <label for="password" class="block text-gray-700 font-medium mb-2">Пароль</label>
              <div class="relative">
                <input type="password" id="password" name="password" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       required>
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <label class="flex items-center">
                <input type="checkbox" class="rounded border-gray-300 text-[#118568] focus:ring-[#17B890]">
                <span class="ml-2 text-gray-700">Запомнить меня</span>
              </label>
              <a href="/forgot-password" class="text-[#118568] hover:text-[#0f755a] font-medium transition-colors duration-300">
                Забыли пароль?
              </a>
            </div>

            <button type="submit" name="login" 
                    class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-lg hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
              Войти в аккаунт
            </button>
          </form>

        </div>
      </div>
    </div>
  </div>
</main>

  <!-- Футер -->
  <?php include_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>