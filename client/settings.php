<?php
session_start();
$pageTitle = "Настройки аккаунта";
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);

                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Пароль успешно изменен.'];
                header("Location: /client/settings");
                exit();
            } else {
                $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Новый пароль должен содержать минимум 6 символов.'];
            }
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Новый пароль и подтверждение не совпадают.'];
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Текущий пароль неверный.'];
    }
}
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
  <div class="container mx-auto px-4 max-w-4xl">
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
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Настройки аккаунта</h1>
      <p class="text-xl text-gray-700 max-w-2xl mx-auto">
        Управляйте безопасностью вашего аккаунта
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="p-6 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          Безопасность аккаунта
        </h2>
        <p class="text-gray-600 mt-1">Изменение пароля и другие настройки безопасности</p>
      </div>

      <form action="" method="POST" class="p-6">
        <div class="mb-8">
          <div class="flex items-center p-4 bg-blue-50 rounded-xl border border-blue-200">
            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center mr-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 class="font-bold text-gray-800">Советы по безопасности</h3>
              <p class="text-sm text-gray-600">Используйте надежный пароль с цифрами и буквами</p>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div>
            <label class="block text-gray-700 font-medium mb-2">Текущий пароль</label>
            <div class="relative">
              <input type="password" name="password" 
                     placeholder="Введите текущий пароль" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12"
                     required>
              <div class="absolute left-4 top-3.5 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-2">Новый пароль</label>
            <div class="relative">
              <input type="password" name="new_password" 
                     placeholder="Введите новый пароль" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12"
                     required minlength="6">
              <div class="absolute left-4 top-3.5 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-1">Минимум 6 символов</p>
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-2">Подтвердите новый пароль</label>
            <div class="relative">
              <input type="password" name="confirm_password" 
                     placeholder="Повторите новый пароль" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12"
                     required>
              <div class="absolute left-4 top-3.5 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
          <button type="submit" 
                  class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            Изменить пароль
          </button>
        </div>
      </form>

      <!-- Дополнительные настройки -->
      <div class="p-6 bg-gray-50 border-t border-gray-200">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Дополнительные настройки</h3>
        <div class="space-y-4">
          <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
            <div>
              <h4 class="font-medium text-gray-800">Уведомления на email</h4>
              <p class="text-sm text-gray-600">Получать уведомления о заказах</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" class="sr-only" id="notifications">
              <label for="notifications" class="block w-12 h-6 bg-[#17B890] rounded-full cursor-pointer"></label>
              <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition"></div>
            </div>
          </div>

          <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
            <div>
              <h4 class="font-medium text-gray-800">Рассылка новостей</h4>
              <p class="text-sm text-gray-600">Получать информацию о скидках</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" class="sr-only" id="newsletter" checked>
              <label for="newsletter" class="block w-12 h-6 bg-[#118568] rounded-full cursor-pointer"></label>
              <div class="dot absolute left-7 top-1 bg-white w-4 h-4 rounded-full transition"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Опасная зона -->
    <div class="mt-8 bg-white rounded-3xl shadow-2xl overflow-hidden border border-red-200">
      <div class="p-6 border-b border-red-200">
        <h2 class="text-2xl font-bold text-red-600 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          Опасная зона
        </h2>
        <p class="text-gray-600 mt-1">Осторожно! Эти действия нельзя отменить</p>
      </div>

      <div class="p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h3 class="font-bold text-gray-800">Удалить аккаунт</h3>
            <p class="text-gray-600 text-sm">Полное удаление аккаунта и всех данных</p>
          </div>
          <button class="px-6 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 font-medium">
            Удалить аккаунт
          </button>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>