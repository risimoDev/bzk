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
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // если через Composer

$message_id = intval($_GET['id'] ?? 0);

if (!$message_id) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный ID рассылки.'];
    header("Location: /admin/messaging/");
    exit();
}

// Получение информации о рассылке
$stmt = $pdo->prepare("
    SELECT mm.* 
    FROM mass_messages mm 
    WHERE mm.id = ?
");
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

// Обработка отправки (эмуляция батчевой обработки)
$batch_size = 5; // Отправляем по 5 сообщений за раз
$emails_sent = 0;
$telegrams_sent = 0;
$errors = [];

try {
    // Получение получателей для отправки
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
        // Все сообщения отправлены, завершаем процесс
        $stmt = $pdo->prepare("UPDATE mass_messages SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $stmt->execute([$message_id]);

        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Рассылка успешно завершена!'];
        header("Location: /admin/messaging/details.php?id=$message_id");
        exit();
    }

    // Настройка PHPMailer
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'localhost';
    $mail->SMTPAuth = true;
    $mail->Username = 'mailer@bzkprint.ru';
    $mail->Password = 'jezGFC3tHLhIajpZYYSq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('mailer@bzkprint.ru', 'BZK Print');

    // Инициализация Telegram бота
    $telegram = getTelegramBot();

    foreach ($pending_recipients as $recipient) {
        if (!is_array($recipient))
            continue;

        $recipient_id = $recipient['id'];
        $user_name = $recipient['name'] ?? 'Пользователь';
        $user_email = $recipient['email'] ?? '';
        $telegram_chat_id = $recipient['telegram_chat_id'] ?? '';

        // Отправка Email
        if ($recipient['email_status'] === 'pending' && !empty($user_email)) {
            try {
                $mail->clearAddresses();
                $mail->addAddress($user_email, $user_name);
                $mail->Subject = $message['title'] ?? 'Уведомление от BZK Print';

                // HTML версия письма
                $html_content = "
                <html>
                <head>
                    <meta charset='UTF-8'>
                </head>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #118568 0%, #17B890 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0;'>
                            <h1 style='margin: 0; font-size: 24px;'>" . htmlspecialchars($message['title'] ?? '') . "</h1>
                        </div>
                        <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #ddd; border-top: none;'>
                            <p style='margin-top: 0;'>Здравствуйте, " . htmlspecialchars($user_name) . "!</p>
                            <div style='margin: 20px 0;'>
                                " . nl2br(htmlspecialchars($message['content'] ?? '')) . "
                            </div>
                            <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                            <p style='font-size: 12px; color: #666; margin-bottom: 0;'>
                                С уважением,<br>
                                Команда BZK Print<br>
                                <a href='https://" . $_SERVER['HTTP_HOST'] . "' style='color: #118568;'>Перейти на сайт</a>
                            </p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $mail->isHTML(true);
                $mail->Body = $html_content;
                $mail->AltBody = strip_tags($message['content'] ?? '');

                if ($mail->send()) {
                    $stmt = $pdo->prepare("UPDATE mass_message_recipients SET email_status = 'sent', sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$recipient_id]);
                    $emails_sent++;
                } else {
                    throw new Exception('Ошибка отправки email');
                }

            } catch (Exception $e) {
                $stmt = $pdo->prepare("UPDATE mass_message_recipients SET email_status = 'failed' WHERE id = ?");
                $stmt->execute([$recipient_id]);
                $errors[] = "Email для {$user_name}: " . $e->getMessage();
            }
        }

        // Отправка Telegram
        if ($recipient['telegram_status'] === 'pending' && !empty($telegram_chat_id)) {
            try {
                $telegram_message = "📬 <b>" . htmlspecialchars($message['title'] ?? '') . "</b>\n\n";
                $telegram_message .= htmlspecialchars($message['content'] ?? '');
                $telegram_message .= "\n\n🌐 <a href='https://" . $_SERVER['HTTP_HOST'] . "'>Перейти на сайт</a>";

                if ($telegram->sendMessage($telegram_chat_id, $telegram_message)) {
                    $stmt = $pdo->prepare("UPDATE mass_message_recipients SET telegram_status = 'sent', sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$recipient_id]);
                    $telegrams_sent++;
                } else {
                    throw new Exception('Ошибка отправки в Telegram');
                }

            } catch (Exception $e) {
                $stmt = $pdo->prepare("UPDATE mass_message_recipients SET telegram_status = 'failed' WHERE id = ?");
                $stmt->execute([$recipient_id]);
                $errors[] = "Telegram для {$user_name}: " . $e->getMessage();
            }
        }
    }

    // Обновление счетчиков в основной таблице
    $stmt = $pdo->prepare("
        UPDATE mass_messages 
        SET emails_sent = emails_sent + ?, telegrams_sent = telegrams_sent + ? 
        WHERE id = ?
    ");
    $stmt->execute([$emails_sent, $telegrams_sent, $message_id]);

} catch (Exception $e) {
    $errors[] = "Критическая ошибка: " . $e->getMessage();
}

// Получение обновленной статистики
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pending,
        SUM(CASE WHEN email_status = 'sent' THEN 1 ELSE 0 END) as emails_sent,
        SUM(CASE WHEN telegram_status = 'sent' THEN 1 ELSE 0 END) as telegrams_sent,
        SUM(CASE WHEN email_status = 'failed' OR telegram_status = 'failed' THEN 1 ELSE 0 END) as total_failed
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

        <!-- Заголовок -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Отправка рассылки</h1>
            <p class="text-lg text-gray-700">
                <?php echo htmlspecialchars($message['title'] ?? 'Неизвестная рассылка'); ?></p>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <!-- Результаты отправки -->
        <div class="bg-white rounded-3xl shadow-xl p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Результаты отправки батча</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="text-center p-6 bg-green-50 rounded-lg border border-green-200">
                    <div class="text-3xl font-bold text-green-600"><?php echo $emails_sent; ?></div>
                    <div class="text-green-700">📧 Email отправлено</div>
                </div>

                <div class="text-center p-6 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $telegrams_sent; ?></div>
                    <div class="text-blue-700">📱 Telegram отправлено</div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <h3 class="font-bold text-red-800 mb-2">❌ Ошибки отправки:</h3>
                    <ul class="list-disc list-inside text-red-700 text-sm">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Прогресс -->
            <div class="mb-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Прогресс отправки</span>
                    <span><?php echo $still_pending > 0 ? 'В процессе...' : 'Завершено!'; ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <?php
                    $total_recipients = $message['total_recipients'] ?? 1;
                    $progress = $total_recipients > 0 ? (($total_recipients - $still_pending) / $total_recipients) * 100 : 100;
                    ?>
                    <div class="bg-gradient-to-r from-[#118568] to-[#17B890] h-3 rounded-full transition-all duration-300"
                        style="width: <?php echo round($progress, 1); ?>%"></div>
                </div>
                <div class="text-sm text-gray-600 mt-1">
                    <?php echo round($progress, 1); ?>% завершено
                    (осталось: <?php echo $still_pending; ?> из <?php echo $total_recipients; ?>)
                </div>
            </div>
        </div>

        <!-- Управление процессом -->
        <div class="bg-white rounded-3xl shadow-xl p-8 text-center">
            <?php if ($still_pending > 0): ?>
                <h2 class="text-2xl font-bold text-gray-800 mb-4">🔄 Отправка продолжается</h2>
                <p class="text-gray-600 mb-6">
                    Обрабатывается следующий батч получателей. Процесс автоматически продолжится.
                </p>

                <div class="flex flex-col md:flex-row gap-4 justify-center">
                    <a href="?id=<?php echo $message_id; ?>"
                        class="px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition font-medium">
                        🔄 Продолжить отправку
                    </a>

                    <a href="/admin/messaging/details.php?id=<?php echo $message_id; ?>"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                        📊 Посмотреть детали
                    </a>
                </div>

                <script>
                    // Автоматическое продолжение через 3 секунды
                    setTimeout(function () {
                        window.location.href = '?id=<?php echo $message_id; ?>';
                    }, 3000);
                </script>

            <?php else: ?>
                <h2 class="text-2xl font-bold text-green-600 mb-4">✅ Рассылка завершена!</h2>
                <p class="text-gray-600 mb-6">
                    Все сообщения были обработаны. Проверьте детали для получения полной статистики.
                </p>

                <div class="flex flex-col md:flex-row gap-4 justify-center">
                    <a href="/admin/messaging/details.php?id=<?php echo $message_id; ?>"
                        class="px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition font-medium">
                        📊 Посмотреть детали
                    </a>

                    <a href="/admin/messaging/"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                        ← К списку рассылок
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>