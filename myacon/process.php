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

        // 🔒 Проверка блокировки
        if (!empty($user['is_blocked']) && (int)$user['is_blocked'] === 1) {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Ваш аккаунт заблокирован. Обратитесь в поддержку.'
            ];
            header("Location: /login");
            exit();
        }

        // Устанавливаем сессии
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name']; // Добавляем имя пользователя в сессию
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_authenticated'] = true; // Флаг для простоты проверки

        // --- Обработка "Запомнить меня" ---
        if ($remember_me) {
            $token = bin2hex(random_bytes(32)); // 64 символа
            $token_hash = hash('sha256', $token);
            $expiry = time() + 30 * 24 * 60 * 60; // 30 дней

            $stmt = $pdo->prepare("UPDATE users 
                                   SET remember_token = ?, remember_token_expires_at = FROM_UNIXTIME(?) 
                                   WHERE id = ?");
            $stmt->execute([$token_hash, $expiry, $user['id']]);

            $cookie_value = $user['id'] . ':' . $token;
            $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie(
                'remember_user',
                $cookie_value,
                $expiry,
                '/',
                '',
                $is_https,
                true
            );
        }
        // --- Конец "Запомнить меня" ---

        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Вы успешно вошли!'];

        header("Location: " . ($user['role'] === 'admin' || $user['role'] === 'manager' ? '/admin' : '/client/dashboard'));
        exit();
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный email или пароль.'];
        header("Location: /login");
        exit();
    }
} else {
    header("Location: /login");
    exit();
}
