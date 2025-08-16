<?php
// login/process.php (или аналогичный обработчик)
session_start();
include_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    // Проверяем, установлен ли чекбокс "Запомнить меня"
    $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';

    // Проверка данных пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name']; // Добавляем имя пользователя в сессию
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_authenticated'] = true; // Флаг для простоты проверки

        // --- Добавлено: Обработка "Запомнить меня" ---
        if ($remember_me) {
            // 1. Генерируем уникальный токен
            $token = bin2hex(random_bytes(32)); // 64 символа
            
            // 2. Хешируем токен для хранения в БД (безопасность)
            $token_hash = hash('sha256', $token);
            
            // 3. Устанавливаем срок действия (например, 30 дней)
            $expiry = time() + 30 * 24 * 60 * 60; // 30 дней в секундах
            
            // 4. Сохраняем хеш токена в БД, связанного с пользователем
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_token_expires_at = FROM_UNIXTIME(?) WHERE id = ?");
            $stmt->execute([$token_hash, $expiry, $user['id']]);
            
            // 5. Устанавливаем куку в браузере пользователя
            $cookie_value = $user['id'] . ':' . $token;
            // Определяем, использует ли сайт HTTPS
            $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie(
                'remember_user',
                $cookie_value,
                $expiry,        // Время истечения
                '/',            // Путь
                '',             // Домен (пустой для текущего)
                $is_https,      // Secure
                true            // HttpOnly
            );
        }
        // --- Конец добавленного кода ---
        
        // Добавляем уведомление
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Вы успешно вошли!'];

        header("Location: " . ($user['role'] === 'admin' || $user['role'] === 'manager' ? '/admin' : '/client/dashboard'));
        exit();
    } else {
        // Добавляем уведомление об ошибке
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный email или пароль.'];
        header("Location: /login");
        exit();
    }
} else {
    // Неправильный метод запроса
    header("Location: /login");
    exit();
}
?>