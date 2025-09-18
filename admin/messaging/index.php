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
                    <?php echo number_format($stats['total_emails_sent']); ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Telegram отправлено</h3>
                <p class="text-3xl font-bold text-[#17B890] mt-2">
                    <?php echo number_format($stats['total_telegrams_sent']); ?></p>
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
            <div class="bg-white rounded-3xl shadow-xl p-8 mb-8">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="text-left py-4 px-4 font-medium text-gray-600">Заголовок</th>
                                <th class="text-center py-4 px-4 font-medium text-gray-600">Тип</th>
                                <th class="text-center py-4 px-4 font-medium text-gray-600">Аудитория</th>
                                <th class="text-center py-4 px-4 font-medium text-gray-600">Получатели</th>
                                <th class="text-center py-4 px-4 font-medium text-gray-600">Статус</th>
                                <th class="text-center py-4 px-4 font-medium text-gray-600">Дата создания</th>
                                <th class="text-center py-4 px-4 font-medium text-gray-600">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-4 px-4">
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($message['title']); ?></p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo htmlspecialchars($message['created_by_name']); ?></p>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php
                                        $type_badges = [
                                            'email' => '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Email</span>',
                                            'telegram' => '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Telegram</span>',
                                            'both' => '<span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">Email + Telegram</span>'
                                        ];
                                        echo $type_badges[$message['message_type']];
                                        ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
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
                                    </td>
                                    <td class="py-4 px-4 text-center font-medium"><?php echo $message['total_recipients']; ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php
                                        $status_badges = [
                                            'draft' => '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">Черновик</span>',
                                            'scheduled' => '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Запланировано</span>',
                                            'sending' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Отправляется</span>',
                                            'sent' => '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Отправлено</span>',
                                            'failed' => '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Ошибка</span>'
                                        ];
                                        echo $status_badges[$message['status']];
                                        ?>
                                    </td>
                                    <td class="py-4 px-4 text-center text-gray-600">
                                        <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <a href="/admin/messaging/details.php?id=<?php echo $message['id']; ?>"
                                                class="px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 transition"
                                                title="Посмотреть детали">
                                                📊 Детали
                                            </a>
                                            <?php if ($message['status'] === 'draft'): ?>
                                                <a href="/admin/messaging/edit.php?id=<?php echo $message['id']; ?>"
                                                    class="px-3 py-1 bg-yellow-500 text-white text-xs rounded hover:bg-yellow-600 transition"
                                                    title="Редактировать">
                                                    ✏️ Редактировать
                                                </a>
                                                <a href="/admin/messaging/send.php?id=<?php echo $message['id']; ?>"
                                                    class="px-3 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600 transition"
                                                    title="Отправить">
                                                    🚀 Отправить
                                                </a>
                                            <?php elseif ($message['status'] === 'sending'): ?>
                                                <a href="/admin/messaging/send.php?id=<?php echo $message['id']; ?>"
                                                    class="px-3 py-1 bg-orange-500 text-white text-xs rounded hover:bg-orange-600 transition"
                                                    title="Мониторинг">
                                                    📈 Мониторинг
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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