<?php
session_start();
$pageTitle = "Детали рассылки";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';

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

// Получение получателей с подробной статистикой
$recipients_stmt = $pdo->prepare("
    SELECT mmr.*, u.name, u.email, u.telegram_chat_id, u.role
    FROM mass_message_recipients mmr
    LEFT JOIN users u ON mmr.user_id = u.id
    WHERE mmr.mass_message_id = ?
    ORDER BY u.name
");
$recipients_stmt->execute([$message_id]);
$recipients = $recipients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика по статусам
$email_stats = [
    'pending' => 0,
    'sent' => 0,
    'failed' => 0,
    'skipped' => 0
];

$telegram_stats = [
    'pending' => 0,
    'sent' => 0,
    'failed' => 0,
    'skipped' => 0
];

foreach ($recipients as $recipient) {
    if (is_array($recipient)) {
        $email_status = $recipient['email_status'] ?? 'pending';
        $telegram_status = $recipient['telegram_status'] ?? 'pending';

        if (isset($email_stats[$email_status])) {
            $email_stats[$email_status]++;
        }

        if (isset($telegram_stats[$telegram_status])) {
            $telegram_stats[$telegram_status]++;
        }
    }
}

// Общая статистика
$total_recipients = count($recipients);
$email_success_rate = $total_recipients > 0 ? round(($email_stats['sent'] / $total_recipients) * 100, 1) : 0;
$telegram_success_rate = $total_recipients > 0 ? round(($telegram_stats['sent'] / $total_recipients) * 100, 1) : 0;
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
    <div class="container mx-auto px-6 max-w-7xl">

        <!-- Заголовок -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
            <div>
                <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
            </div>
            <div class="flex gap-3">
                <a href="/admin/messaging/"
                    class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition text-sm font-medium">
                    ← К списку
                </a>
                <?php if (($message['status'] ?? '') === 'draft'): ?>
                    <a href="/admin/messaging/edit.php?id=<?php echo $message_id; ?>"
                        class="px-5 py-2.5 bg-[#17B890] text-white rounded-xl hover:bg-[#15a081] transition text-sm font-medium">
                        ✏️ Редактировать
                    </a>
                    <a href="/admin/messaging/send.php?id=<?php echo $message_id; ?>"
                        class="px-5 py-2.5 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition text-sm font-medium">
                        🚀 Отправить
                    </a>
                <?php elseif (($message['status'] ?? '') === 'sending'): ?>
                    <a href="/admin/messaging/send.php?id=<?php echo $message_id; ?>"
                        class="px-5 py-2.5 bg-orange-500 text-white rounded-xl hover:bg-orange-600 transition text-sm font-medium">
                        📊 Мониторинг
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Заголовок страницы -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Детали рассылки</h1>
            <p class="text-lg text-gray-700">
                <?php echo htmlspecialchars($message['title'] ?? 'Неизвестная рассылка'); ?></p>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">

            <!-- Основная информация -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Информация о рассылке -->
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Информация о рассылке</h2>
                        <span class="px-4 py-2 text-sm font-medium rounded-full 
              <?php
              $status = $message['status'] ?? 'unknown';
              switch ($status) {
                  case 'draft':
                      echo 'bg-gray-100 text-gray-800';
                      break;
                  case 'scheduled':
                      echo 'bg-blue-100 text-blue-800';
                      break;
                  case 'sending':
                      echo 'bg-orange-100 text-orange-800';
                      break;
                  case 'sent':
                      echo 'bg-green-100 text-green-800';
                      break;
                  case 'failed':
                      echo 'bg-red-100 text-red-800';
                      break;
                  default:
                      echo 'bg-gray-100 text-gray-800';
                      break;
              }
              ?>">
                            <?php
                            switch ($status) {
                                case 'draft':
                                    echo '📝 Черновик';
                                    break;
                                case 'scheduled':
                                    echo '⏰ Запланировано';
                                    break;
                                case 'sending':
                                    echo '🔄 Отправляется';
                                    break;
                                case 'sent':
                                    echo '✅ Отправлено';
                                    break;
                                case 'failed':
                                    echo '❌ Ошибка';
                                    break;
                                default:
                                    echo '❓ Неизвестно';
                                    break;
                            }
                            ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-600 text-sm font-medium mb-2">Заголовок</label>
                            <p class="text-lg font-semibold text-gray-800">
                                <?php echo htmlspecialchars($message['title'] ?? 'Без заголовка'); ?></p>
                        </div>

                        <div>
                            <label class="block text-gray-600 text-sm font-medium mb-2">Тип рассылки</label>
                            <p class="text-lg font-medium">
                                <?php
                                $message_type = $message['message_type'] ?? 'unknown';
                                switch ($message_type) {
                                    case 'email':
                                        echo '📧 Email';
                                        break;
                                    case 'telegram':
                                        echo '📱 Telegram';
                                        break;
                                    case 'both':
                                        echo '📧📱 Email + Telegram';
                                        break;
                                    default:
                                        echo '❓ Неизвестно';
                                        break;
                                }
                                ?>
                            </p>
                        </div>

                        <div>
                            <label class="block text-gray-600 text-sm font-medium mb-2">Аудитория</label>
                            <p class="text-lg font-medium">
                                <?php
                                $target_audience = $message['target_audience'] ?? 'unknown';
                                switch ($target_audience) {
                                    case 'all':
                                        echo 'Все пользователи';
                                        break;
                                    case 'customers':
                                        echo 'Клиенты';
                                        break;
                                    case 'admins':
                                        echo 'Администраторы';
                                        break;
                                    case 'managers':
                                        echo 'Менеджеры';
                                        break;
                                    case 'specific':
                                        echo 'Выборочно';
                                        break;
                                    default:
                                        echo 'Неизвестно';
                                        break;
                                }
                                ?>
                            </p>
                        </div>

                        <div>
                            <label class="block text-gray-600 text-sm font-medium mb-2">Создано</label>
                            <p class="text-lg font-medium">
                                <?php echo (is_array($message) && isset($message['created_at']) && $message['created_at']) ? date('d.m.Y H:i', strtotime($message['created_at'])) : 'Неизвестно'; ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($message['created_by_name'] ?? 'Неизвестный автор'); ?>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($message['content'])): ?>
                        <div class="mt-6">
                            <label class="block text-gray-600 text-sm font-medium mb-2">Содержание сообщения</label>
                            <div class="bg-gray-50 rounded-lg p-4 border">
                                <p class="whitespace-pre-wrap text-gray-800">
                                    <?php echo htmlspecialchars($message['content']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (is_array($message) && isset($message['scheduled_at']) && $message['scheduled_at']): ?>
                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <h4 class="font-medium text-blue-800 mb-1">📅 Запланированная отправка</h4>
                            <p class="text-blue-700"><?php echo date('d.m.Y H:i', strtotime($message['scheduled_at'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Статистика отправки -->
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Статистика отправки</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Email статистика -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-700 mb-4">📧 Email статистика</h3>
                            <div class="space-y-3">
                                <?php foreach ($email_stats as $status => $count): ?>
                                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                        <span class="text-gray-600">
                                            <?php
                                            switch ($status) {
                                                case 'pending':
                                                    echo '⏳ Ожидают';
                                                    break;
                                                case 'sent':
                                                    echo '✅ Отправлено';
                                                    break;
                                                case 'failed':
                                                    echo '❌ Ошибки';
                                                    break;
                                                case 'skipped':
                                                    echo '⏭️ Пропущено';
                                                    break;
                                            }
                                            ?>
                                        </span>
                                        <span class="font-bold 
                      <?php
                      switch ($status) {
                          case 'sent':
                              echo 'text-green-600';
                              break;
                          case 'failed':
                              echo 'text-red-600';
                              break;
                          case 'pending':
                              echo 'text-yellow-600';
                              break;
                          default:
                              echo 'text-gray-600';
                              break;
                      }
                      ?>">
                                            <?php echo $count; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="pt-2 border-t">
                                    <div class="flex justify-between items-center">
                                        <span class="font-medium text-gray-700">Успешность:</span>
                                        <span
                                            class="font-bold text-[#118568]"><?php echo $email_success_rate; ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Telegram статистика -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-700 mb-4">📱 Telegram статистика</h3>
                            <div class="space-y-3">
                                <?php foreach ($telegram_stats as $status => $count): ?>
                                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                        <span class="text-gray-600">
                                            <?php
                                            switch ($status) {
                                                case 'pending':
                                                    echo '⏳ Ожидают';
                                                    break;
                                                case 'sent':
                                                    echo '✅ Отправлено';
                                                    break;
                                                case 'failed':
                                                    echo '❌ Ошибки';
                                                    break;
                                                case 'skipped':
                                                    echo '⏭️ Пропущено';
                                                    break;
                                            }
                                            ?>
                                        </span>
                                        <span class="font-bold 
                      <?php
                      switch ($status) {
                          case 'sent':
                              echo 'text-green-600';
                              break;
                          case 'failed':
                              echo 'text-red-600';
                              break;
                          case 'pending':
                              echo 'text-yellow-600';
                              break;
                          default:
                              echo 'text-gray-600';
                              break;
                      }
                      ?>">
                                            <?php echo $count; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="pt-2 border-t">
                                    <div class="flex justify-between items-center">
                                        <span class="font-medium text-gray-700">Успешность:</span>
                                        <span
                                            class="font-bold text-[#118568]"><?php echo $telegram_success_rate; ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Список получателей -->
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Получатели (<?php echo $total_recipients; ?>)</h2>

                    <?php if (!empty($recipients)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b-2 border-gray-200">
                                        <th class="text-left py-3 px-4 font-medium text-gray-600">Пользователь</th>
                                        <th class="text-center py-3 px-4 font-medium text-gray-600">Роль</th>
                                        <th class="text-center py-3 px-4 font-medium text-gray-600">Email</th>
                                        <th class="text-center py-3 px-4 font-medium text-gray-600">Telegram</th>
                                        <th class="text-center py-3 px-4 font-medium text-gray-600">Статус отправки</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recipients as $recipient): ?>
                                        <?php if (!is_array($recipient))
                                            continue; ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                                            <td class="py-4 px-4">
                                                <div>
                                                    <p class="font-semibold">
                                                        <?php echo htmlspecialchars($recipient['name'] ?? 'Неизвестный'); ?></p>
                                                    <p class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($recipient['email'] ?? 'Нет email'); ?></p>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <span class="px-2 py-1 text-xs rounded-full 
                          <?php
                          $role = $recipient['role'] ?? 'user';
                          switch ($role) {
                              case 'admin':
                                  echo 'bg-red-100 text-red-800';
                                  break;
                              case 'manager':
                                  echo 'bg-blue-100 text-blue-800';
                                  break;
                              default:
                                  echo 'bg-gray-100 text-gray-800';
                                  break;
                          }
                          ?>">
                                                    <?php
                                                    switch ($role) {
                                                        case 'admin':
                                                            echo 'Админ';
                                                            break;
                                                        case 'manager':
                                                            echo 'Менеджер';
                                                            break;
                                                        default:
                                                            echo 'Клиент';
                                                            break;
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <?php
                                                $email_status = $recipient['email_status'] ?? 'pending';
                                                $email_badges = [
                                                    'pending' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">⏳</span>',
                                                    'sent' => '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">✅</span>',
                                                    'failed' => '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">❌</span>',
                                                    'skipped' => '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">⏭️</span>'
                                                ];
                                                echo $email_badges[$email_status] ?? $email_badges['pending'];
                                                ?>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <?php
                                                $telegram_status = $recipient['telegram_status'] ?? 'pending';
                                                $telegram_badges = [
                                                    'pending' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">⏳</span>',
                                                    'sent' => '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">✅</span>',
                                                    'failed' => '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">❌</span>',
                                                    'skipped' => '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">⏭️</span>'
                                                ];
                                                echo $telegram_badges[$telegram_status] ?? $telegram_badges['pending'];
                                                ?>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <?php 
                                                $sent_at = $recipient['email_sent_at'] ?? $recipient['telegram_sent_at'] ?? null;
                                                if ($sent_at): ?>
                                                    <span class="text-xs text-gray-500">
                                                        <?php echo date('d.m.Y H:i', strtotime($sent_at)); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400">Не отправлено</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <p class="text-gray-600">Нет получателей для этой рассылки</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Боковая панель -->
            <div class="lg:col-span-1 space-y-6">

                <!-- Быстрая статистика -->
                <div class="bg-white rounded-3xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Сводка</h3>
                    <div class="space-y-4">
                        <div class="text-center p-4 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-lg text-white">
                            <p class="text-2xl font-bold"><?php echo $total_recipients; ?></p>
                            <p class="text-sm opacity-90">Всего получателей</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                <p class="text-lg font-bold text-green-600"><?php echo $email_stats['sent']; ?></p>
                                <p class="text-xs text-green-700">Email</p>
                            </div>
                            <div class="text-center p-3 bg-blue-50 rounded-lg">
                                <p class="text-lg font-bold text-blue-600"><?php echo $telegram_stats['sent']; ?></p>
                                <p class="text-xs text-blue-700">Telegram</p>
                            </div>
                        </div>

                        <?php if ($email_stats['failed'] > 0 || $telegram_stats['failed'] > 0): ?>
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-sm text-red-700 font-medium">
                                    ⚠️ Ошибки: <?php echo $email_stats['failed'] + $telegram_stats['failed']; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Действия -->
                <div class="bg-white rounded-3xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Действия</h3>
                    <div class="space-y-3">
                        <?php if (($message['status'] ?? '') === 'draft'): ?>
                            <a href="/admin/messaging/edit.php?id=<?php echo $message_id; ?>"
                                class="w-full block py-2 px-4 bg-[#17B890] text-white text-center rounded-lg hover:bg-[#15a081] transition text-sm">
                                ✏️ Редактировать
                            </a>
                            <a href="/admin/messaging/send.php?id=<?php echo $message_id; ?>"
                                class="w-full block py-2 px-4 bg-[#118568] text-white text-center rounded-lg hover:bg-[#0f755a] transition text-sm">
                                🚀 Отправить
                            </a>
                        <?php elseif (($message['status'] ?? '') === 'sending'): ?>
                            <a href="/admin/messaging/send.php?id=<?php echo $message_id; ?>"
                                class="w-full block py-2 px-4 bg-orange-500 text-white text-center rounded-lg hover:bg-orange-600 transition text-sm">
                                📊 Мониторинг
                            </a>
                        <?php endif; ?>

                        <button onclick="window.print()"
                            class="w-full py-2 px-4 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm">
                            🖨️ Печать отчета
                        </button>

                        <a href="/admin/messaging/"
                            class="w-full block py-2 px-4 bg-gray-100 text-gray-800 text-center rounded-lg hover:bg-gray-200 transition text-sm">
                            ← К списку рассылок
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>