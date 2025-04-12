<?php
session_start();

// Очищаем сессию
session_destroy();

// Добавляем уведомление
$_SESSION['notifications'][] = ['type' => 'info', 'message' => 'Вы успешно вышли!'];

header("Location: /");
exit();
?>