<?php
/**
 * Скрипт для настройки Telegram webhook
 * Запустите этот скрипт один раз для настройки webhook
 */

require_once 'vendor/autoload.php';

// Загружаем переменные окружения
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

if (empty($bot_token)) {
    die("❌ Ошибка: TELEGRAM_BOT_TOKEN не настроен в .env файле\n");
}

// URL вашего webhook (замените на ваш домен)
$webhook_url = "https://bzkprint.ru/telegram_webhook.php";

// Для локального тестирования можно использовать ngrok
// $webhook_url = "https://your-ngrok-id.ngrok.io/telegram_webhook.php";

echo "🔧 Настройка Telegram webhook...\n";
echo "Bot token: " . substr($bot_token, 0, 10) . "...\n";
echo "Webhook URL: $webhook_url\n\n";

// Настройка webhook
$api_url = "https://api.telegram.org/bot$bot_token/setWebhook";

$data = [
    'url' => $webhook_url,
    'max_connections' => 40,
    'allowed_updates' => json_encode(['message'])
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    die("❌ cURL ошибка: " . curl_error($ch) . "\n");
}

curl_close($ch);

$response = json_decode($result, true);

if ($http_code === 200 && $response['ok']) {
    echo "✅ Webhook успешно настроен!\n";
    echo "📋 Описание: " . ($response['description'] ?? 'N/A') . "\n\n";
    
    // Получение информации о боте
    $bot_info_url = "https://api.telegram.org/bot$bot_token/getMe";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $bot_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $bot_result = curl_exec($ch);
    curl_close($ch);
    
    $bot_data = json_decode($bot_result, true);
    if ($bot_data['ok']) {
        $bot_username = $bot_data['result']['username'];
        echo "🤖 Бот: @$bot_username\n";
        echo "📱 Ссылка для пользователей: https://t.me/$bot_username\n\n";
    }
    
    echo "📝 Инструкции для пользователей:\n";
    echo "1. Перейти в Telegram бот @$bot_username\n";
    echo "2. Нажать /start\n";
    echo "3. Использовать команду /connect [email] для автоматического подключения\n";
    echo "   Или скопировать Chat ID в настройки профиля на сайте\n\n";
    
    echo "🔧 Дополнительные команды бота:\n";
    echo "/start - начало работы\n";
    echo "/connect [email] - подключение аккаунта\n";
    echo "/help - справка\n\n";
    
} else {
    echo "❌ Ошибка настройки webhook:\n";
    echo "HTTP код: $http_code\n";
    echo "Ответ: $result\n\n";
    
    if ($response && isset($response['description'])) {
        echo "Описание ошибки: " . $response['description'] . "\n";
    }
}

// Проверка текущего webhook
echo "🔍 Проверка текущих настроек webhook...\n";
$webhook_info_url = "https://api.telegram.org/bot$bot_token/getWebhookInfo";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhook_info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$webhook_result = curl_exec($ch);
curl_close($ch);

$webhook_info = json_decode($webhook_result, true);
if ($webhook_info['ok']) {
    $info = $webhook_info['result'];
    echo "📋 Webhook информация:\n";
    echo "URL: " . ($info['url'] ?? 'не установлен') . "\n";
    echo "Статус: " . ($info['has_custom_certificate'] ? 'с сертификатом' : 'без сертификата') . "\n";
    echo "Pending updates: " . ($info['pending_update_count'] ?? 0) . "\n";
    if (isset($info['last_error_date'])) {
        echo "Последняя ошибка: " . date('Y-m-d H:i:s', $info['last_error_date']) . "\n";
        echo "Сообщение ошибки: " . ($info['last_error_message'] ?? 'N/A') . "\n";
    }
}

echo "\n✅ Настройка завершена!\n";
?>