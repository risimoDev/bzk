<?php
session_start();
$pageTitle = "Настройки аккаунта";

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

// Проверяем существование полей уведомлений
$notifications_enabled = false;
try {
    $check_stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'email_notifications'");
    $check_stmt->execute();
    if ($check_stmt->fetch()) {
        $notifications_enabled = true;
    }
} catch (Exception $e) {
    // Поля уведомлений не существуют
}

// Обработка изменения персональных данных
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    // Fix for birthday issue - handle empty string properly
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    $shipping_address = $_POST['shipping_address'];
    
    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, birthday = ?, shipping_address = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $birthday, $shipping_address, $user_id]);
    
    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Персональные данные успешно обновлены.'];
    header("Location: /client/settings");
    exit();
}

// Обработка изменения пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
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

// Обработка изменения настроек уведомлений
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    if ($notifications_enabled) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $newsletter = isset($_POST['newsletter']) ? 1 : 0;
        $telegram_notifications = isset($_POST['telegram_notifications']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ?, newsletter = ?, telegram_notifications = ? WHERE id = ?");
        $stmt->execute([$email_notifications, $sms_notifications, $newsletter, $telegram_notifications, $user_id]);
        
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Настройки уведомлений сохранены.'];
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Функция уведомлений временно недоступна.'];
    }
    header("Location: /client/settings");
    exit();
}

// Обработка запроса на корпоративный аккаунт
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_corporate'])) {
    $company_name = $_POST['company_name'];
    $inn = $_POST['inn'];
    $kpp = $_POST['kpp'] ?? '';
    $legal_address = $_POST['legal_address'] ?? '';
    
    // Check if user already has a pending request
    $stmt = $pdo->prepare("SELECT id FROM corporate_account_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $existing_request = $stmt->fetch();
    
    if ($existing_request) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'У вас уже есть активный запрос на корпоративный аккаунт. Дождитесь рассмотрения администратором.'];
    } else {
        // Save corporate account request to database
        $stmt = $pdo->prepare("INSERT INTO corporate_account_requests (user_id, company_name, inn, kpp, legal_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $company_name, $inn, $kpp, $legal_address]);
        
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Запрос на корпоративный аккаунт отправлен. Наши менеджеры свяжутся с вами в ближайшее время.'];
    }
    header("Location: /client/settings");
    exit();
}

// Обработка запроса на удаление аккаунта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $reason = $_POST['deletion_reason'] ?? '';
    
    // Check if user already has a pending deletion request
    $stmt = $pdo->prepare("SELECT id FROM account_deletion_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $existing_request = $stmt->fetch();
    
    if ($existing_request) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'У вас уже есть активный запрос на удаление аккаунта. Дождитесь рассмотрения администратором.'];
    } else {
        // Save account deletion request to database
        $stmt = $pdo->prepare("INSERT INTO account_deletion_requests (user_id, reason) VALUES (?, ?)");
        $stmt->execute([$user_id, $reason]);
        
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Запрос на удаление аккаунта отправлен. Наши менеджеры свяжутся с вами в ближайшее время.'];
    }
    header("Location: /client/settings");
    exit();
}

// Check if user has pending corporate account request
$stmt = $pdo->prepare("SELECT id FROM corporate_account_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$has_pending_corporate_request = $stmt->fetch();

// Check if user has pending account deletion request
$stmt = $pdo->prepare("SELECT id FROM account_deletion_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$has_pending_deletion_request = $stmt->fetch();
?>

<?php include_once('../includes/header.php');?>

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
        Управляйте безопасностью и настройками вашего аккаунта
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Вкладки для навигации -->
<div class="mb-8 border-b border-[#DEE5E5]">
  <nav class="flex flex-wrap gap-2 overflow-x-auto pb-2 -mx-2 px-2">
    <button onclick="showTab('profile')" id="profile-tab" 
            class="tab-button px-6 py-3 rounded-t-xl font-medium transition-all duration-300 whitespace-nowrap <?php echo $active_tab === 'profile' ? 'bg-[#118568] text-white shadow-lg transform scale-105' : 'bg-[#DEE5E5] text-gray-700 hover:bg-[#9DC5BB]'; ?>">
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 <?php echo $active_tab === 'profile' ? 'text-white' : 'text-[#118568]'; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        Профиль
      </div>
    </button>
    
    <button onclick="showTab('security')" id="security-tab" 
            class="tab-button px-6 py-3 rounded-t-xl font-medium transition-all duration-300 whitespace-nowrap <?php echo $active_tab === 'security' ? 'bg-[#118568] text-white shadow-lg transform scale-105' : 'bg-[#DEE5E5] text-gray-700 hover:bg-[#9DC5BB]'; ?>">
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 <?php echo $active_tab === 'security' ? 'text-white' : 'text-[#118568]'; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        Безопасность
      </div>
    </button>
    
    <?php if ($notifications_enabled): ?>
    <button onclick="showTab('notifications')" id="notifications-tab" 
            class="tab-button px-6 py-3 rounded-t-xl font-medium transition-all duration-300 whitespace-nowrap <?php echo $active_tab === 'notifications' ? 'bg-[#118568] text-white shadow-lg transform scale-105' : 'bg-[#DEE5E5] text-gray-700 hover:bg-[#9DC5BB]'; ?>">
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 <?php echo $active_tab === 'notifications' ? 'text-white' : 'text-[#118568]'; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        Уведомления
      </div>
    </button>
    <?php endif; ?>
    
    <button onclick="showTab('corporate')" id="corporate-tab" 
            class="tab-button px-6 py-3 rounded-t-xl font-medium transition-all duration-300 whitespace-nowrap <?php echo $active_tab === 'corporate' ? 'bg-[#118568] text-white shadow-lg transform scale-105' : 'bg-[#DEE5E5] text-gray-700 hover:bg-[#9DC5BB]'; ?>">
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 <?php echo $active_tab === 'corporate' ? 'text-white' : 'text-[#118568]'; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
        Корпоративный аккаунт
      </div>
    </button>
  </nav>
</div>

    <!-- Вкладка профиля -->
    <div id="profile-tab-content" class="tab-content bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="p-6 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          Персональные данные
        </h2>
        <p class="text-gray-600 mt-1">Редактирование вашей персональной информации</p>
      </div>

      <form action="" method="POST" class="p-6">
        <input type="hidden" name="update_profile" value="1">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-gray-700 font-medium mb-2">ФИО</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['name']); ?>"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   required>
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-2">Email</label>
            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-100 cursor-not-allowed" disabled>
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-2">Номер телефона</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                   placeholder="+7 (___) ___-____"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-2">Дата рождения</label>
            <input type="date" name="birthday" value="<?php echo htmlspecialchars($user['birthday'] ?? ''); ?>"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
          </div>
        </div>

        <div class="mt-6 relative">
          <label class="block text-gray-700 font-medium mb-2">Адрес доставки</label>
          <textarea name="shipping_address" id="shipping_address"
                    placeholder="Введите полный адрес доставки с указанием города, улицы, дома и квартиры"
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 resize-none"
                    rows="4"><?php echo htmlspecialchars($user['shipping_address']); ?></textarea>
          <div id="address-suggestions" class="address-suggestions hidden"></div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
          <button type="submit" 
                  class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            Сохранить изменения
          </button>
        </div>
      </form>
    </div>

    <!-- Вкладка безопасности -->
    <div id="security-tab-content" class="tab-content bg-white rounded-3xl shadow-2xl overflow-hidden hidden">
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
        <input type="hidden" name="change_password" value="1">
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
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
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
    </div>

    <?php if ($notifications_enabled): ?>
    <!-- Вкладка уведомлений -->
    <div id="notifications-tab-content" class="tab-content bg-white rounded-3xl shadow-2xl overflow-hidden hidden">
      <div class="p-6 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          Настройки уведомлений
        </h2>
        <p class="text-gray-600 mt-1">Выберите, какие уведомления вы хотите получать</p>
      </div>

      <form action="" method="POST" class="p-6">
        <input type="hidden" name="update_notifications" value="1">
        <div class="space-y-6">
          <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow border border-gray-200">
            <div>
              <h4 class="font-medium text-gray-800">Уведомления на email</h4>
              <p class="text-sm text-gray-600">Получать уведомления о заказах и статусе выполнения</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" name="email_notifications" class="sr-only peer" id="email-notifications" <?php echo isset($user['email_notifications']) && $user['email_notifications'] ? 'checked' : ''; ?>>
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#9DC5BB] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#118568]"></div>
            </label>
          </div>

          <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow border border-gray-200">
            <div>
              <h4 class="font-medium text-gray-800">SMS-уведомления</h4>
              <p class="text-sm text-gray-600">Получать SMS о важных событиях</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" name="sms_notifications" class="sr-only peer" id="sms-notifications" <?php echo isset($user['sms_notifications']) && $user['sms_notifications'] ? 'checked' : ''; ?>>
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#9DC5BB] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#118568]"></div>
            </label>
          </div>

          <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow border border-gray-200">
            <div>
              <h4 class="font-medium text-gray-800">Рассылка новостей</h4>
              <p class="text-sm text-gray-600">Получать информацию о скидках и новинках</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" name="newsletter" class="sr-only peer" id="newsletter" <?php echo isset($user['newsletter']) && $user['newsletter'] ? 'checked' : ''; ?>>
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#9DC5BB] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#118568]"></div>
            </label>
          </div>

          <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow border border-gray-200">
            <div>
              <h4 class="font-medium text-gray-800">Telegram-уведомления</h4>
              <p class="text-sm text-gray-600">Получать уведомления в Telegram</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" name="telegram_notifications" class="sr-only peer" id="telegram-notifications" <?php echo isset($user['telegram_notifications']) && $user['telegram_notifications'] ? 'checked' : ''; ?> <?php echo !$user['telegram_chat_id'] ? 'disabled' : ''; ?>>
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#9DC5BB] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#118568] <?php echo !$user['telegram_chat_id'] ? 'opacity-50' : ''; ?>"></div>
            </label>
          </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
          <button type="submit" 
                  class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            Сохранить настройки
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Вкладка корпоративного аккаунта -->
    <div id="corporate-tab-content" class="tab-content bg-white rounded-3xl shadow-2xl overflow-hidden hidden">
      <div class="p-6 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
          Корпоративный аккаунт
        </h2>
        <p class="text-gray-600 mt-1">Получите специальные условия для бизнеса</p>
      </div>

      <?php if ($has_pending_corporate_request): ?>
      <div class="p-6">
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800">Запрос на рассмотрении</h3>
              <div class="mt-2 text-sm text-yellow-700">
                <p>Ваш запрос на корпоративный аккаунт находится на рассмотрении. После обработки запроса вы сможете подать новый запрос.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <form action="" method="POST" class="p-6">
        <input type="hidden" name="make_corporate" value="1">
        <div class="mb-8">
          <div class="flex items-center p-4 bg-blue-50 rounded-xl border border-blue-200">
            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center mr-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 class="font-bold text-gray-800">Преимущества корпоративного аккаунта</h3>
              <ul class="text-sm text-gray-600 list-disc list-inside">
                <li>Специальные цены для юридических лиц</li>
                <li>Отсрочка платежа</li>
                <li>Персональный менеджер</li>
                <li>Бухгалтерские документы</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div>
            <label class="block text-gray-700 font-medium mb-2">Название компании</label>
            <input type="text" name="company_name" 
                   placeholder="Введите полное название вашей компании" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   required>
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-2">ИНН</label>
            <input type="text" name="inn" 
                   placeholder="Введите ИНН компании" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   required>
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-2">КПП (если есть)</label>
            <input type="text" name="kpp" 
                   placeholder="Введите КПП компании" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-2">Юридический адрес</label>
            <textarea name="legal_address" 
                      placeholder="Введите юридический адрес компании" 
                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 resize-none"
                      rows="3"
                      required></textarea>
          </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
          <button type="submit" 
                  class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            Отправить запрос на корпоративный аккаунт
          </button>
        </div>
      </form>
      <?php endif; ?>
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
        <?php if ($has_pending_deletion_request): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800">Запрос на удаление отправлен</h3>
              <div class="mt-2 text-sm text-yellow-700">
                <p>Ваш запрос на удаление аккаунта находится на рассмотрении. После обработки запроса администратором будет принято решение.</p>
              </div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <form method="POST">
          <input type="hidden" name="delete_account" value="1">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h3 class="font-bold text-gray-800">Удалить аккаунт</h3>
              <p class="text-gray-600 text-sm">Полное удаление аккаунта и всех данных</p>
            </div>
            <button type="button" id="delete-account-btn" class="px-6 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 font-medium">
              Удалить аккаунт
            </button>
          </div>
          
          <!-- Confirmation modal -->
          <div id="delete-confirmation-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-xl max-w-md w-full p-6">
              <h3 class="text-lg font-bold text-gray-800 mb-2">Подтверждение удаления</h3>
              <p class="text-gray-600 mb-4">Вы уверены, что хотите удалить свой аккаунт? Это действие нельзя отменить.</p>
              
              <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Причина удаления (необязательно)</label>
                <textarea name="deletion_reason" 
                          placeholder="Расскажите, почему вы хотите удалить аккаунт" 
                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-300 resize-none"
                          rows="3"></textarea>
              </div>
              
              <div class="flex gap-3">
                <button type="button" id="cancel-delete" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-300 font-medium">
                  Отмена
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 font-medium">
                  Удалить
                </button>
              </div>
            </div>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<style>
  /* Address suggestions dropdown */
  .address-suggestions {
    position: absolute;
    z-index: 1000;
    width: 100%;
    background: white;
    border: 1px solid #d1d5db;
    border-top: none;
    border-radius: 0 0 0.5rem 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  }
  
  .address-suggestion {
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background-color 0.2s;
  }
  
  .address-suggestion:hover {
    background-color: #f3f4f6;
  }
</style>
<script>
// Tab switching functionality
function showTab(tabName) {
  // Hide all tab contents
  document.querySelectorAll('.tab-content').forEach(content => {
    content.classList.add('hidden');
  });
  
  // Remove active class from all tabs
  document.querySelectorAll('.tab-button').forEach(tab => {
    tab.classList.remove('bg-[#118568]', 'text-white', 'shadow-lg', 'transform', 'scale-105');
    tab.classList.add('bg-[#DEE5E5]', 'text-gray-700');
  });
  
  // Show selected tab content
  document.getElementById(tabName + '-tab-content').classList.remove('hidden');
  
  // Add active class to selected tab
  const activeTab = document.getElementById(tabName + '-tab');
  activeTab.classList.remove('bg-[#DEE5E5]', 'text-gray-700');
  activeTab.classList.add('bg-[#118568]', 'text-white', 'shadow-lg', 'transform', 'scale-105');
}

// Toggle switch functionality
document.addEventListener('DOMContentLoaded', function() {
  // Phone number formatting using Inputmask
  if (typeof Inputmask !== 'undefined') {
    Inputmask({
      mask: "+7 (999) 999-99-99",
      showMaskOnHover: false,
      clearIncomplete: true
    }).mask("input[name='phone']");
  }
  
  // Address suggestions functionality
  const addressInput = document.getElementById('shipping_address');
  const suggestionsContainer = document.getElementById('address-suggestions');

  if (!addressInput || !suggestionsContainer) return;

  let abortController = null;

  addressInput.addEventListener('input', async function () {
    const value = this.value.trim();
    suggestionsContainer.innerHTML = '';

    // Можно оставить 3, но 2 — лучше для городов вроде "Мо"
    if (value.length < 2) {
      suggestionsContainer.classList.add('hidden');
      return;
    }

    suggestionsContainer.classList.remove('hidden');

    if (abortController) {
      abortController.abort();
    }
    abortController = new AbortController();

    try {
      const url = `https://suggest-maps.yandex.ru/v1/suggest?apikey=7bf18e0c-8c1b-4e0b-bd7f-0fc933d4d1cd&text=${encodeURIComponent(value)}&lang=ru_RU&results=5&types=country,province,area,locality,district,street,house&print_address=1`;

      const response = await fetch(url, { signal: abortController.signal });

      if (!response.ok) {
        throw new Error('Сетевая ошибка');
      }

      const data = await response.json();
      suggestionsContainer.innerHTML = '';

      if (!data.results || data.results.length === 0) {
        const noResults = document.createElement('div');
        noResults.className = 'address-suggestion text-gray-500';
        noResults.textContent = 'Нет результатов';
        suggestionsContainer.appendChild(noResults);
        return;
      }

      data.results.forEach(item => {
        // Используем unrestricted_value — это полный нормализованный адрес от Яндекса
        const fullAddress = item.unrestricted_value || 
                           (item.title?.text ? 
                             [item.title.text, item.subtitle?.text].filter(Boolean).join(', ') 
                             : 'Адрес недоступен');

        const suggestionElement = document.createElement('div');
        suggestionElement.className = 'address-suggestion';
        suggestionElement.textContent = fullAddress;

        suggestionElement.addEventListener('click', () => {
          addressInput.value = fullAddress;
          suggestionsContainer.classList.add('hidden');
        });

        suggestionsContainer.appendChild(suggestionElement);
      });
    } catch (err) {
      if (err.name !== 'AbortError') {
        console.error('Ошибка Suggest API:', err);
        suggestionsContainer.innerHTML = '';
        const errorEl = document.createElement('div');
        errorEl.className = 'address-suggestion text-red-500';
        errorEl.textContent = 'Не удалось загрузить подсказки';
        suggestionsContainer.appendChild(errorEl);
      }
    }
  });

  // Скрытие подсказок при клике вне поля
  document.addEventListener('click', (e) => {
    if (!addressInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
      suggestionsContainer.classList.add('hidden');
    }
  });
  
  // Delete account functionality
  const deleteBtn = document.getElementById('delete-account-btn');
  const modal = document.getElementById('delete-confirmation-modal');
  const cancelBtn = document.getElementById('cancel-delete');
  
  if (deleteBtn && modal && cancelBtn) {
    deleteBtn.addEventListener('click', function() {
      modal.classList.remove('hidden');
    });
    
    cancelBtn.addEventListener('click', function() {
      modal.classList.add('hidden');
    });
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        modal.classList.add('hidden');
      }
    });
  }
});
</script>

<?php include_once('../includes/footer.php'); ?>
