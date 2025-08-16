<?php
// logout.php
session_start();
include_once __DIR__ . '/includes/db.php'; // Подключение к БД

// --- Добавлено: Удаление токена "Запомнить меня" ---
if (isset($_SESSION['user_id']) && isset($pdo)) {
    // Удаляем токен из БД
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_token_expires_at = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Удаляем куку "Запомнить меня"
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/', '', true, true);
}
// --- Конец добавленного кода ---

// Уничтожаем сессию
session_unset();
session_destroy();

// Перенаправляем на главную
header("Location: /");
exit();
?>