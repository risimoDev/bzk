<?php
session_start();
$pageTitle = "Массовые рассылки";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

// Параметры пагинации
$page = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Получение списка сообщений
$sql = "
    SELECT 
        mm.*,
        u.name as created_by_name,
        COUNT(mmr.id) as total_recipients
    FROM mass_messages mm
    LEFT JOIN users u ON mm.created_by = u.id
    LEFT JOIN mass_message_recipients mmr ON mm.id = mmr.mass_message_id
    GROUP BY mm.id
    ORDER BY mm.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$limit, $offset]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Общее количество сообщений для пагинации
$count_stmt = $pdo->query("SELECT COUNT(*) FROM mass_messages");
$total_messages = $count_stmt->fetchColumn();
$total_pages = ceil($total_messages / $limit);

// Статистика
$stats_sql = "
    SELECT 
        COUNT(*) as total_messages,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_messages,
        SUM(CASE WHEN status = 'sending' THEN 1 ELSE 0 END) as sending_messages,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_messages,
        SUM(emails_sent) as total_emails_sent,
        SUM(telegrams_sent) as total_telegrams_sent
    FROM mass_messages
";
$stats_stmt = $pdo->query($stats_sql);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
    <div class="container mx-auto px-6 max-w-7xl">

        <!-- Заголовок -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
            <div>
                <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
            </div>
            <div class="flex gap-3">
                <?php echo backButton(); ?>
                <a href="/admin/messaging/create.php"
                    class="px-5 py-2.5 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition text-sm font-medium">
                    + Новая рассылка
                </a>
            </div>
        </div>

        <!-- Заголовок страницы -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Массовые рассылки</h1>
            <p class="text-lg text-gray-700">Управление email и Telegram рассылками</p>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <!-- Уведомления -->
        <?php foreach ($notifications as $n): ?>
            <div
                class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <?php echo htmlspecialchars($n['message']); ?>
            </div>
        <?php endforeach; ?>

        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Всего рассылок</h3>
                <p class="text-3xl font-bold text-[#118568] mt-2"><?php echo $stats['total_messages']; ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Отправлено</h3>
                <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $stats['sent_messages']; ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">В процессе</h3>
                <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $stats['sending_messages']; ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Запланировано</h3>
                <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $stats['scheduled_messages']; ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Email отправлено</h3>
                <p class="text-3xl font-bold text-[#118568] mt-2">
                    <?php echo number_format($stats['total_emails_sent']); ?>
                </p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Telegram отправлено</h3>
                <p class="text-3xl font-bold text-[#17B890] mt-2">
                    <?php echo number_format($stats['total_telegrams_sent']); ?>
                </p>
            </div>
        </div>

        <!-- Список рассылок -->
        <?php if (empty($messages)): ?>
            <div class="text-center py-16">
                <div class="bg-white rounded-3xl shadow-lg p-12">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Рассылки не найдены</h3>
                    <p class="text-gray-600 mb-8">Создайте первую массовую рассылку</p>
                    <a href="/admin/messaging/create.php"
                        class="inline-block px-8 py-4 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition font-medium">
                        + Создать рассылку
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($messages as $message): ?>
                    <div
                        class="bg-white rounded-3xl shadow-xl p-6 hover:shadow-2xl transition-shadow duration-300 border border-gray-100">
                        <!-- Заголовок карточки -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center flex-1 min-w-0">
                                <div
                                    class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                    <?php if ($message['message_type'] === 'email'): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    <?php elseif ($message['message_type'] === 'telegram'): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                d="M12 0C5.374 0 0 5.373 0 12s5.374 12 12 12 12-5.373 12-12S18.626 0 12 0zm5.568 8.16c-.169 1.858-.896 6.728-.896 6.728-.378 1.586-.842 1.819-1.369 1.819-.6 0-.894-.562-.894-1.167 0-.303.03-.573.069-.806l.259-1.725c.133-.884.199-1.341.199-1.37l-.013-.03-.039-.009-.077.069c-.585.52-1.153 1.035-1.738 1.546l-.889.777c-.373.326-.765.63-1.173.897-.316.207-.628.376-.935.506l-.451.191c-.31.13-.615.226-.914.287-.327.067-.646.1-.957.1-.855 0-1.55-.289-2.085-.868-.535-.578-.803-1.337-.803-2.277 0-.636.128-1.277.384-1.923.307-.774.765-1.529 1.373-2.267C8.264 7.538 9.016 6.985 9.94 6.568c.923-.417 1.942-.625 3.057-.625.702 0 1.334.105 1.896.314.562.21 1.042.497 1.44.863.397.365.695.784.893 1.256.198.472.297.969.297 1.491 0 .344-.037.674-.111.99z" />
                                        </svg>
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-bold text-gray-800 text-lg truncate">
                                        <?php echo htmlspecialchars($message['title']); ?></h3>
                                    <p class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($message['created_by_name']); ?></p>
                                </div>
                            </div>
                            <?php
                            $status_badges = [
                                'draft' => '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full font-medium">Черновик</span>',
                                'scheduled' => '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">Запланировано</span>',
                                'sending' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full font-medium">Отправляется</span>',
                                'sent' => '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">Отправлено</span>',
                                'failed' => '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full font-medium">Ошибка</span>'
                            ];
                            echo $status_badges[$message['status']];
                            ?>
                        </div>

                        <!-- Информация о рассылке -->
                        <div class="space-y-3 mb-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Тип:</span>
                                <?php
                                $type_badges = [
                                    'email' => '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Email</span>',
                                    'telegram' => '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Telegram</span>',
                                    'both' => '<span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">Email + Telegram</span>'
                                ];
                                echo $type_badges[$message['message_type']];
                                ?>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Аудитория:</span>
                                <span class="text-sm font-medium text-gray-800">
                                    <?php
                                    $audience_labels = [
                                        'all' => 'Все пользователи',
                                        'customers' => 'Клиенты',
                                        'admins' => 'Администраторы',
                                        'managers' => 'Менеджеры',
                                        'specific' => 'Выборочно'
                                    ];
                                    echo $audience_labels[$message['target_audience']];
                                    ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Получатели:</span>
                                <span
                                    class="text-lg font-bold text-[#118568]"><?php echo $message['total_recipients']; ?></span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Дата создания:</span>
                                <span
                                    class="text-sm text-gray-700"><?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?></span>
                            </div>
                        </div>

                        <!-- Действия -->
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">
                            <a href="/admin/messaging/details.php?id=<?php echo $message['id']; ?>"
                                class="flex-1 min-w-0 text-center px-3 py-2 bg-blue-500 text-white text-xs rounded-lg hover:bg-blue-600 transition font-medium"
                                title="Посмотреть детали">
                                📊 Детали
                            </a>
                            <?php if ($message['status'] === 'draft'): ?>
                                <a href="/admin/messaging/edit.php?id=<?php echo $message['id']; ?>"
                                    class="flex-1 min-w-0 text-center px-3 py-2 bg-yellow-500 text-white text-xs rounded-lg hover:bg-yellow-600 transition font-medium"
                                    title="Редактировать">
                                    ✏️ Редактировать
                                </a>
                                <a href="/admin/messaging/send.php?id=<?php echo $message['id']; ?>"
                                    class="flex-1 min-w-0 text-center px-3 py-2 bg-green-500 text-white text-xs rounded-lg hover:bg-green-600 transition font-medium"
                                    title="Отправить">
                                    🚀 Отправить
                                </a>
                            <?php elseif ($message['status'] === 'sending'): ?>
                                <a href="/admin/messaging/send.php?id=<?php echo $message['id']; ?>"
                                    class="flex-1 min-w-0 text-center px-3 py-2 bg-orange-500 text-white text-xs rounded-lg hover:bg-orange-600 transition font-medium"
                                    title="Мониторинг">
                                    📈 Мониторинг
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Пагинация -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center">
                    <nav class="bg-white rounded-2xl shadow-lg p-4">
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                    Назад
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>"
                                    class="px-4 py-2 <?php echo $i === $page ? 'bg-[#118568] text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-lg transition">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                    Далее
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>