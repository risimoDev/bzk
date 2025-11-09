<?php
session_start();
$pageTitle = "Процесс отправки";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/telegram.php';
require_once '../../includes/security.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php'; // composer autoload

$message_id = intval($_GET['id'] ?? 0);

if (!$message_id) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный ID рассылки.'];
    header("Location: /admin/messaging/");
    exit();
}

// Получение информации о рассылке
$stmt = $pdo->prepare("SELECT * FROM mass_messages WHERE id = ?");
$stmt->execute([$message_id]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Рассылка не найдена.'];
    header("Location: /admin/messaging/");
    exit();
}

// Проверка статуса рассылки
if (($message['status'] ?? '') !== 'sending') {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Рассылка не находится в процессе отправки.'];
    header("Location: /admin/messaging/details.php?id=$message_id");
    exit();
}

$batch_size = 5;
$emails_sent = 0;
$telegrams_sent = 0;
$errors = [];

try {
    // Получаем список ожидающих отправки
    $stmt = $pdo->prepare("
        SELECT mmr.*, u.name, u.email, u.telegram_chat_id 
        FROM mass_message_recipients mmr
        LEFT JOIN users u ON mmr.user_id = u.id
        WHERE mmr.mass_message_id = ? 
        AND (mmr.email_status = 'pending' OR mmr.telegram_status = 'pending')
        LIMIT ?
    ");
    $stmt->execute([$message_id, $batch_size]);
    $pending_recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pending_recipients)) {
        // Завершаем рассылку
        $stmt = $pdo->prepare("UPDATE mass_messages SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $stmt->execute([$message_id]);

        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Рассылка успешно завершена!'];
        header("Location: /admin/messaging/details.php?id=$message_id");
        exit();
    }

    // --- Общая настройка PHPMailer (один экземпляр для батча) ---
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? ($_ENV['MAIL_HOST'] ?? 'localhost');
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USERNAME'] ?? '';
    $mail->Password = $_ENV['SMTP_PASSWORD'] ?? '';
    // порт и шифрование — если у вас 465 используйте ENCRYPTION_SMTPS, если 587 — STARTTLS
    $smtp_port = $_ENV['SMTP_PORT'] ?? 587;
    $mail->SMTPSecure = ($smtp_port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)$smtp_port;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'example.com'), $_ENV['SMTP_FROM_NAME'] ?? 'BZK Print');
    $mail->isHTML(true);
    // --- конец настройки PHPMailer ---

    // Инициализация Telegram бота (твоя функция)
    $telegram = getTelegramBot();

    foreach ($pending_recipients as $recipient) {
        if (!is_array($recipient)) continue;

        $recipient_id = $recipient['id'];
        $user_name = $recipient['name'] ?? 'Пользователь';
        $user_email = trim($recipient['email'] ?? '');
        $telegram_chat_id = trim($recipient['telegram_chat_id'] ?? '');

        // --------- EMAIL ---------
        if (($recipient['email_status'] ?? '') === 'pending' && $user_email !== '') {

            // Валидация email, если невалидный — помечаем и пропускаем
            if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Некорректный email у получателя ID {$recipient_id}: {$user_email}";
                $stmt = $pdo->prepare("UPDATE mass_message_recipients SET email_status = 'invalid' WHERE id = ?");
                $stmt->execute([$recipient_id]);
            } else {
                try {
                    $mail->clearAddresses();
                    $mail->addAddress($user_email, $user_name);
                    $mail->Subject = $message['title'] ?? 'Уведомление от BZK Print';

                    // Формируем тело письма
                    $html_content = "
                    <html><head><meta charset='UTF-8'></head>
                    <body style='font-family: Arial, sans-serif; line-height:1.6; color:#333;'>
                        <div style='max-width:600px;margin:0 auto;padding:20px;'>
                            <div style='background:linear-gradient(135deg,#118568 0%,#17B890 100%);color:#fff;padding:20px;border-radius:10px 10px 0 0;'>
                                <h1 style='margin:0;font-size:20px;'>" . htmlspecialchars($message['title'] ?? '') . "</h1>
                            </div>
                            <div style='background:#f9f9f9;padding:25px;border-radius:0 0 10px 10px;border:1px solid #ddd;border-top:none;'>
                                <p>Здравствуйте, " . htmlspecialchars($user_name) . "!</p>
                                <div>" . nl2br(htmlspecialchars($message['content'] ?? '')) . "</div>
                                <hr style='margin:20px 0;border:none;border-top:1px solid #ddd;'>
                                <p style='font-size:12px;color:#666;'>С уважением,<br>Команда BZK Print<br>
                                    <a href='https://" . ($_SERVER['HTTP_HOST'] ?? '') . "' style='color:#118568;'>Перейти на сайт</a></p>
                            </div>
                        </div>
                    </body></html>";

                    $mail->Body = $html_content;
                    $mail->AltBody = strip_tags($message['content'] ?? '');

                    if ($mail->send()) {
                        $stmt = $pdo->prepare("UPDATE mass_message_recipients SET email_status = 'sent', sent_at = NOW() WHERE id = ?");
                        $stmt->execute([$recipient_id]);
                        $emails_sent++;
                    } else {
                        // теоретически PHPMailer бросает Exception, но оставим защиту
                        $stmt = $pdo->prepare("UPDATE mass_message_recipients SET email_status = 'failed' WHERE id = ?");
                        $stmt->execute([$recipient_id]);
                        $errors[] = "Не удалось отправить email для {$user_name} ({$user_email})";
                    }

                } catch (Exception $e) {
                    $stmt = $pdo->prepare("UPDATE mass_message_recipients SET email_status = 'failed' WHERE id = ?");
                    $stmt->execute([$recipient_id]);
                    $errors[] = "Email для {$user_name} ({$user_email}): " . $e->getMessage();
                }
            }
        }

        // --------- TELEGRAM ---------
        if (($recipient['telegram_status'] ?? '') === 'pending' && $telegram_chat_id !== '') {
            try {
                $telegram_message = "📬 <b>" . htmlspecialchars($message['title'] ?? '') . "</b>\n\n" .
                    htmlspecialchars($message['content'] ?? '') .
                    "\n\n🌐 <a href='https://" . ($_SERVER['HTTP_HOST'] ?? '') . "'>Перейти на сайт</a>";

                if ($telegram->sendMessage($telegram_chat_id, $telegram_message)) {
                    $stmt = $pdo->prepare("UPDATE mass_message_recipients SET telegram_status = 'sent', sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$recipient_id]);
                    $telegrams_sent++;
                } else {
                    $stmt = $pdo->prepare("UPDATE mass_message_recipients SET telegram_status = 'failed' WHERE id = ?");
                    $stmt->execute([$recipient_id]);
                    $errors[] = "Telegram: не удалось отправить сообщение пользователю {$user_name} (chat_id={$telegram_chat_id})";
                }
            } catch (Exception $e) {
                $stmt = $pdo->prepare("UPDATE mass_message_recipients SET telegram_status = 'failed' WHERE id = ?");
                $stmt->execute([$recipient_id]);
                $errors[] = "Telegram для {$user_name}: " . $e->getMessage();
            }
        }
    }

    // Обновляем общие счётчики в таблице mass_messages
    $stmt = $pdo->prepare("
        UPDATE mass_messages
        SET emails_sent = emails_sent + ?, telegrams_sent = telegrams_sent + ?
        WHERE id = ?
    ");
    $stmt->execute([$emails_sent, $telegrams_sent, $message_id]);

} catch (Exception $e) {
    $errors[] = "Критическая ошибка: " . $e->getMessage();
}

// Получение статистики оставшихся
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pending,
        SUM(CASE WHEN email_status = 'sent' THEN 1 ELSE 0 END) as emails_sent,
        SUM(CASE WHEN telegram_status = 'sent' THEN 1 ELSE 0 END) as telegrams_sent
    FROM mass_message_recipients
    WHERE mass_message_id = ?
    AND (email_status = 'pending' OR telegram_status = 'pending')
");
$stmt->execute([$message_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$still_pending = $stats['total_pending'] ?? 0;

?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
    <div class="container mx-auto px-6 max-w-4xl">

        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Отправка рассылки</h1>
            <p class="text-lg text-gray-700"><?= htmlspecialchars($message['title'] ?? 'Неизвестная рассылка'); ?></p>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <div class="bg-white rounded-3xl shadow-xl p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Результаты отправки батча</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="text-center p-6 bg-green-50 rounded-lg border border-green-200">
                    <div class="text-3xl font-bold text-green-600"><?= $emails_sent; ?></div>
                    <div class="text-green-700">📧 Email отправлено</div>
                </div>

                <div class="text-center p-6 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="text-3xl font-bold text-blue-600"><?= $telegrams_sent; ?></div>
                    <div class="text-blue-700">📱 Telegram отправлено</div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <h3 class="font-bold text-red-800 mb-2">❌ Ошибки:</h3>
                    <ul class="list-disc list-inside text-red-700 text-sm">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Прогресс -->
            <div class="mb-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Прогресс отправки</span>
                    <span><?= $still_pending > 0 ? 'В процессе...' : 'Завершено!'; ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <?php
                    $total_recipients = $message['total_recipients'] ?? 1;
                    $progress = $total_recipients > 0 ? (($total_recipients - $still_pending) / $total_recipients) * 100 : 100;
                    ?>
                    <div class="bg-gradient-to-r from-[#118568] to-[#17B890] h-3 rounded-full transition-all duration-300"
                         style="width: <?= round($progress, 1); ?>%"></div>
                </div>
                <div class="text-sm text-gray-600 mt-1">
                    <?= round($progress, 1); ?>% завершено (осталось: <?= $still_pending; ?> из <?= $total_recipients; ?>)
                </div>
            </div>

            <?php if ($still_pending > 0): ?>
                <div class="flex gap-4 justify-center">
                    <a href="?id=<?= $message_id; ?>" class="px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a]">🔄 Продолжить</a>
                    <a href="/admin/messaging/details.php?id=<?= $message_id; ?>" class="px-6 py-3 bg-gray-200 rounded-lg">📊 Детали</a>
                </div>
                <script>setTimeout(()=>{ window.location.href='?id=<?=$message_id;?>'; }, 3000);</script>
            <?php else: ?>
                <div class="flex gap-4 justify-center">
                    <a href="/admin/messaging/details.php?id=<?= $message_id; ?>" class="px-6 py-3 bg-[#118568] text-white rounded-lg">📊 Детали</a>
                    <a href="/admin/messaging/" class="px-6 py-3 bg-gray-200 rounded-lg">← К списку</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>
