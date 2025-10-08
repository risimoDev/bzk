<?php
session_start();
$pageTitle = "Создать рассылку";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/telegram.php';
require_once '../../includes/security.php';
// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            // Обработка времени отправки
            $scheduled_at = null;
            if ($schedule_type === 'scheduled' && !empty($scheduled_date) && !empty($scheduled_time)) {
                $scheduled_at = $scheduled_date . ' ' . $scheduled_time . ':00';
            }

            // Обработка специфических пользователей
            $specific_ids_json = null;
            if ($target_audience === 'specific' && !empty($specific_user_ids)) {
                $specific_ids_json = json_encode(array_map('intval', $specific_user_ids));
            }

            // Создание записи рассылки
            $status = ($schedule_type === 'scheduled') ? 'scheduled' : 'draft';
            $stmt = $pdo->prepare("
                INSERT INTO mass_messages 
                (title, content, message_type, target_audience, specific_user_ids, status, scheduled_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $content,
                $message_type,
                $target_audience,
                $specific_ids_json,
                $status,
                $scheduled_at,
                $_SESSION['user_id']
            ]);

            $message_id = $pdo->lastInsertId();

            // Определение получателей
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

            // Добавление получателей
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

            // Если отправка немедленная - начинаем отправку
            if ($schedule_type === 'now') {
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Рассылка создана и запущена!'];
                header("Location: /admin/messaging/send.php?id=$message_id");
                exit();
            } else {
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Рассылка успешно создана и запланирована!'];
                header("Location: /admin/messaging/");
                exit();
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $notifications[] = ['type' => 'error', 'message' => 'Ошибка при создании рассылки: ' . $e->getMessage()];
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
                <a href="/admin/messaging/"
                    class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition text-sm font-medium">
                    ← К списку
                </a>
            </div>
        </div>

        <!-- Заголовок страницы -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Создать рассылку</h1>
            <p class="text-lg text-gray-700">Массовая отправка сообщений пользователям</p>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <!-- Уведомления -->
        <?php foreach ($notifications as $n): ?>
            <div
                class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <?php echo htmlspecialchars($n['message']); ?>
            </div>
        <?php endforeach; ?>

        <!-- Форма -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
            <form method="POST" class="space-y-8" id="message-form">
                <?php echo csrf_field(); ?>

                <!-- Основная информация -->
                <section>
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Основная информация</h2>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Заголовок сообщения *</label>
                            <input type="text" name="title" required
                                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]"
                                placeholder="Введите заголовок рассылки">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Текст сообщения *</label>
                            <textarea name="content" required rows="6"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]"
                                placeholder="Введите текст сообщения..."><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                            <p class="text-sm text-gray-600 mt-2">Поддерживается HTML для email рассылок и обычный текст
                                для Telegram</p>
                        </div>
                    </div>
                </section>

                <!-- Настройки рассылки -->
                <section>
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Настройки рассылки</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Тип рассылки</label>
                            <select name="message_type"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                                <option value="both" <?php echo ($_POST['message_type'] ?? 'both') === 'both' ? 'selected' : ''; ?>>Email + Telegram</option>
                                <option value="email" <?php echo ($_POST['message_type'] ?? '') === 'email' ? 'selected' : ''; ?>>Только Email</option>
                                <option value="telegram" <?php echo ($_POST['message_type'] ?? '') === 'telegram' ? 'selected' : ''; ?>>Только Telegram</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Целевая аудитория</label>
                            <select name="target_audience" id="target-audience"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                                <option value="all" <?php echo ($_POST['target_audience'] ?? 'all') === 'all' ? 'selected' : ''; ?>>Все пользователи</option>
                                <option value="customers" <?php echo ($_POST['target_audience'] ?? '') === 'customers' ? 'selected' : ''; ?>>Клиенты</option>
                                <option value="admins" <?php echo ($_POST['target_audience'] ?? '') === 'admins' ? 'selected' : ''; ?>>Администраторы</option>
                                <option value="managers" <?php echo ($_POST['target_audience'] ?? '') === 'managers' ? 'selected' : ''; ?>>Менеджеры</option>
                                <option value="specific" <?php echo ($_POST['target_audience'] ?? '') === 'specific' ? 'selected' : ''; ?>>Выборочно</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Выбор конкретных пользователей -->
                <section id="specific-users-section" style="display: none;">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Выбор пользователей</h2>
                    <div class="max-h-60 overflow-y-auto border-2 border-gray-200 rounded-lg p-4">
                        <?php foreach ($users as $user): ?>
                            <label class="flex items-center space-x-3 mb-3 hover:bg-gray-50 p-2 rounded">
                                <input type="checkbox" name="specific_users[]" value="<?php echo $user['id']; ?>"
                                    class="w-4 h-4 text-[#118568] border-gray-300 rounded focus:ring-[#9DC5BB]">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium"><?php echo htmlspecialchars($user['name']); ?></span>
                                        <span class="text-xs px-2 py-1 rounded-full 
                      <?php
                      switch ($user['role']) {
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
                                            switch ($user['role']) {
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
                </section>

                <!-- Планирование отправки -->
                <section>
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Время отправки</h2>
                    <div class="space-y-4">
                        <label class="flex items-center space-x-3">
                            <input type="radio" name="schedule_type" value="now" checked
                                class="w-4 h-4 text-[#118568] border-gray-300 focus:ring-[#9DC5BB]">
                            <span class="font-medium">Отправить сейчас</span>
                        </label>
                        <label class="flex items-center space-x-3">
                            <input type="radio" name="schedule_type" value="scheduled" id="schedule-radio"
                                class="w-4 h-4 text-[#118568] border-gray-300 focus:ring-[#9DC5BB]">
                            <span class="font-medium">Запланировать отправку</span>
                        </label>
                        <div id="schedule-fields" class="ml-7 grid grid-cols-1 md:grid-cols-2 gap-4"
                            style="display: none;">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Дата</label>
                                <input type="date" name="scheduled_date" min="<?php echo date('Y-m-d'); ?>"
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Время</label>
                                <input type="time" name="scheduled_time"
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Кнопки -->
                <div class="flex gap-4 pt-6">
                    <button type="submit"
                        class="flex-1 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:scale-105 transition font-bold text-lg shadow">
                        Создать рассылку
                    </button>
                    <a href="/admin/messaging/"
                        class="flex-1 px-4 py-4 bg-gray-200 text-gray-700 text-center rounded-xl hover:bg-gray-300 transition font-bold text-lg">
                        Отмена
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const targetAudience = document.getElementById('target-audience');
        const specificUsersSection = document.getElementById('specific-users-section');
        const scheduleRadio = document.getElementById('schedule-radio');
        const scheduleFields = document.getElementById('schedule-fields');

        // Показ/скрытие выбора пользователей
        targetAudience.addEventListener('change', function () {
            if (this.value === 'specific') {
                specificUsersSection.style.display = 'block';
            } else {
                specificUsersSection.style.display = 'none';
            }
        });

        // Показ/скрытие полей планирования
        document.querySelectorAll('input[name="schedule_type"]').forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.value === 'scheduled') {
                    scheduleFields.style.display = 'grid';
                } else {
                    scheduleFields.style.display = 'none';
                }
            });
        });

        // Инициализация
        if (targetAudience.value === 'specific') {
            specificUsersSection.style.display = 'block';
        }
    });
</script>

<?php include_once('../../includes/footer.php'); ?>