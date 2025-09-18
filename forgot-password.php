<?php
session_start();

$pageTitle = "Восстановление пароля";

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

// Проверка на спам (лимит попыток)
$ip_address = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "password_reset_attempts_" . $ip_address;
$max_attempts = 5;
$time_window = 3600; // 1 час

// Функция для проверки лимита попыток
function checkRateLimit($pdo, $ip_address, $max_attempts, $time_window) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM password_reset_attempts 
        WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$ip_address, $time_window]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['attempts'] < $max_attempts;
}

// Функция для записи попытки сброса
function logResetAttempt($pdo, $ip_address, $email) {
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_attempts (ip_address, email, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$ip_address, $email]);
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Валидация email
    if (empty($email)) {
        $error_message = 'Пожалуйста, введите email адрес.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Пожалуйста, введите корректный email адрес.';
    } elseif (!checkRateLimit($pdo, $ip_address, $max_attempts, $time_window)) {
        $error_message = 'Слишком много попыток. Попробуйте позже.';
    } else {
        // Запись попытки
        logResetAttempt($pdo, $ip_address, $email);
        
        // Проверка существования пользователя
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_blocked = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Проверка на существующий активный токен
            if ($user['reset_token'] && $user['reset_token_expires'] > date('Y-m-d H:i:s')) {
                $success_message = 'Инструкции по сбросу пароля уже отправлены на ваш email. Проверьте почту.';
            } else {
                // Генерация нового токена
                $token = bin2hex(random_bytes(32)); // Увеличили длину токена
                $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours')); // Увеличили время до 2 часов

                // Сохранение токена в базе данных
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires_at, $user['id']]);

                // Формирование ссылки для сброса пароля
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $reset_link = "{$protocol}://{$host}/reset-password?token={$token}";

                // Отправка письма через PHPMailer
                $mail = new PHPMailer(true);

                try {
                    // Настройки SMTP
                    $mail->isSMTP();
                    $mail->Host = 'mail.bzkprint.ru';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'mailuser';
                    $mail->Password = 'risimo1517';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';

                    // Отправитель и получатель
                    $mail->setFrom('info@bzkprint.ru', 'Типография BZK Print');
                    $mail->addAddress($email, $user['name']);

                    // Содержимое письма
                    $mail->isHTML(true);
                    $mail->Subject = 'Восстановление пароля - BZK Print';
                    $mail->Body = "
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <title>Восстановление пароля</title>
                        </head>
                        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <h1 style='color: #118568;'>BZK Print</h1>
                                    <h2 style='color: #666;'>Восстановление пароля</h2>
                                </div>
                                
                                <p>Здравствуйте, <strong>{$user['name']}</strong>!</p>
                                
                                <p>Мы получили запрос на восстановление пароля для вашей учетной записи.</p>
                                
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='{$reset_link}' 
                                       style='display: inline-block; padding: 15px 30px; background-color: #118568; 
                                              color: white; text-decoration: none; border-radius: 8px; 
                                              font-weight: bold;'>Восстановить пароль</a>
                                </div>
                                
                                <p><strong>Важно:</strong></p>
                                <ul>
                                    <li>Ссылка действительна в течение 2 часов</li>
                                    <li>Если вы не запрашивали восстановление пароля, проигнорируйте это письмо</li>
                                    <li>Для безопасности никому не передавайте эту ссылку</li>
                                </ul>
                                
                                <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px;'>
                                    Если кнопка не работает, скопируйте и вставьте эту ссылку в браузер:<br>
                                    <a href='{$reset_link}' style='color: #118568;'>{$reset_link}</a>
                                </p>
                                
                                <div style='text-align: center; margin-top: 30px; color: #999; font-size: 12px;'>
                                    © 2024 BZK Print. Все права защищены.
                                </div>
                            </div>
                        </body>
                        </html>
                    ";

                    $mail->send();
                    $success_message = 'Инструкции по сбросу пароля отправлены на ваш email. Проверьте почту.';
                    
                } catch (Exception $e) {
                    error_log('Password reset email error: ' . $mail->ErrorInfo);
                    $error_message = 'Ошибка при отправке письма. Попробуйте позже или обратитесь к администратору.';
                }
            }
        } else {
            // Для безопасности показываем тот же успешный сообщение даже если пользователь не найден
            $success_message = 'Если указанный email существует в нашей системе, инструкции по сбросу пароля будут отправлены.';
        }
    }
}
?>
<?php include_once __DIR__ . '/includes/header.php';?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
  <div class="container mx-auto px-6 max-w-md">
    
    <!-- Заголовок -->
    <div class="text-center mb-8">
      <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Восстановление пароля</h1>
      <p class="text-lg text-gray-700">Введите ваш email адрес</p>
      <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php if ($error_message): ?>
      <div class="mb-6 p-4 rounded-xl bg-red-100 border border-red-400 text-red-700">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
      <div class="mb-6 p-4 rounded-xl bg-green-100 border border-green-400 text-green-700">
        <?php echo htmlspecialchars($success_message); ?>
      </div>
    <?php endif; ?>

    <!-- Форма -->
    <div class="bg-white rounded-3xl shadow-xl p-8">
      <form method="POST" class="space-y-6">
        <div>
          <label for="email" class="block text-gray-700 font-medium mb-2">Email адрес</label>
          <input type="email" 
                 id="email" 
                 name="email" 
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                 class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition"
                 placeholder="Ваш email"
                 required>
        </div>
        
        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:scale-105 transition font-bold text-lg shadow">
          Отправить инструкции
        </button>
      </form>
      
      <div class="mt-6 text-center">
        <p class="text-gray-600">Вспомнили пароль?</p>
        <a href="/login" class="text-[#118568] hover:text-[#0f755a] font-medium">Войти в аккаунт</a>
      </div>
    </div>
    
    <!-- Информация -->
    <div class="mt-8 bg-blue-50 rounded-2xl p-6">
      <h3 class="text-lg font-bold text-blue-800 mb-3">Как это работает?</h3>
      <ul class="text-blue-700 space-y-2">
        <li class="flex items-start">
          <span class="text-blue-500 mr-2">•</span>
          Мы отправим ссылку для сброса пароля на ваш email
        </li>
        <li class="flex items-start">
          <span class="text-blue-500 mr-2">•</span>
          Перейдите по ссылке в письме
        </li>
        <li class="flex items-start">
          <span class="text-blue-500 mr-2">•</span>
          Установите новый пароль
        </li>
      </ul>
    </div>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>