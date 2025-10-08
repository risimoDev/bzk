<?php
session_start();
$pageTitle = "Редактирование рассылки";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/security.php';

$message_id = intval($_GET['id'] ?? 0);

if (!$message_id) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный ID рассылки.'];
    header("Location: /admin/messaging/");
    exit();
}

// Получение информации о рассылке
$stmt = $pdo->prepare("
    SELECT mm.* 
    FROM mass_messages mm 
    WHERE mm.id = ?
");
$stmt->execute([$message_id]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Рассылка не найдена.'];
    header("Location: /admin/messaging/");
    exit();
}

// Проверка, можно ли редактировать рассылку
if (($message['status'] ?? '') !== 'draft') {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Можно редактировать только черновики рассылок.'];
    header("Location: /admin/messaging/details.php?id=$message_id");
    exit();
}

$notifications = [];

// Обработка сохранения изменений
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $message_type = $_POST['message_type'] ?? 'both';
    $target_audience = $_POST['target_audience'] ?? 'all';
    $specific_user_ids = $_POST['specific_users'] ?? [];
    $schedule_type = $_POST['schedule_type'] ?? 'now';
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $scheduled_time = $_POST['scheduled_time'] ?? '';

    if (!empty($title) && !empty($content)) {
        try {
            $pdo->beginTransaction();

            // Подготовка времени планирования
            $scheduled_at = null;
            if ($schedule_type === 'scheduled' && !empty($scheduled_date) && !empty($scheduled_time)) {
                $scheduled_at = $scheduled_date . ' ' . $scheduled_time . ':00';
            }

            // Обновление основной информации о рассылке
            $stmt = $pdo->prepare("
                UPDATE mass_messages 
                SET title = ?, content = ?, message_type = ?, target_audience = ?, scheduled_at = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $content, $message_type, $target_audience, $scheduled_at, $message_id]);

            // Удаление старых получателей
            $stmt = $pdo->prepare("DELETE FROM mass_message_recipients WHERE mass_message_id = ?");
            $stmt->execute([$message_id]);

            // Получение списка новых получателей на основе аудитории
            $recipients = [];
            switch ($target_audience) {
                case 'all':
                    $stmt = $pdo->query("SELECT id, name, email, telegram_chat_id FROM users WHERE is_blocked = 0");
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'customers':
                    $stmt = $pdo->query("SELECT id, name, email, telegram_chat_id FROM users WHERE role = 'user' AND is_blocked = 0");
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'admins':
                    $stmt = $pdo->query("SELECT id, name, email, telegram_chat_id FROM users WHERE role = 'admin' AND is_blocked = 0");
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'managers':
                    $stmt = $pdo->query("SELECT id, name, email, telegram_chat_id FROM users WHERE role = 'manager' AND is_blocked = 0");
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'specific':
                    if (!empty($specific_user_ids)) {
                        $placeholders = str_repeat('?,', count($specific_user_ids) - 1) . '?';
                        $stmt = $pdo->prepare("SELECT id, name, email, telegram_chat_id FROM users WHERE id IN ($placeholders) AND is_blocked = 0");
                        $stmt->execute($specific_user_ids);
                        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    break;
            }
            
            // Добавление новых получателей
            foreach ($recipients as $recipient) {
                $stmt = $pdo->prepare("
                    INSERT INTO mass_message_recipients (mass_message_id, user_id, email_status, telegram_status)
                    VALUES (?, ?, ?, ?)
                ");
                
                $email_status = ($message_type === 'telegram') ? 'skipped' : 'pending';
                $telegram_status = ($message_type === 'email') ? 'skipped' : 'pending';
                
                // Проверяем наличие email и telegram
                if (empty($recipient['email']) && $message_type !== 'telegram') {
                    $email_status = 'skipped';
                }
                if (empty($recipient['telegram_chat_id']) && $message_type !== 'email') {
                    $telegram_status = 'skipped';
                }
                
                $stmt->execute([$message_id, $recipient['id'], $email_status, $telegram_status]);
            }

            // Обновление счетчика получателей
            $stmt = $pdo->prepare("UPDATE mass_messages SET total_recipients = ? WHERE id = ?");
            $stmt->execute([count($recipients), $message_id]);
            
            $pdo->commit();
            
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Рассылка успешно обновлена!'];
            header("Location: /admin/messaging/details.php?id=$message_id");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $notifications[] = ['type' => 'error', 'message' => 'Ошибка при обновлении рассылки: ' . $e->getMessage()];
        }
    } else {
        $notifications[] = ['type' => 'error', 'message' => 'Заполните все обязательные поля.'];
    }
}

// Получение списка пользователей для выбора
$users_stmt = $pdo->query("
    SELECT id, name, email, role, telegram_chat_id 
    FROM users 
    WHERE is_blocked = 0 
    ORDER BY role DESC, name ASC
");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение текущих получателей для случая specific
$current_recipients = [];
if (($message['target_audience'] ?? '') === 'specific') {
    $stmt = $pdo->prepare("SELECT user_id FROM mass_message_recipients WHERE mass_message_id = ?");
    $stmt->execute([$message_id]);
    $current_recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
  <div class="container mx-auto px-6 max-w-4xl">

    <!-- Заголовок -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
      <div>
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="flex gap-3">
        <a href="/admin/messaging/details.php?id=<?php echo $message_id; ?>" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition text-sm font-medium">
          ← К деталям
        </a>
      </div>
    </div>

    <!-- Заголовок страницы -->
    <div class="text-center mb-8">
      <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Редактирование рассылки</h1>
      <p class="text-lg text-gray-700">Внесите изменения в черновик рассылки</p>
      <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $n): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($n['message']); ?>
      </div>
    <?php endforeach; ?>

    <form method="POST" class="space-y-8">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="update">

      <!-- Основная информация -->
      <div class="bg-white rounded-3xl shadow-xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Основная информация</h2>
        
        <div class="space-y-6">
          <div>
            <label for="title" class="block text-gray-700 font-medium mb-2">
              Заголовок рассылки <span class="text-red-500">*</span>
            </label>
            <input type="text" id="title" name="title" required
                   value="<?php echo htmlspecialchars($message['title'] ?? ''); ?>"
                   placeholder="Введите заголовок рассылки"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition">
          </div>

          <div>
            <label for="content" class="block text-gray-700 font-medium mb-2">
              Содержание сообщения <span class="text-red-500">*</span>
            </label>
            <textarea id="content" name="content" required rows="8"
                      placeholder="Введите текст сообщения для рассылки..."
                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition resize-none"><?php echo htmlspecialchars($message['content'] ?? ''); ?></textarea>
            <p class="text-sm text-gray-500 mt-2">
              Это сообщение будет отправлено всем выбранным получателям
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="message_type" class="block text-gray-700 font-medium mb-2">
                Тип рассылки <span class="text-red-500">*</span>
              </label>
              <select id="message_type" name="message_type" required
                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition">
                <option value="both" <?php echo ($message['message_type'] ?? '') === 'both' ? 'selected' : ''; ?>>📧📱 Email + Telegram</option>
                <option value="email" <?php echo ($message['message_type'] ?? '') === 'email' ? 'selected' : ''; ?>>📧 Только Email</option>
                <option value="telegram" <?php echo ($message['message_type'] ?? '') === 'telegram' ? 'selected' : ''; ?>>📱 Только Telegram</option>
              </select>
            </div>

            <div>
              <label for="target_audience" class="block text-gray-700 font-medium mb-2">
                Целевая аудитория <span class="text-red-500">*</span>
              </label>
              <select id="target_audience" name="target_audience" required
                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition"
                      onchange="toggleSpecificUsers()">
                <option value="all" <?php echo ($message['target_audience'] ?? '') === 'all' ? 'selected' : ''; ?>>Все пользователи</option>
                <option value="customers" <?php echo ($message['target_audience'] ?? '') === 'customers' ? 'selected' : ''; ?>>Клиенты</option>
                <option value="admins" <?php echo ($message['target_audience'] ?? '') === 'admins' ? 'selected' : ''; ?>>Администраторы</option>
                <option value="managers" <?php echo ($message['target_audience'] ?? '') === 'managers' ? 'selected' : ''; ?>>Менеджеры</option>
                <option value="specific" <?php echo ($message['target_audience'] ?? '') === 'specific' ? 'selected' : ''; ?>>Выборочно</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Выбор конкретных пользователей -->
      <div id="specific-users-section" class="bg-white rounded-3xl shadow-xl p-8" 
           style="display: <?php echo ($message['target_audience'] ?? '') === 'specific' ? 'block' : 'none'; ?>;">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Выбор получателей</h2>
        
        <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($users as $user): ?>
              <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                <input type="checkbox" name="specific_users[]" value="<?php echo $user['id']; ?>"
                       <?php echo in_array($user['id'], $current_recipients) ? 'checked' : ''; ?>
                       class="w-4 h-4 text-[#118568] border-gray-300 rounded focus:ring-[#9DC5BB] mr-3">
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['name']); ?></p>
                    <span class="px-2 py-1 text-xs rounded-full 
                      <?php 
                        switch($user['role']) {
                          case 'admin': echo 'bg-red-100 text-red-800'; break;
                          case 'manager': echo 'bg-blue-100 text-blue-800'; break;
                          default: echo 'bg-gray-100 text-gray-800'; break;
                        }
                      ?>">
                      <?php 
                        switch($user['role']) {
                          case 'admin': echo 'Админ'; break;
                          case 'manager': echo 'Менеджер'; break;
                          default: echo 'Клиент'; break;
                        }
                      ?>
                    </span>
                  </div>
                  <div class="text-sm text-gray-600">
                    <?php if ($user['email']): ?>
                      📧 <?php echo htmlspecialchars($user['email']); ?>
                    <?php endif; ?>
                    <?php if ($user['telegram_chat_id']): ?>
                      📱 Telegram
                    <?php endif; ?>
                  </div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Планирование отправки -->
      <div class="bg-white rounded-3xl shadow-xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Время отправки</h2>
        
        <div class="space-y-4">
          <label class="flex items-center space-x-3">
            <input type="radio" name="schedule_type" value="now" 
                   <?php echo empty($message['scheduled_at']) ? 'checked' : ''; ?>
                   class="w-4 h-4 text-[#118568] border-gray-300 focus:ring-[#9DC5BB]"
                   onchange="toggleScheduleFields()">
            <span class="font-medium">Сохранить как черновик</span>
          </label>
          
          <label class="flex items-center space-x-3">
            <input type="radio" name="schedule_type" value="scheduled" id="schedule-radio"
                   <?php echo !empty($message['scheduled_at']) ? 'checked' : ''; ?>
                   class="w-4 h-4 text-[#118568] border-gray-300 focus:ring-[#9DC5BB]"
                   onchange="toggleScheduleFields()">
            <span class="font-medium">Запланировать отправку</span>
          </label>
          
          <div id="schedule-fields" class="ml-7 grid grid-cols-1 md:grid-cols-2 gap-4" 
               style="display: <?php echo !empty($message['scheduled_at']) ? 'flex' : 'none'; ?>;">
            <div>
              <label class="block text-gray-700 font-medium mb-2">Дата</label>
              <input type="date" name="scheduled_date" 
                     value="<?php echo (is_array($message) && isset($message['scheduled_at']) && $message['scheduled_at']) ? date('Y-m-d', strtotime($message['scheduled_at'])) : ''; ?>"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition">
            </div>
            <div>
              <label class="block text-gray-700 font-medium mb-2">Время</label>
              <input type="time" name="scheduled_time"
                     value="<?php echo (is_array($message) && isset($message['scheduled_at']) && $message['scheduled_at']) ? date('H:i', strtotime($message['scheduled_at'])) : ''; ?>"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition">
            </div>
          </div>
        </div>
      </div>

      <!-- Кнопки действий -->
      <div class="flex flex-col md:flex-row gap-4 justify-between">
        <a href="/admin/messaging/details.php?id=<?php echo $message_id; ?>" 
           class="px-8 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium text-center">
          Отмена
        </a>
        
        <div class="flex flex-col md:flex-row gap-4">
          <button type="submit" 
                  class="px-8 py-3 bg-gradient-to-r from-[#17B890] to-[#118568] text-white rounded-lg hover:scale-105 transition font-medium">
            💾 Сохранить изменения
          </button>
        </div>
      </div>
    </form>
  </div>
</main>

<script>
function toggleSpecificUsers() {
    const audience = document.getElementById('target_audience').value;
    const section = document.getElementById('specific-users-section');
    section.style.display = audience === 'specific' ? 'block' : 'none';
}

function toggleScheduleFields() {
    const scheduleRadio = document.getElementById('schedule-radio');
    const scheduleFields = document.getElementById('schedule-fields');
    scheduleFields.style.display = scheduleRadio.checked ? 'flex' : 'none';
}
</script>

<?php include_once('../../includes/footer.php'); ?>