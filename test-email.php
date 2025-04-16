<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . './PHPMailer/src/Exception.php';
require __DIR__ . './PHPMailer/src/PHPMailer.php';
require __DIR__ . './PHPMailer/src/SMTP.php';

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
    $mail->setFrom('info@bzkprint.ru', 'BZK PRINT');
    $mail->addAddress('risimo2014@yandex.ru'); // Замените на адрес получателя

    // Содержимое письма
    $mail->isHTML(true);
    $mail->Subject = 'Тестовое письмо';
    $mail->Body = 'Это тестовое письмо, отправленное через PHPMailer.';

    $mail->send();
    echo 'Письмо успешно отправлено!';
} catch (Exception $e) {
    echo "Ошибка при отправке письма: {$mail->ErrorInfo}";
}