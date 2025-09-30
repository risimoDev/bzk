<?php
/**
 * Script to set up Telegram webhook
 */

// Get the bot token from .env file
require_once 'includes/db.php';

$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
if (empty($bot_token)) {
    die("❌ Telegram bot token is not configured in .env file\n");
}

// Get the webhook URL (adjust this to your actual domain)
// For local development, you might need to use a service like ngrok
$webhook_url = 'https://bzkprint.ru/telegram_webhook.php'; // Change this to your actual domain
// For local testing with ngrok:
// $webhook_url = 'https://your-ngrok-url.ngrok.io/telegram_webhook.php';

echo "=== Telegram Webhook Setup ===\n";
echo "Bot token: " . substr($bot_token, 0, 5) . "..." . substr($bot_token, -5) . "\n";
echo "Webhook URL: $webhook_url\n";

// Set up the webhook
$api_url = "https://api.telegram.org/bot$bot_token/setWebhook";

$data = [
    'url' => $webhook_url,
    'allowed_updates' => ['message', 'callback_query']
];

// Add secret token if configured
$secret_token = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
if ($secret_token) {
    $data['secret_token'] = $secret_token;
    echo "Using secret token for webhook verification\n";
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "❌ cURL error: " . curl_error($ch) . "\n";
    curl_close($ch);
    exit(1);
}

curl_close($ch);

if ($http_code !== 200) {
    echo "❌ Telegram API error (HTTP $http_code): $result\n";
    exit(1);
}

$response = json_decode($result, true);
if ($response['ok']) {
    echo "✅ Webhook set successfully!\n";
    echo "Webhook info: " . json_encode($response['result'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Failed to set webhook: " . $response['description'] . "\n";
    exit(1);
}

// Also get webhook info to verify
echo "\n--- Current Webhook Info ---\n";
$info_url = "https://api.telegram.org/bot$bot_token/getWebhookInfo";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "❌ cURL error getting webhook info: " . curl_error($ch) . "\n";
    curl_close($ch);
} else {
    curl_close($ch);
    
    if ($http_code === 200) {
        $response = json_decode($result, true);
        if ($response['ok']) {
            echo "Webhook URL: " . ($response['result']['url'] ?? 'Not set') . "\n";
            echo "Has custom certificate: " . ($response['result']['has_custom_certificate'] ? 'Yes' : 'No') . "\n";
            echo "Pending update count: " . ($response['result']['pending_update_count'] ?? 0) . "\n";
            if (!empty($response['result']['last_error_date'])) {
                echo "Last error date: " . date('Y-m-d H:i:s', $response['result']['last_error_date']) . "\n";
                echo "Last error message: " . ($response['result']['last_error_message'] ?? 'None') . "\n";
            }
            if (!empty($response['result']['last_synchronization_error_date'])) {
                echo "Last sync error date: " . date('Y-m-d H:i:s', $response['result']['last_synchronization_error_date']) . "\n";
            }
        } else {
            echo "❌ Error getting webhook info: " . $response['description'] . "\n";
        }
    } else {
        echo "❌ HTTP error getting webhook info: $http_code\n";
    }
}

echo "\n🎉 Webhook setup completed!\n";
?>