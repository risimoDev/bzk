<?php
require_once __DIR__ . '/../includes/session.php';
$pageTitle = "Настройка Telegram уведомлений";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');
include_once('../includes/security.php');

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

// Обработка сохранения chat_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_chat_id'])) {
    $chat_id = trim($_POST['telegram_chat_id']);

    // Валидация chat_id (должен быть числом)
    if (empty($chat_id)) {
        $chat_id = null;
    } elseif (!is_numeric($chat_id)) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Chat ID должен содержать только цифры'];
        header("Location: /admin/telegram_setup");
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?");
        if ($stmt->execute([$chat_id, $_SESSION['user_id']])) {
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Настройки Telegram успешно сохранены!'];
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при сохранении настроек'];
        }
    } catch (Exception $e) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка базы данных: ' . $e->getMessage()];
    }

    header("Location: /admin/telegram_setup");
    exit();
}

// Обработка сохранения предпочтений уведомлений
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    verify_csrf();

    $receive_task_created = isset($_POST['receive_task_created']) ? 1 : 0;
    $receive_new_order = isset($_POST['receive_new_order']) ? 1 : 0;
    $receive_task_status = isset($_POST['receive_task_status']) ? 1 : 0;
    $pref_channel = in_array($_POST['pref_channel'] ?? 'both', ['telegram', 'email', 'both'], true) ? $_POST['pref_channel'] : 'both';
    $show_task_buttons = isset($_POST['show_task_buttons']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("INSERT INTO notification_prefs (user_id, receive_task_created, receive_new_order, receive_task_status, pref_channel, show_task_buttons)
                               VALUES (?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                                 receive_task_created=VALUES(receive_task_created),
                                 receive_new_order=VALUES(receive_new_order),
                                 receive_task_status=VALUES(receive_task_status),
                                 pref_channel=VALUES(pref_channel),
                                 show_task_buttons=VALUES(show_task_buttons)");
        $stmt->execute([$_SESSION['user_id'], $receive_task_created, $receive_new_order, $receive_task_status, $pref_channel, $show_task_buttons]);
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Настройки уведомлений сохранены.'];
    } catch (Exception $e) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка сохранения настроек: ' . $e->getMessage()];
    }

    header("Location: /admin/telegram_setup");
    exit();
}

// Получение текущих настроек пользователя
$stmt = $pdo->prepare("SELECT telegram_chat_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$current_chat_id = $user_data['telegram_chat_id'] ?? '';

// Текущие предпочтения уведомлений
$prefs = [
    'receive_task_created' => 1,
    'receive_new_order' => 1,
    'receive_task_status' => 1,
    'pref_channel' => 'both',
    'show_task_buttons' => 1,
];
try {
    $stmt = $pdo->prepare("SELECT receive_task_created, receive_new_order, receive_task_status, pref_channel, show_task_buttons FROM notification_prefs WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $prefs = array_merge($prefs, $row);
    }
} catch (Exception $e) { /* ignore */
}

// Получение токена бота для инструкций
$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? 'НЕ_НАСТРОЕН';
?>

<?php include_once('../includes/header.php'); ?>

<main class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Breadcrumbs -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div class="w-full md:w-auto">
            <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
        </div>
        <div class="w-full md:w-auto">
            <?php echo backButton(); ?>
        </div>
    </div>

    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-800 mb-4">Настройка Telegram уведомлений</h1>
        <p class="text-lg text-gray-600">Настройте получение уведомлений о задачах и рассылках в Telegram</p>
        <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $n): ?>
        <div
            class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($n['message']); ?>
        </div>
    <?php endforeach; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Автоматический способ -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
            <div class="flex items-center mb-6">
                <div
                    class="w-12 h-12 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-xl flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">🚀 Быстрое подключение</h2>
            </div>

            <div class="space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-blue-800 font-medium mb-2">⚡ Самый простой способ!</p>
                    <p class="text-blue-700 text-sm">Бот автоматически определит ваш Chat ID и подключит аккаунт.</p>
                </div>

                <div class="space-y-3">
                    <h3 class="font-bold text-gray-800">📋 Пошаговая инструкция:</h3>
                    <div class="space-y-2">
                        <div class="flex items-start">
                            <span
                                class="bg-[#118568] text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center mr-3 mt-0.5">1</span>
                            <div>
                                <p class="font-medium">Перейдите в Telegram бот</p>
                                <a href="https://t.me/BZKPrintBot" target="_blank"
                                    class="inline-block mt-1 px-4 py-2 bg-[#0088cc] text-white rounded-lg hover:bg-[#006699] transition text-sm">
                                    📱 Открыть бот в Telegram
                                </a>
                                <p class="text-xs text-gray-500 mt-1">Если ссылка не работает, найдите @BZKPrintBot в
                                    поиске</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <span
                                class="bg-[#118568] text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center mr-3 mt-0.5">2</span>
                            <div>
                                <p class="font-medium">Нажмите кнопку "Start" или отправьте</p>
                                <code class="bg-gray-100 px-2 py-1 rounded text-sm">/start</code>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <span
                                class="bg-[#118568] text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center mr-3 mt-0.5">3</span>
                            <div class="flex-1">
                                <p class="font-medium">Отправьте команду подключения:</p>
                                <div class="mt-1 p-2 bg-gray-50 rounded border flex items-center justify-between">
                                    <code class="text-sm"
                                        id="connect-command">/connect <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'ваш@email.com'); ?></code>
                                    <button
                                        onclick="copyToClipboard('/connect <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'ваш@email.com'); ?>')"
                                        class="ml-2 px-2 py-1 text-[#118568] hover:text-[#0f755a] text-xs hover:bg-gray-100 rounded transition">
                                        📋 Копировать
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <span
                                class="bg-green-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center mr-3 mt-0.5">✓</span>
                            <p class="font-medium text-green-700">Готово! Вы получите подтверждение в боте</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ручной способ -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="flex items-center mb-6">
                <div
                    class="w-12 h-12 bg-gradient-to-r from-[#17B890] to-[#9DC5BB] rounded-xl flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">⚙️ Ручная настройка</h2>
            </div>

            <form method="post" class="space-y-6">
                <div>
                    <label for="telegram_chat_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Telegram Chat ID
                    </label>
                    <input type="text" id="telegram_chat_id" name="telegram_chat_id"
                        value="<?php echo htmlspecialchars($current_chat_id); ?>" placeholder="Например: 123456789"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300">
                    <p class="mt-2 text-sm text-gray-500">
                        Оставьте пустым, чтобы отключить уведомления
                    </p>
                </div>

                <div class="pt-4">
                    <button type="submit" name="save_chat_id"
                        class="w-full px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 font-medium">
                        💾 Сохранить настройки
                    </button>
                </div>
            </form>

            <?php if (!empty($current_chat_id)): ?>
                <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-green-800">Уведомления настроены!</span>
                    </div>
                    <p class="text-sm text-green-700 mt-1">
                        Вы будете получать уведомления о назначенных вам задачах
                    </p>
                </div>
            <?php else: ?>
                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 mr-2" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span class="text-sm font-medium text-yellow-800">Уведомления отключены</span>
                    </div>
                    <p class="text-sm text-yellow-700 mt-1">
                        Настройте Chat ID для получения уведомлений
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Настройки уведомлений -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="flex items-center mb-6">
                <div
                    class="w-12 h-12 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-xl flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" viewBox="0 0 24 24"
                        fill="currentColor">
                        <path
                            d="M12 6a9 9 0 100 18 9 9 0 000-18zm.75 4.5a.75.75 0 00-1.5 0V15a.75.75 0 001.5 0v-4.5zm-.75 7.5a1 1 0 110-2 1 1 0 010 2z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">⚙️ Настройки уведомлений</h2>
            </div>
            <form method="post" class="space-y-6">
                <?php echo csrf_field(); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="receive_task_created" class="w-5 h-5" <?php echo $prefs['receive_task_created'] ? 'checked' : ''; ?>>
                            <span>Получать уведомления о новых задачах</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="receive_new_order" class="w-5 h-5" <?php echo $prefs['receive_new_order'] ? 'checked' : ''; ?>>
                            <span>Получать уведомления о новых заказах</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="receive_task_status" class="w-5 h-5" <?php echo $prefs['receive_task_status'] ? 'checked' : ''; ?>>
                            <span>Получать уведомления об изменении статусов задач</span>
                        </label>
                    </div>
                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-gray-700">Предпочтительный канал</label>
                        <select name="pref_channel"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent">
                            <option value="both" <?php echo $prefs['pref_channel'] === 'both' ? 'selected' : ''; ?>>Telegram и
                                Email</option>
                            <option value="telegram" <?php echo $prefs['pref_channel'] === 'telegram' ? 'selected' : ''; ?>>
                                Только Telegram</option>
                            <option value="email" <?php echo $prefs['pref_channel'] === 'email' ? 'selected' : ''; ?>>Только
                                Email</option>
                        </select>
                        <label class="flex items-center gap-3 mt-3">
                            <input type="checkbox" name="show_task_buttons" class="w-5 h-5" <?php echo $prefs['show_task_buttons'] ? 'checked' : ''; ?>>
                            <span>Показывать кнопки задач в Telegram</span>
                        </label>
                    </div>
                </div>
                <div class="pt-4">
                    <button type="submit" name="save_prefs"
                        class="w-full px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 font-medium">💾
                        Сохранить уведомления</button>
                </div>
            </form>
        </div>

        <!-- Инструкция -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h2 class="text-xl font-bold text-gray-800 mb-6">📋 Пошаговая инструкция</h2>

            <div class="space-y-6">
                <!-- Шаг 1 -->
                <div class="flex">
                    <div class="flex-shrink-0">
                        <div
                            class="w-8 h-8 bg-[#118568] text-white rounded-full flex items-center justify-center text-sm font-bold">
                            1</div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800">Найдите нашего бота</h3>
                        <p class="text-gray-600 mt-1">Найдите бота в Telegram и напишите ему любое сообщение</p>
                        <?php if ($bot_token !== 'НЕ_НАСТРОЕН'): ?>
                            <div class="mt-2 p-3 bg-gray-100 rounded-lg">
                                <code class="text-sm">Имя бота будет предоставлено администратором</code>
                            </div>
                        <?php else: ?>
                            <div class="mt-2 p-3 bg-red-100 rounded-lg">
                                <p class="text-red-700 text-sm">⚠️ Бот не настроен. Обратитесь к администратору.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Шаг 2 -->
                <div class="flex">
                    <div class="flex-shrink-0">
                        <div
                            class="w-8 h-8 bg-[#118568] text-white rounded-full flex items-center justify-center text-sm font-bold">
                            2</div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800">Получите Chat ID</h3>
                        <p class="text-gray-600 mt-1">Обратитесь к администратору для получения вашего Chat ID</p>
                        <div class="mt-2 text-sm text-gray-500">
                            <p>• Напишите боту любое сообщение</p>
                            <p>• Сообщите администратору о том, что написали боту</p>
                            <p>• Администратор предоставит вам Chat ID</p>
                        </div>
                    </div>
                </div>

                <!-- Шаг 3 -->
                <div class="flex">
                    <div class="flex-shrink-0">
                        <div
                            class="w-8 h-8 bg-[#118568] text-white rounded-full flex items-center justify-center text-sm font-bold">
                            3</div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800">Введите Chat ID</h3>
                        <p class="text-gray-600 mt-1">Введите полученный Chat ID в форму выше и сохраните</p>
                    </div>
                </div>

                <!-- Шаг 4 -->
                <div class="flex">
                    <div class="flex-shrink-0">
                        <div
                            class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                            ✓</div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800">Готово!</h3>
                        <p class="text-gray-600 mt-1">Теперь вы будете получать уведомления о задачах в Telegram</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h4 class="text-sm font-medium text-blue-800 mb-2">💡 Полезная информация</h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Уведомления приходят при назначении новых задач</li>
                    <li>• Уведомления приходят при изменении статуса ваших задач</li>
                    <li>• Вы можете отключить уведомления, удалив Chat ID</li>
                    <li>• Если возникают проблемы, обратитесь к администратору</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<script>
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            // Modern async clipboard API
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Команда скопирована в буфер обмена!', 'success');
            }).catch(() => {
                fallbackCopyTextToClipboard(text);
            });
        } else {
            // Fallback method
            fallbackCopyTextToClipboard(text);
        }
    }

    function fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";
        textArea.style.opacity = "0";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            showNotification('Команда скопирована в буфер обмена!', 'success');
        } catch (err) {
            showNotification('Ошибка копирования. Скопируйте вручную.', 'error');
        }

        document.body.removeChild(textArea);
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-100 text-green-700 border border-green-400' :
            'bg-red-100 text-red-700 border border-red-400'
            }`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
</script>

<?php include_once('../includes/footer.php'); ?>