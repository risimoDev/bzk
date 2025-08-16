<?php
// includes/session_check.php

// Проверка авторизации
function login_with_remember_cookie($pdo) {
    if (!isset($_COOKIE['remember_user'])) {
        return false; // Кука не установлена
    }

    // 1. Разбираем значение куки
    $cookie_data = explode(':', $_COOKIE['remember_user'], 2);
    if (count($cookie_data) !== 2) {
        // Неверный формат куки, удаляем её
        setcookie('remember_user', '', time() - 3600, '/', '', true, true);
        return false;
    }
    
    [$user_id, $token] = $cookie_data;
    $user_id = (int)$user_id;

    // 2. Получаем хеш токена из БД
    $stmt = $pdo->prepare("SELECT id, name, email, role, remember_token, remember_token_expires_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Пользователь не найден, удаляем куку
        setcookie('remember_user', '', time() - 3600, '/', '', true, true);
        return false;
    }

    // 3. Проверяем срок действия токена
    $expires_at = strtotime($user['remember_token_expires_at']);
    if ($expires_at === false || $expires_at < time()) {
        // Токен истёк, удаляем его из БД и куку
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_token_expires_at = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        setcookie('remember_user', '', time() - 3600, '/', '', true, true);
        return false;
    }

    // 4. Сравниваем хеши токенов
    $token_hash = hash('sha256', $token);
    if (!hash_equals($user['remember_token'], $token_hash)) { // hash_equals для защиты от timing attacks
        // Неверный токен, удаляем его из БД и куку
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_token_expires_at = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        setcookie('remember_user', '', time() - 3600, '/', '', true, true);
        return false;
    }

    // 5. Токен действителен! Восстанавливаем сессию
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name']; // Добавляем имя пользователя
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_authenticated'] = true;
    
    return true;
}

// Основная логика проверки авторизации
$is_logged_in = false;
$user_role = 'guest'; // Роль по умолчанию

// 1. Проверяем активную сессию
if (isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'] === true) {
    $is_logged_in = true;
    $user_role = $_SESSION['role'] ?? 'user'; // Получаем роль из сессии
} 
// 2. Если сессии нет, пробуем войти через куку
elseif (isset($pdo) && login_with_remember_cookie($pdo)) { // $pdo должен быть доступен
    $is_logged_in = true;
    $user_role = $_SESSION['role'] ?? 'user'; // Получаем роль из восстановленной сессии
}
?>