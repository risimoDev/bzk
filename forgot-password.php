<?php
session_start();
include_once __DIR__ . '/includes/header.php';

$pageTitle = "Восстановление пароля";

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // Проверка существования пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Генерация временного токена для сброса пароля
        $token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Сохранение токена в базе данных
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires_at, $user['id']]);

        // Формирование ссылки для сброса пароля
        $reset_link = "http://bzkprintsite/reset-password?token=$token";

        // Отправка письма через PHPMailer
        $mail = new PHPMailer(true);

        try {
            // Настройки SMTP
            $mail->isSMTP();
            $mail->Host = 'mail.bzkprint.ru'; // Замените на ваш SMTP-сервер
            $mail->SMTPAuth = true;
            $mail->Username = 'mailuser'; // Замените на ваш email
            $mail->Password = 'risimo1517'; // Замените на ваш пароль
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Или ENCRYPTION_SMTPS
            $mail->Port = 587; // Или 465 для SSL

            // Отправитель и получатель
            $mail->setFrom('info@bzkprint.ru', 'Типография'); // Замените на ваш email
            $mail->addAddress($email);

            // Содержимое письма
            $mail->isHTML(true);
            $mail->Subject = 'Сброс пароля';
            $mail->Body = "Для сброса пароля перейдите по ссылке: <a href='$reset_link'>$reset_link</a>";

            $mail->send();

            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Инструкции по сбросу пароля отправлены на ваш email.'];
        } catch (Exception $e) {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при отправке письма: ' . $mail->ErrorInfo];
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пользователь с таким email не найден.'];
    }

    header("Location: /forgot-password");
    exit();
}
?>

<main class="container mx-auto px-4 py-8">
  <!-- Вставка breadcrumbs и кнопки "Назад" -->
<div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <!-- Breadcrumbs -->
    <div>
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
    </div>

    <!-- Кнопка "Назад" -->
    <div>
        <?php echo backButton(); ?>
    </div>
</div>
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Восстановление пароля</h1>
  <form action="" method="POST" class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
    <div class="mb-4">
      <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
      <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
    </div>
    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
      Отправить
    </button>
  </form>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>