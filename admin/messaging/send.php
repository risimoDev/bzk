<?php
session_start();
$pageTitle = "Отправка рассылки";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/telegram.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../PHPMailer/src/Exception.php';
require __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../../PHPMailer/src/SMTP.php';

$message_id = intval($_GET['id'] ?? 0);

if (!$message_id) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный ID рассылки.'];
    header("Location: /admin/messaging/");
    exit();
}

// Получение информации о рассылке
$stmt = $pdo->prepare("
    SELECT mm.*, u.name as created_by_name 
    FROM mass_messages mm 
    LEFT JOIN users u ON mm.created_by = u.id 
    WHERE mm.id = ?
");
$stmt->execute([$message_id]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Рассылка не найдена.'];
    header("Location: /admin/messaging/");
    exit();
}

if ($message['status'] === 'sent') {
    $_SESSION['notifications'][] = ['type' => 'info', 'message' => 'Рассылка уже была отправлена.'];
    header("Location: /admin/messaging/details.php?id=$message_id");
    exit();
}

// Обработка запуска отправки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    try {
        // Обновляем статус на "отправляется"
        $stmt = $pdo->prepare("UPDATE mass_messages SET status = 'sending' WHERE id = ?");
        $stmt->execute([$message_id]);
        
        // Запускаем отправку в фоне (эмуляция - в реальности нужно использовать очереди)
        $redirect_url = "/admin/messaging/send_process.php?id=$message_id";
        header("Location: $redirect_url");
        exit();
        
    } catch (Exception $e) {
        $error_message = 'Ошибка при запуске отправки: ' . $e->getMessage();
    }
}

// Получение получателей
$recipients_stmt = $pdo->prepare("
    SELECT mmr.*, u.name, u.email, u.telegram_chat_id 
    FROM mass_message_recipients mmr
    LEFT JOIN users u ON mmr.user_id = u.id
    WHERE mmr.mass_message_id = ?
    ORDER BY u.name
");
$recipients_stmt->execute([$message_id]);
$recipients = $recipients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика получателей
$email_pending = 0;
$telegram_pending = 0;
$total_pending = 0;

foreach ($recipients as $recipient) {
    // Защита от ошибок "Illegal string offset"
    if (!is_array($recipient)) {
        continue;
    }
    
    $email_status = $recipient['email_status'] ?? 'pending';
    $telegram_status = $recipient['telegram_status'] ?? 'pending';
    
    if ($email_status === 'pending') $email_pending++;
    if ($telegram_status === 'pending') $telegram_pending++;
    if ($email_status === 'pending' || $telegram_status === 'pending') {
        $total_pending++;
    }
}
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
  <div class="container mx-auto px-6 max-w-6xl">

    <!-- Заголовок -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
      <div>
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="flex gap-3">
        <a href="/admin/messaging/" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition text-sm font-medium">
          ← К списку
        </a>
      </div>
    </div>

    <!-- Заголовок страницы -->
    <div class="text-center mb-8">
      <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Отправка рассылки</h1>
      <p class="text-lg text-gray-700"><?php echo htmlspecialchars($message['title']); ?></p>
      <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <?php if (isset($error_message)): ?>
      <div class="mb-6 p-4 rounded-xl bg-red-100 border border-red-400 text-red-700">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Информация о рассылке -->
      <div class="lg:col-span-2 space-y-8">
        
        <!-- Детали сообщения -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Детали сообщения</h2>
          
          <div class="space-y-4">
            <div>
              <label class="block text-gray-600 text-sm font-medium mb-1">Заголовок</label>
              <p class="text-lg font-semibold"><?php echo htmlspecialchars($message['title']); ?></p>
            </div>
            
            <div>
              <label class="block text-gray-600 text-sm font-medium mb-1">Содержание</label>
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="whitespace-pre-wrap"><?php echo htmlspecialchars($message['content']); ?></p>
              </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-gray-600 text-sm font-medium mb-1">Тип рассылки</label>
                <p class="font-medium">
                  <?php 
                    switch($message['message_type']) {
                      case 'email': echo '📧 Email'; break;
                      case 'telegram': echo '📱 Telegram'; break;
                      case 'both': echo '📧📱 Email + Telegram'; break;
                    }
                  ?>
                </p>
              </div>
              
              <div>
                <label class="block text-gray-600 text-sm font-medium mb-1">Аудитория</label>
                <p class="font-medium">
                  <?php 
                    switch($message['target_audience']) {
                      case 'all': echo 'Все пользователи'; break;
                      case 'customers': echo 'Клиенты'; break;
                      case 'admins': echo 'Администраторы'; break;
                      case 'managers': echo 'Менеджеры'; break;
                      case 'specific': echo 'Выборочно'; break;
                    }
                  ?>
                </p>
              </div>
              
              <div>
                <label class="block text-gray-600 text-sm font-medium mb-1">Создано</label>
                <p class="font-medium"><?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Список получателей -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Получатели (<?php echo count($recipients); ?>)</h2>
          
          <?php if (!empty($recipients)): ?>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="border-b-2 border-gray-200">
                    <th class="text-left py-3 px-4 font-medium text-gray-600">Пользователь</th>
                    <th class="text-center py-3 px-4 font-medium text-gray-600">Email статус</th>
                    <th class="text-center py-3 px-4 font-medium text-gray-600">Telegram статус</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recipients as $recipient): ?>
                    <?php 
                      // Защита от ошибок "Illegal string offset"
                      if (!is_array($recipient)) {
                          continue;
                      }
                      
                      $name = $recipient['name'] ?? 'Неизвестный';
                      $email = $recipient['email'] ?? '';
                      $email_status = $recipient['email_status'] ?? 'pending';
                      $telegram_status = $recipient['telegram_status'] ?? 'pending';
                    ?>
                    <tr class="border-b border-gray-100">
                      <td class="py-4 px-4">
                        <div>
                          <p class="font-semibold"><?php echo htmlspecialchars($name); ?></p>
                          <p class="text-sm text-gray-600"><?php echo htmlspecialchars($email ?: 'Нет email'); ?></p>
                        </div>
                      </td>
                      <td class="py-4 px-4 text-center">
                        <?php
                          $email_badges = [
                            'pending' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Ожидает</span>',
                            'sent' => '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Отправлено</span>',
                            'failed' => '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Ошибка</span>',
                            'skipped' => '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">Пропущено</span>'
                          ];
                          echo $email_badges[$email_status] ?? $email_badges['pending'];
                        ?>
                      </td>
                      <td class="py-4 px-4 text-center">
                        <?php
                          $telegram_badges = [
                            'pending' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Ожидает</span>',
                            'sent' => '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Отправлено</span>',
                            'failed' => '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Ошибка</span>',
                            'skipped' => '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">Пропущено</span>'
                          ];
                          echo $telegram_badges[$telegram_status] ?? $telegram_badges['pending'];
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-8">
              <p class="text-gray-600">Нет получателей для отправки</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Боковая панель -->
      <div class="space-y-6">
        
        <!-- Статистика -->
        <div class="bg-white rounded-3xl shadow-xl p-6">
          <h3 class="text-xl font-bold text-gray-800 mb-4">Статистика</h3>
          <div class="space-y-4">
            <div class="flex justify-between">
              <span class="text-gray-600">Всего получателей:</span>
              <span class="font-bold text-[#118568]"><?php echo count($recipients); ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Email ожидают:</span>
              <span class="font-medium text-yellow-600"><?php echo $email_pending; ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Telegram ожидают:</span>
              <span class="font-medium text-yellow-600"><?php echo $telegram_pending; ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Отправлено Email:</span>
              <span class="font-medium text-green-600"><?php echo $message['emails_sent']; ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Отправлено Telegram:</span>
              <span class="font-medium text-green-600"><?php echo $message['telegrams_sent']; ?></span>
            </div>
          </div>
        </div>

        <!-- Управление отправкой -->
        <?php if ($message['status'] === 'draft' && $total_pending > 0): ?>
          <div class="bg-white rounded-3xl shadow-xl p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Управление</h3>
            
            <form method="POST" id="send-form">
              <input type="hidden" name="action" value="send">
              <button type="submit" 
                      class="w-full py-3 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-lg hover:scale-105 transition font-medium"
                      onclick="return confirm('Вы уверены, что хотите начать отправку рассылки?')">
                🚀 Начать отправку
              </button>
            </form>
            
            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
              <p class="text-sm text-blue-800">
                <strong>Внимание:</strong> После начала отправки процесс нельзя будет остановить. 
                Убедитесь, что все данные корректны.
              </p>
            </div>
          </div>
        <?php elseif ($message['status'] === 'sending'): ?>
          <div class="bg-white rounded-3xl shadow-xl p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Отправка в процессе</h3>
            <div class="flex items-center justify-center py-8">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#118568]"></div>
            </div>
            <p class="text-center text-gray-600">Рассылка отправляется...</p>
            <button onclick="location.reload()" 
                    class="w-full mt-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
              🔄 Обновить страницу
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include_once('../../includes/footer.php'); ?>