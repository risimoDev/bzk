<?php
// ajax/send_message.php
header('Content-Type: application/json');
session_start();
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/chat_functions.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован.']);
    exit();
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса.']);
    exit();
}

// Получение данных
$order_id = intval($_POST['order_id'] ?? 0);
$message_text = trim($_POST['message_text'] ?? '');

// Валидация
if (!$order_id || !$message_text) {
    echo json_encode(['success' => false, 'message' => 'Не указаны обязательные данные.']);
    exit();
}

if (strlen($message_text) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Сообщение слишком длинное (максимум 1000 символов).']);
    exit();
}

// Получаем информацию о чате
$chat = get_chat_by_order_id($pdo, $order_id);

// Если чат не существует, создаем его
if (!$chat) {
    $chat_id = create_chat_for_order($pdo, $order_id);
    if ($chat_id) {
        $chat = get_chat_by_order_id($pdo, $order_id);
    } else {
        echo json_encode(['success' => false, 'message' => 'Не удалось создать чат.']);
        exit();
    }
}

// Отправляем сообщение
$message_id = send_message($pdo, $chat['id'], $_SESSION['user_id'], $message_text);

if ($message_id) {
    // Помечаем сообщения как прочитанные
    mark_messages_as_read($pdo, $chat['id'], $_SESSION['user_id']);
    
    // Получаем обновленный список сообщений
    $messages = get_chat_messages($pdo, $chat['id']);
    
    // Форматируем сообщения для отправки в JS
    $formatted_messages = [];
    foreach ($messages as $msg) {
        $formatted_messages[] = [
            'id' => $msg['id'],
            'user_id' => $msg['user_id'],
            'is_own' => $msg['user_id'] == $_SESSION['user_id'],
            'sender_name' => $msg['sender_name'],
            'sender_role' => $msg['sender_role'],
            'message' => $msg['message'],
            'created_at' => $msg['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Сообщение отправлено.',
        'messages' => $formatted_messages
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка при отправке сообщения.']);
}

?>