<?php
// /ajax/toggle_urgent_order.php
session_start();
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован.']);
    exit();
}

$is_urgent = $_POST['is_urgent'] ?? '0';
$is_urgent = $is_urgent === '1' ? true : false;

// Сохраняем состояние срочного заказа в сессии
$_SESSION['is_urgent_order'] = $is_urgent;

echo json_encode(['success' => true, 'message' => 'Статус срочного заказа обновлен.']);
?>