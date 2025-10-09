<?php
// test_email.php — тест отправки email через PHPMailer

// Загружаем автозагрузчик Composer (если используется)
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Загружаем .env (если используется)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Настройки из .env или по умолчанию
$smtp_host     = $_ENV['SMTP_HOST'] ?? 'smtp.yandex.ru';
$smtp_port     = $_ENV['SMTP_PORT'] ?? 587;
$smtp_username = $_ENV['SMTP_USERNAME'] ?? 'risimo2014@yandex.ru'; // ваш ящик
$smtp_password = $_ENV['SMTP_PASSWORD'] ?? ''; // ← ОБЯЗАТЕЛЬНО укажите в .env!
$from_email    = $_ENV['SMTP_FROM_EMAIL'] ?? 'risimo2014@yandex.ru';
$from_name     = $_ENV['SMTP_FROM_NAME'] ?? 'BZK Print — Тест';

$to_email = 'risimo2014@yandex.ru';
$to_name  = 'Тестовый получатель';

$mail = new PHPMailer(true);

try {
    // Настройки SMTP
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_username;
    $mail->Password   = $smtp_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtp_port;
    $mail->CharSet    = 'UTF-8';

    // Отправитель и получатель
    $mail->setFrom($from_email, $from_name);
    $mail->addAddress($to_email, $to_name);

    // Содержимое письма
    $mail->isHTML(true);
    $mail->Subject = '✅ Тестовое письмо от BZK Print';
    $mail->Body    = "
        <h2>Тест отправки email</h2>
        <p>Если вы видите это письмо — значит, PHPMailer настроен корректно!</p>
        <p><strong>Дата отправки:</strong> " . date('d.m.Y H:i:s') . "</p>
        <hr>
        <p style='font-size: 12px; color: #666;'>
            Это письмо отправлено автоматически для тестирования SMTP-настроек.
        </p>
    ";
    $mail->AltBody = "Тестовое письмо от BZK Print. Дата: " . date('d.m.Y H:i:s');

    // Отправка
    $mail->send();
    echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #e6f7ee; border: 1px solid #4caf50; border-radius: 8px; color: #2e7d32;'>";
    echo "<h2>✅ Успех!</h2>";
    echo "<p>Письмо успешно отправлено на <strong>{$to_email}</strong>.</p>";
    echo "<p>Проверьте почтовый ящик (включая папку «Спам»).</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #ffebee; border: 1px solid #f44336; border-radius: 8px; color: #c62828;'>";
    echo "<h2>❌ Ошибка отправки</h2>";
    echo "<p><strong>Сообщение:</strong> " . htmlspecialchars($mail->ErrorInfo) . "</p>";
    echo "<p><strong>Исключение:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Проверьте:</p>";
    echo "<ul>";
    echo "<li>Правильность SMTP-данных в <code>.env</code></li>";
    echo "<li>Разрешена ли отправка через SMTP в настройках Яндекса</li>";
    echo "<li>Включена ли «Пароль для внешних приложений» в Яндекс.Почте</li>";
    echo "</ul>";
    echo "</div>";
}
?>