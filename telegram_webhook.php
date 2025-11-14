<?php
/**
 * Secure Telegram Webhook for BZK Print Application
 * Enhanced with security validation and rate limiting
 */

require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/telegram.php';

// Security: Verify Telegram secret token if configured
function verifyTelegramWebhook()
{
    $secret_token = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? null;

    if ($secret_token) {
        $received_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if (!hash_equals($secret_token, $received_token)) {
            error_log('Telegram webhook: Invalid secret token');
            http_response_code(401);
            exit('Unauthorized');
        }
    }
}

// Verify webhook security
verifyTelegramWebhook();

// Rate limiting
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit = check_rate_limit($client_ip, 'telegram_webhook', 100, 300); // 100 requests per 5 minutes
if (!$rate_limit['allowed']) {
    http_response_code(429);
    exit('Too Many Requests');
}
record_rate_limit_attempt($client_ip, 'telegram_webhook');

// Validate and sanitize input
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    exit('Bad Request');
}

$update = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Telegram webhook: Invalid JSON');
    http_response_code(400);
    exit('Invalid JSON');
}

// Log for security monitoring (sanitized)
error_log('Telegram webhook received from IP: ' . $client_ip);

// Обработка callback query (нажатия на кнопки)
if (isset($update['callback_query'])) {
    $telegram = getTelegramBot();
    $telegram->handleCallbackQuery($update['callback_query']);
    http_response_code(200);
    exit('OK');
}

if (!isset($update['message'])) {
    http_response_code(200);
    exit('OK');
}

$message = $update['message'];
$chat_id = (int) ($message['chat']['id'] ?? 0);
$user_id = (int) ($message['from']['id'] ?? 0);
$first_name = sanitize_text($message['from']['first_name'] ?? '', 50);
$last_name = sanitize_text($message['from']['last_name'] ?? '', 50);
$username = sanitize_text($message['from']['username'] ?? '', 50);
$text = sanitize_text($message['text'] ?? '', 1000);

// Additional validation
if (!$chat_id || !$user_id) {
    http_response_code(400);
    exit('Invalid message data');
}

// Обработка команд
if (strpos($text, '/start') === 0) {
    handleStartCommand($chat_id, $first_name);
} elseif (strpos($text, '/connect') === 0) {
    handleConnectCommand($chat_id, $text, $first_name);
} elseif (strpos($text, '/help') === 0) {
    handleHelpCommand($chat_id);
} elseif (strpos($text, '/tasks') === 0) {
    handleTasksCommand($chat_id);
} elseif (trim($text) === 'Задачи') {
    // Кнопка reply "Задачи"
    handleTasksCommand($chat_id);
} elseif (trim($text) === 'Заказы') {
    // Кнопка reply "Заказы"
    $orders_link = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bzkprint.ru') . '/admin/orders';
    $kb = [
        'inline_keyboard' => [
            [['text' => '📦 Открыть заказы', 'url' => $orders_link]]
        ]
    ];
    sendTelegramMessage($chat_id, "📦 Раздел заказов: {$orders_link}", 'HTML', $kb);
} else {
    // Обработка обычных сообщений
    handleGeneralMessage($chat_id, $text, $first_name);
}

http_response_code(200);
exit('OK');

/**
 * Обработка команды /start
 */
function handleStartCommand($chat_id, $first_name)
{
    $message = "👋 Привет, $first_name!\n\n";
    $message .= "Я бот уведомлений BZK PRINT!\n\n";
    $message .= "🔗 Чтобы связать ваш аккаунт с Telegram:\n";
    $message .= "1. Войдите в личный кабинет на сайте\n";
    $message .= "2. Перейдите в настройки Telegram\n";
    $message .= "3. Введите ваш Chat ID: <code>$chat_id</code>\n\n";
    $message .= "📋 Доступные команды:\n";
    $message .= "/connect [email] - быстрое подключение по email\n";
    $message .= "/help - помощь\n\n";
    $message .= "💡 После подключения вы будете получать уведомления о заказах, и рассылках акций и промокодов!";

    sendTelegramMessage($chat_id, $message, 'HTML', buildMainReplyKeyboard());
}

/**
 * Обработка команды /connect с дополнительной безопасностью
 */
function handleConnectCommand($chat_id, $text, $first_name)
{
    global $pdo;

    // Rate limiting for connect attempts
    $rate_limit = check_rate_limit($chat_id, 'telegram_connect', 5, 300);
    if (!$rate_limit['allowed']) {
        sendTelegramMessage($chat_id, "⏳ Слишком много попыток подключения. Попробуйте через 5 минут.", 'HTML', buildMainReplyKeyboard());
        return;
    }
    record_rate_limit_attempt($chat_id, 'telegram_connect');

    // Извлечение email из команды
    $parts = explode(' ', $text, 2);
    if (count($parts) < 2) {
        $message = "❌ Неверный формат команды!\n\n";
        $message .= "Используйте: /connect your@email.ru\n";
        $message .= "Например: /connect bzkprint@yandex.ru";
        sendTelegramMessage($chat_id, $message, 'HTML', buildMainReplyKeyboard());
        return;
    }

    $email = trim($parts[1]);

    // Enhanced email validation
    if (!validate_email($email)) {
        sendTelegramMessage($chat_id, "❌ Неверный формат email адреса!", 'HTML', buildMainReplyKeyboard());
        return;
    }

    try {
        // Поиск пользователя по email с дополнительными проверками
        $stmt = $pdo->prepare("SELECT id, name, telegram_chat_id FROM users WHERE email = ? AND is_blocked = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = "❌ Пользователь с email " . e($email) . " не найден!\n\n";
            $message .= "Убедитесь, что:\n";
            $message .= "• Email указан правильно\n";
            $message .= "• У вас есть аккаунт на сайте\n";
            $message .= "• Аккаунт не заблокирован";
            sendTelegramMessage($chat_id, $message, 'HTML', buildMainReplyKeyboard());
            return;
        }

        // Проверка, не привязан ли аккаунт к другому chat_id
        if (!empty($user['telegram_chat_id']) && $user['telegram_chat_id'] != $chat_id) {
            sendTelegramMessage($chat_id, "⚠️ Этот аккаунт уже привязан к другому Telegram. Обратитесь к администратору.");
            return;
        }

        // Обновление chat_id
        $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$chat_id, $user['id']])) {
            $message = "✅ Отлично, " . e($user['name']) . "!\n\n";
            $message .= "Ваш аккаунт успешно связан с Telegram!\n";
            $message .= "Теперь вы будете получать уведомления:\n\n";
            $message .= "📦 О ваших заказах\n";
            $message .= "📧 Рассылки промокодов и акций\n";
            $message .= "💬 Обновления статусов\n\n";
            $message .= "🔗 Chat ID: <code>" . e($chat_id) . "</code>";

            sendTelegramMessage($chat_id, $message);

            // Отправляем тестовое уведомление
            $test_message = "🎉 Тестовое уведомление!\n\nВаш Telegram успешно подключен к системе BZK PRINT.";
            sendTelegramMessage($chat_id, $test_message, 'HTML', buildMainReplyKeyboard());

            // Log successful connection
            error_log("Telegram account connected: user_id={$user['id']}, chat_id=$chat_id");
        } else {
            // Получаем информацию об ошибке
            $error_info = $stmt->errorInfo();
            error_log("Telegram connect database error: " . print_r($error_info, true));
            sendTelegramMessage($chat_id, "❌ Ошибка при обновлении данных. Попробуйте позже. Код ошибки: " . $error_info[0], 'HTML', buildMainReplyKeyboard());
        }

    } catch (Exception $e) {
        error_log("Telegram connect exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        sendTelegramMessage($chat_id, "❌ Произошла ошибка. Обратитесь к администратору. Детали: " . $e->getMessage(), 'HTML', buildMainReplyKeyboard());
    }
}

/**
 * Обработка команды /help
 */
function handleHelpCommand($chat_id)
{
    $message = "ℹ️ <b>Помощь по боту BZK PRINT</b>\n\n";
    $message .= "📋 <b>Доступные команды:</b>\n";
    $message .= "/start - начать работу с ботом\n";
    $message .= "/connect [email] - связать аккаунт с Telegram\n";
    $message .= "/tasks - мои задачи (для админов/менеджеров)\n";
    $message .= "/help - показать эту справку\n\n";
    $message .= "🔗 <b>Подключение аккаунта:</b>\n";
    $message .= "1. Способ 1: Команда /connect your@email.com\n";
    $message .= "2. Способ 2: Вручную в настройках сайта\n\n";
    $message .= "💡 <b>Что вы будете получать:</b>\n";
    $message .= "• Уведомления о заказах\n";
    $message .= "• Рассылки акций и промокодов\n";
    $message .= "• Обновления статусов\n\n";
    $message .= "❓ Нужна помощь? Обратитесь к администратору сайта.";

    sendTelegramMessage($chat_id, $message, 'HTML', buildMainReplyKeyboard());
}

/**
 * Обработка обычных сообщений
 */
function handleGeneralMessage($chat_id, $text, $first_name)
{
    global $pdo;

    // Проверяем, подключен ли уже этот chat_id
    try {
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE telegram_chat_id = ? AND is_blocked = 0");
        $stmt->execute([$chat_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $message = "👋 Привет, {$user['name']}!\n\n";
            $message .= "Ваш аккаунт уже подключен к системе.\n";
            $message .= "Используйте /help для просмотра доступных команд.";
        } else {
            $message = "💬 Привет, $first_name!\n\n";
            $message .= "Чтобы получать уведомления, подключите ваш аккаунт:\n";
            $message .= "• Используйте команду /connect [ваш_email]\n";
            $message .= "• Или введите Chat ID в настройках сайта: <code>$chat_id</code>\n\n";
            $message .= "Введите /help для получения дополнительной информации.";
        }

        sendTelegramMessage($chat_id, $message, 'HTML', buildMainReplyKeyboard());

    } catch (Exception $e) {
        error_log("Telegram general message error: " . $e->getMessage());
        $message = "Используйте /start для начала работы с ботом.";
        sendTelegramMessage($chat_id, $message, 'HTML', buildMainReplyKeyboard());
    }
}

/**
 * Отправка сообщения в Telegram
 */
function sendTelegramMessage($chat_id, $text, $parse_mode = 'HTML', $reply_markup = null)
{
    $bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

    if (empty($bot_token)) {
        error_log('Telegram bot token not configured');
        return false;
    }

    $url = "https://api.telegram.org/bot$bot_token/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];

    // Добавляем клавиатуру, если она передана
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log('Telegram cURL error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    if ($http_code !== 200) {
        error_log('Telegram API error: ' . $result);
        return false;
    }

    return true;
}

/**
 * Обработка команды /tasks — показывает задачи для админов/менеджеров
 */
function handleTasksCommand($chat_id)
{
    global $pdo;
    $telegram = getTelegramBot();

    // Определяем пользователя по chat_id
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE telegram_chat_id = ? AND is_blocked = 0");
    $stmt->execute([(string) $chat_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !in_array($user['role'], ['admin', 'manager'])) {
        sendTelegramMessage($chat_id, "❌ Команда доступна только администраторам и менеджерам.");
        return;
    }

    // Проверяем, не отключены ли кнопки задач настройками пользователя
    try {
        $stmt = $pdo->prepare("SELECT show_task_buttons FROM notification_prefs WHERE user_id = ?");
        $stmt->execute([(int) $user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int) $row['show_task_buttons'] === 0) {
            sendTelegramMessage($chat_id, "ℹ️ Кнопки задач отключены в ваших настройках уведомлений.");
            return;
        }
    } catch (Exception $e) { /* ignore */
    }

    // По умолчанию — мои задачи
    $telegram->sendTaskList($chat_id, $user['id'], 'my');
}

/**
 * Построение главной reply-клавиатуры бота
 */
function buildMainReplyKeyboard()
{
    return [
        'keyboard' => [
            [
                ['text' => 'Заказы'],
                ['text' => 'Задачи']
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
        'is_persistent' => true
    ];
}