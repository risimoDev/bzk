<?php
/**
 * Функции уведомлений для мини-склада
 */

require_once 'telegram.php';

/**
 * Отправка уведомления о добавлении товара в мини-склад
 */
function sendMiniWarehouseItemAddedNotification($user_id, $item_data) {
    global $pdo;
    
    // Получаем данные пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['mini_warehouse_enabled']) {
        return false;
    }
    
    // Формируем сообщение
    $message = "📥 <b>Новый товар добавлен в мини-склад!</b>\n\n";
    $message .= "👤 <b>Клиент:</b> " . htmlspecialchars($user['name']) . "\n";
    $message .= "📦 <b>Товар:</b> " . htmlspecialchars($item_data['name']) . "\n";
    $message .= "🔢 <b>Количество:</b> " . $item_data['quantity'] . " шт.\n";
    
    if (!empty($item_data['description'])) {
        $message .= "📝 <b>Описание:</b> " . htmlspecialchars($item_data['description']) . "\n";
    }
    
    $message .= "\n🆔 <b>ID клиента:</b> #{$user_id}\n";
    $message .= "🌐 <a href='https://{$_SERVER['HTTP_HOST']}/admin/client_card.php?id={$user_id}'>Перейти в карточку клиента</a>";
    
    // Отправляем уведомление в Telegram, если включены уведомления
    if ($user['telegram_notifications'] && !empty($user['telegram_chat_id'])) {
        $telegram = new TelegramBot();
        $telegram->sendToGroupAndUser($user['telegram_chat_id'], $message);
    }
    
    // Здесь можно добавить логику для других типов уведомлений (email, SMS и т.д.)
    
    return true;
}

/**
 * Отправка уведомления об удалении товара из мини-склада
 */
function sendMiniWarehouseItemRemovedNotification($user_id, $item_data) {
    global $pdo;
    
    // Получаем данные пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['mini_warehouse_enabled']) {
        return false;
    }
    
    // Формируем сообщение
    $message = "🗑️ <b>Товар удален из мини-склада</b>\n\n";
    $message .= "👤 <b>Клиент:</b> " . htmlspecialchars($user['name']) . "\n";
    $message .= "📦 <b>Товар:</b> " . htmlspecialchars($item_data['name']) . "\n";
    $message .= "🔢 <b>Количество:</b> " . $item_data['quantity'] . " шт.\n";
    
    $message .= "\n🆔 <b>ID клиента:</b> #{$user_id}\n";
    $message .= "🌐 <a href='https://{$_SERVER['HTTP_HOST']}/admin/client_card.php?id={$user_id}'>Перейти в карточку клиента</a>";
    
    // Отправляем уведомление в Telegram, если включены уведомления
    if ($user['telegram_notifications'] && !empty($user['telegram_chat_id'])) {
        $telegram = new TelegramBot();
        $telegram->sendToGroupAndUser($user['telegram_chat_id'], $message);
    }
    
    return true;
}
?>