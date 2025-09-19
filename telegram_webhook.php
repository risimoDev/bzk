<?php
/**
 * Telegram Webhook для автоматического получения chat_id пользователей
 * Этот файл нужно настроить как webhook для Telegram бота
 */

// Защита от прямого доступа
if (!isset($_ENV['TELEGRAM_BOT_TOKEN'])) {
    require_once 'vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

require_once 'includes/db.php';

// Получение данных от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Логирование для отладки (можно удалить в продакшене)
error_log("Telegram webhook received: " . $input);

if (!$update || !isset($update['message'])) {
    http_response_code(200);
    exit('OK');
}

$message = $update['message'];
$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$first_name = $message['from']['first_name'] ?? '';
$last_name = $message['from']['last_name'] ?? '';
$username = $message['from']['username'] ?? '';
$text = $message['text'] ?? '';

// Обработка команд
if (strpos($text, '/start') === 0) {
    handleStartCommand($chat_id, $first_name);
} elseif (strpos($text, '/connect') === 0) {
    handleConnectCommand($chat_id, $text, $first_name);
} elseif (strpos($text, '/help') === 0) {
    handleHelpCommand($chat_id);
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

    sendTelegramMessage($chat_id, $message);
}

/**
 * Обработка команды /connect
 */
function handleConnectCommand($chat_id, $text, $first_name)
{
    global $pdo;

    // Извлечение email из команды
    $parts = explode(' ', $text, 2);
    if (count($parts) < 2) {
        $message = "❌ Неверный формат команды!\n\n";
        $message .= "Используйте: /connect your@email.ru\n";
        $message .= "Например: /connect bzkprint@yandex.ru";
        sendTelegramMessage($chat_id, $message);
        return;
    }

    $email = trim($parts[1]);

    // Валидация email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendTelegramMessage($chat_id, "❌ Неверный формат email адреса!");
        return;
    }

    try {
        // Поиск пользователя по email
        $stmt = $pdo->prepare("SELECT id, name, telegram_chat_id FROM users WHERE email = ? AND is_blocked = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = "❌ Пользователь с email $email не найден!\n\n";
            $message .= "Убедитесь, что:\n";
            $message .= "• Email указан правильно\n";
            $message .= "• У вас есть аккаунт на сайте\n";
            $message .= "• Аккаунт не заблокирован";
            sendTelegramMessage($chat_id, $message);
            return;
        }

        // Обновление chat_id
        $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?");
        if ($stmt->execute([$chat_id, $user['id']])) {
            $message = "✅ Отлично, {$user['name']}!\n\n";
            $message .= "Ваш аккаунт успешно связан с Telegram!\n";
            $message .= "Теперь вы будете получать уведомления:\n\n";
            $message .= "📦 О ваших заказах\n";
            $message .= "📧 Рассылки промокодов и акций\n";
            $message .= "💬 Обновления статусов\n\n";
            $message .= "🔗 Chat ID: <code>$chat_id</code>";

            sendTelegramMessage($chat_id, $message);

            // Отправляем тестовое уведомление
            $test_message = "🎉 Тестовое уведомление!\n\nВаш Telegram успешно подключен к системе BZK PRINT.";
            sendTelegramMessage($chat_id, $test_message);
        } else {
            sendTelegramMessage($chat_id, "❌ Ошибка при обновлении данных. Попробуйте позже.");
        }

    } catch (Exception $e) {
        error_log("Telegram connect error: " . $e->getMessage());
        sendTelegramMessage($chat_id, "❌ Произошла ошибка. Обратитесь к администратору.");
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
    $message .= "/help - показать эту справку\n\n";
    $message .= "🔗 <b>Подключение аккаунта:</b>\n";
    $message .= "1. Способ 1: Команда /connect your@email.com\n";
    $message .= "2. Способ 2: Вручную в настройках сайта\n\n";
    $message .= "💡 <b>Что вы будете получать:</b>\n";
    $message .= "• Уведомления о заказах\n";
    $message .= "• Рассылки акций и промокодов\n";
    $message .= "• Обновления статусов\n\n";
    $message .= "❓ Нужна помощь? Обратитесь к администратору сайта.";

    sendTelegramMessage($chat_id, $message);
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

        sendTelegramMessage($chat_id, $message);

    } catch (Exception $e) {
        error_log("Telegram general message error: " . $e->getMessage());
        $message = "Используйте /start для начала работы с ботом.";
        sendTelegramMessage($chat_id, $message);
    }
}

/**
 * Отправка сообщения в Telegram
 */
function sendTelegramMessage($chat_id, $text, $parse_mode = 'HTML')
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
?>