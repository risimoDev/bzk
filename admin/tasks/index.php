<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = "Управление задачами";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');
include_once('../../includes/telegram.php');

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

// Обработка изменения статуса задачи
if (isset($_POST['update_status'])) {
    $task_id = intval($_POST['task_id']);
    $new_status = $_POST['status'];

    // Получаем старый статус
    $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $old_status = $stmt->fetchColumn();

    // Обновляем статус
    $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$new_status, $task_id])) {
        // Отправляем уведомление о смене статуса
        sendTaskStatusNotification($task_id, $old_status, $new_status);
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Статус задачи обновлен!'];
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при обновлении статуса!'];
    }

    header("Location: /admin/tasks");
    exit();
}

// Фильтры
$filter_status = $_GET['status'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$filter_assigned = $_GET['assigned'] ?? 'all';

$query = "SELECT t.*, 
                 assigned.name as assigned_name, 
                 creator.name as creator_name
          FROM tasks t
          LEFT JOIN users assigned ON t.assigned_to = assigned.id
          LEFT JOIN users creator ON t.created_by = creator.id
          WHERE 1=1";
$params = [];

if ($filter_status !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $filter_status;
}

if ($filter_priority !== 'all') {
    $query .= " AND t.priority = ?";
    $params[] = $filter_priority;
}

if ($filter_assigned !== 'all') {
    if ($filter_assigned === 'unassigned') {
        $query .= " AND t.assigned_to IS NULL";
    } elseif ($filter_assigned === 'me') {
        $query .= " AND t.assigned_to = ?";
        $params[] = $_SESSION['user_id'];
    } else {
        $query .= " AND t.assigned_to = ?";
        $params[] = $filter_assigned;
    }
}

$query .= " ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END, 
            t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение списка пользователей для фильтра
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role IN ('admin', 'manager') ORDER BY name");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика
$total_tasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$pending_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn();
$in_progress_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'")->fetchColumn();
$completed_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn();
$my_tasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status NOT IN ('completed', 'cancelled')");
$my_tasks->execute([$_SESSION['user_id']]);
$my_tasks_count = $my_tasks->fetchColumn();
?>

<?php include_once('../../includes/header.php'); ?>

<main class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Breadcrumbs -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div class="w-full md:w-auto">
            <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
        </div>
        <div class="w-full md:w-auto">
            <div class="flex gap-2">
                <?php echo backButton(); ?>
                <a href="/admin/tasks/add"
                    class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">
                    + Новая задача
                </a>
            </div>
        </div>
    </div>

    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление задачами</h1>
        <p class="text-lg text-gray-600">Всего: <?php echo $total_tasks; ?> | Моих активных:
            <?php echo $my_tasks_count; ?></p>
        <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $n): ?>
        <div
            class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($n['message']); ?>
        </div>
    <?php endforeach; ?>

    <!-- Статистические карточки -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $total_tasks; ?></p>
                    <p class="text-gray-600">Всего задач</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $pending_tasks; ?></p>
                    <p class="text-gray-600">В ожидании</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $in_progress_tasks; ?></p>
                    <p class="text-gray-600">В работе</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $completed_tasks; ?></p>
                    <p class="text-gray-600">Завершено</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <form method="get" class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Фильтры</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Статус</label>
                <select name="status"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent">
                    <option value="all">Все статусы</option>
                    <option value="pending" <?php if ($filter_status === 'pending')
                        echo 'selected'; ?>>В ожидании</option>
                    <option value="in_progress" <?php if ($filter_status === 'in_progress')
                        echo 'selected'; ?>>В работе
                    </option>
                    <option value="completed" <?php if ($filter_status === 'completed')
                        echo 'selected'; ?>>Завершено
                    </option>
                    <option value="cancelled" <?php if ($filter_status === 'cancelled')
                        echo 'selected'; ?>>Отменено
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Приоритет</label>
                <select name="priority"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent">
                    <option value="all">Все приоритеты</option>
                    <option value="urgent" <?php if ($filter_priority === 'urgent')
                        echo 'selected'; ?>>🔴 Срочно</option>
                    <option value="high" <?php if ($filter_priority === 'high')
                        echo 'selected'; ?>>🟠 Высокий</option>
                    <option value="medium" <?php if ($filter_priority === 'medium')
                        echo 'selected'; ?>>🟡 Средний</option>
                    <option value="low" <?php if ($filter_priority === 'low')
                        echo 'selected'; ?>>🟢 Низкий</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Исполнитель</label>
                <select name="assigned"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent">
                    <option value="all">Все исполнители</option>
                    <option value="me" <?php if ($filter_assigned === 'me')
                        echo 'selected'; ?>>Мои задачи</option>
                    <option value="unassigned" <?php if ($filter_assigned === 'unassigned')
                        echo 'selected'; ?>>Не
                        назначено</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php if ($filter_assigned == $user['id'])
                               echo 'selected'; ?>>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit"
                    class="w-full px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300">
                    Применить
                </button>
            </div>
        </div>
    </form>

    <!-- Список задач -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Задачи (<?php echo count($tasks); ?>)</h2>
        </div>

        <?php if (empty($tasks)): ?>
            <div class="p-8 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="text-gray-500 text-lg">Задач не найдено</p>
                <a href="/admin/tasks/add"
                    class="inline-block mt-4 px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300">
                    Создать первую задачу
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($tasks as $task): ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors duration-300">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                            <!-- Основная информация -->
                            <div class="flex-grow">
                                <div class="flex items-center gap-3 mb-2">
                                    <!-- Приоритет -->
                                    <?php
                                    $priority_colors = [
                                        'low' => 'bg-green-100 text-green-800',
                                        'medium' => 'bg-yellow-100 text-yellow-800',
                                        'high' => 'bg-orange-100 text-orange-800',
                                        'urgent' => 'bg-red-100 text-red-800'
                                    ];
                                    $priority_emojis = [
                                        'low' => '🟢',
                                        'medium' => '🟡',
                                        'high' => '🟠',
                                        'urgent' => '🔴'
                                    ];
                                    ?>
                                    <span
                                        class="px-2 py-1 text-xs rounded-full <?php echo $priority_colors[$task['priority']]; ?>">
                                        <?php echo $priority_emojis[$task['priority']]; ?>
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>

                                    <!-- ID задачи -->
                                    <span class="text-xs text-gray-500">#<?php echo $task['id']; ?></span>
                                </div>

                                <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($task['title']); ?></h3>

                                <!-- Task items if available -->
                                <?php if (!empty($task['task_items'])): ?>
                                    <?php
                                    $task_items = json_decode($task['task_items'], true);
                                    if (is_array($task_items) && !empty($task_items)):
                                        ?>
                                        <div class="mb-3">
                                            <p class="text-sm font-medium text-gray-700 mb-2">📋 Пункты для выполнения:</p>
                                            <ul class="text-sm text-gray-600 space-y-1 ml-4">
                                                <?php foreach ($task_items as $index => $item): ?>
                                                    <li class="flex items-start">
                                                        <span class="text-[#118568] font-bold mr-2"><?php echo ($index + 1); ?>.</span>
                                                        <span><?php echo htmlspecialchars($item); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($task['description'])): ?>
                                    <div class="mb-3">
                                        <p class="text-sm font-medium text-gray-700 mb-1">📄 Описание:</p>
                                        <p class="text-gray-600 text-sm line-clamp-3">
                                            <?php echo htmlspecialchars(substr($task['description'], 0, 200)); ?>            <?php if (strlen($task['description']) > 200)
                                                                 echo '...'; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                                    <span>👤 Создал: <?php echo htmlspecialchars($task['creator_name']); ?></span>
                                    <?php if ($task['assigned_name']): ?>
                                        <span>🎯 Исполнитель: <?php echo htmlspecialchars($task['assigned_name']); ?></span>
                                    <?php else: ?>
                                        <span>🎯 Общая задача</span>
                                    <?php endif; ?>
                                    <span>📅 Создано: <?php echo date('d.m.Y H:i', strtotime($task['created_at'])); ?></span>
                                    <?php if ($task['due_date']): ?>
                                        <span
                                            class="<?php echo strtotime($task['due_date']) < time() && $task['status'] !== 'completed' ? 'text-red-600 font-medium' : ''; ?>">
                                            ⏰ Срок: <?php echo date('d.m.Y H:i', strtotime($task['due_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Статус и действия -->
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                <!-- Текущий статус -->
                                <?php
                                $status_colors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                    'completed' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800'
                                ];
                                $status_names = [
                                    'pending' => '⏳ В ожидании',
                                    'in_progress' => '🔄 В работе',
                                    'completed' => '✅ Завершено',
                                    'cancelled' => '❌ Отменено'
                                ];
                                ?>
                                <span class="px-3 py-1 text-sm rounded-full <?php echo $status_colors[$task['status']]; ?>">
                                    <?php echo $status_names[$task['status']]; ?>
                                </span>

                                <!-- Форма изменения статуса -->
                                <form method="post" class="flex gap-2">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <select name="status"
                                        class="px-3 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent">
                                        <option value="pending" <?php if ($task['status'] === 'pending')
                                            echo 'selected'; ?>>⏳ В
                                            ожидании</option>
                                        <option value="in_progress" <?php if ($task['status'] === 'in_progress')
                                            echo 'selected'; ?>>🔄 В работе</option>
                                        <option value="completed" <?php if ($task['status'] === 'completed')
                                            echo 'selected'; ?>>✅
                                            Завершено</option>
                                        <option value="cancelled" <?php if ($task['status'] === 'cancelled')
                                            echo 'selected'; ?>>❌
                                            Отменено</option>
                                    </select>
                                    <button type="submit" name="update_status"
                                        class="px-3 py-1 bg-[#118568] text-white text-sm rounded-lg hover:bg-[#0f755a] transition-colors duration-300">
                                        Обновить
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>

<style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>