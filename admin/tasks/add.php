<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = "Создание задачи";

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

// Обработка создания задачи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    // Process task items
    $task_items = [];
    if (!empty($_POST['task_items'])) {
        foreach ($_POST['task_items'] as $item) {
            $item = trim($item);
            if (!empty($item)) {
                $task_items[] = $item;
            }
        }
    }
    $task_items_json = !empty($task_items) ? json_encode($task_items, JSON_UNESCAPED_UNICODE) : null;

    // Валидация
    $errors = [];
    if (empty($title)) {
        $errors[] = 'Заголовок задачи обязателен';
    }
    if (strlen($title) > 255) {
        $errors[] = 'Заголовок не должен превышать 255 символов';
    }
    if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
        $errors[] = 'Неверный приоритет';
    }
    if (empty($task_items) && empty($description)) {
        $errors[] = 'Добавьте хотя бы один пункт задачи или описание';
    }

    if (empty($errors)) {
        try {
            // Создание задачи
            $stmt = $pdo->prepare("
                INSERT INTO tasks (title, description, task_items, assigned_to, created_by, priority, due_date, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");

            if ($stmt->execute([$title, $description, $task_items_json, $assigned_to, $_SESSION['user_id'], $priority, $due_date])) {
                $task_id = $pdo->lastInsertId();

                // Отправка уведомления в Telegram
                try {
                    sendTaskAssignmentNotification($task_id);
                } catch (Exception $e) {
                    error_log('Telegram notification error: ' . $e->getMessage());
                }

                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Задача успешно создана и уведомление отправлено!'];
                header("Location: /admin/tasks");
                exit();
            } else {
                $errors[] = 'Ошибка при создании задачи';
            }
        } catch (Exception $e) {
            $errors[] = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => implode('<br>', $errors)];
    }
}

// Получение списка пользователей для назначения
$stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE role IN ('admin', 'manager') AND is_blocked = 0 ORDER BY name");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include_once('../../includes/header.php'); ?>

<main class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Breadcrumbs -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div class="w-full md:w-auto">
            <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
        </div>
        <div class="w-full md:w-auto">
            <div class="flex gap-2">
                <?php echo backButton(); ?>
                <a href="/admin/tasks"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-300 text-sm">
                    Все задачи
                </a>
            </div>
        </div>
    </div>

    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-800 mb-4">Создание новой задачи</h1>
        <p class="text-lg text-gray-600">Создайте общую задачу или назначьте её конкретному исполнителю</p>
        <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $n): ?>
        <div
            class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $n['message']; ?>
        </div>
    <?php endforeach; ?>

    <!-- Форма создания задачи -->
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <form method="post" class="space-y-6">
            <!-- Заголовок задачи -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                    Заголовок задачи <span class="text-red-500">*</span>
                </label>
                <input type="text" id="title" name="title" required maxlength="255"
                    value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300"
                    placeholder="Введите краткое описание задачи">
            </div>

            <!-- Описание задачи -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Общее описание (опционально)
                </label>
                <textarea id="description" name="description" rows="3"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300"
                    placeholder="Общие рекомендации, контекст задачи..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <!-- Пункты задачи -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Пункты для выполнения
                </label>
                <div id="task-items-container" class="space-y-3">
                    <!-- Первый пункт -->
                    <div class="flex items-center gap-2 task-item">
                        <span class="text-gray-500 font-mono text-sm w-8">1.</span>
                        <input type="text" name="task_items[]" placeholder="Опишите конкретное действие..."
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300">
                        <button type="button"
                            class="remove-item px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-300"
                            style="display: none;">
                            ✖
                        </button>
                    </div>
                </div>

                <button type="button" id="add-item"
                    class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-300 text-sm">
                    + Добавить пункт
                </button>

                <p class="mt-2 text-sm text-gray-500">
                    Добавьте конкретные шаги, которые нужно выполнить. Например: "Подготовить макет", "Согласовать с
                    клиентом"
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Приоритет -->
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
                        Приоритет <span class="text-red-500">*</span>
                    </label>
                    <select id="priority" name="priority" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300">
                        <option value="low" <?php echo ($_POST['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>🟢
                            Низкий</option>
                        <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>🟡 Средний</option>
                        <option value="high" <?php echo ($_POST['priority'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>
                            🟠 Высокий</option>
                        <option value="urgent" <?php echo ($_POST['priority'] ?? 'medium') === 'urgent' ? 'selected' : ''; ?>>🔴 Срочно</option>
                    </select>
                </div>

                <!-- Срок выполнения -->
                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Срок выполнения
                    </label>
                    <input type="datetime-local" id="due_date" name="due_date"
                        value="<?php echo $_POST['due_date'] ?? ''; ?>" min="<?php echo date('Y-m-d\TH:i'); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300">
                </div>
            </div>

            <!-- Назначение исполнителя -->
            <div>
                <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-2">
                    Исполнитель
                </label>
                <select id="assigned_to" name="assigned_to"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300">
                    <option value="">Общая задача (для всех администраторов)</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($_POST['assigned_to'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['name']); ?>
                            (<?php echo $user['role'] === 'admin' ? 'Администратор' : 'Менеджер'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-2 text-sm text-gray-500">
                    Если не выбрать исполнителя, задача будет общей и уведомления получат все администраторы и менеджеры
                </p>
            </div>

            <!-- Информационный блок -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <h4 class="text-sm font-medium text-blue-800">Уведомления в Telegram</h4>
                        <p class="text-sm text-blue-700 mt-1">
                            После создания задачи автоматически отправится уведомление в Telegram:
                        </p>
                        <ul class="text-sm text-blue-700 mt-2 list-disc list-inside">
                            <li>Если назначен конкретный исполнитель - уведомление отправится ему</li>
                            <li>Если задача общая - уведомления получат все администраторы и менеджеры</li>
                            <li>Для получения уведомлений пользователь должен настроить Telegram ID в профиле</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Кнопки -->
            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                <button type="submit" name="create_task"
                    class="flex-1 px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 font-medium">
                    🚀 Создать задачу и отправить уведомления
                </button>
                <a href="/admin/tasks"
                    class="flex-1 px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors duration-300 font-medium text-center">
                    Отмена
                </a>
            </div>
        </form>
    </div>

    <!-- Предпросмотр уведомления -->
    <div class="mt-8 bg-white rounded-2xl shadow-xl p-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">📱 Предпросмотр Telegram уведомления</h2>
        <div class="bg-gray-100 rounded-lg p-4 font-mono text-sm">
            <div class="bg-white rounded-lg p-4 shadow">
                <div class="text-blue-600 font-bold mb-2">📋 Новая задача назначена!</div>
                <div class="space-y-1 text-gray-800">
                    <div>🟡 <strong>Приоритет:</strong> <span id="preview-priority">Medium</span></div>
                    <div>📝 <strong>Заголовок:</strong> <span id="preview-title">Название задачи</span></div>
                    <div>📄 <strong>Описание:</strong><br><span id="preview-description">Описание задачи</span></div>
                    <div>⏰ <strong>Срок выполнения:</strong> <span id="preview-due">Не указан</span></div>
                    <div>👤 <strong>Создал:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    <div>🆔 <strong>ID задачи:</strong> #[ID будет присвоен автоматически]</div>
                    <div class="pt-2 text-blue-600">🌐 Посмотреть все задачи:
                        https://<?php echo $_SERVER['HTTP_HOST']; ?>/admin/tasks</div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const titleInput = document.getElementById('title');
        const descriptionInput = document.getElementById('description');
        const priorityInput = document.getElementById('priority');
        const dueDateInput = document.getElementById('due_date');

        const previewTitle = document.getElementById('preview-title');
        const previewDescription = document.getElementById('preview-description');
        const previewPriority = document.getElementById('preview-priority');
        const previewDue = document.getElementById('preview-due');

        const priorityEmojis = {
            'low': '🟢 Low',
            'medium': '🟡 Medium',
            'high': '🟠 High',
            'urgent': '🔴 Urgent'
        };

        function updatePreview() {
            previewTitle.textContent = titleInput.value || 'Название задачи';
            previewDescription.textContent = descriptionInput.value || 'Описание задачи';
            previewPriority.textContent = priorityEmojis[priorityInput.value] || '🟡 Medium';

            if (dueDateInput.value) {
                const date = new Date(dueDateInput.value);
                previewDue.textContent = date.toLocaleString('ru-RU');
            } else {
                previewDue.textContent = 'Не указан';
            }
        }

        titleInput.addEventListener('input', updatePreview);
        descriptionInput.addEventListener('input', updatePreview);
        priorityInput.addEventListener('change', updatePreview);
        dueDateInput.addEventListener('change', updatePreview);

        // Управление пунктами задачи
        const container = document.getElementById('task-items-container');
        const addButton = document.getElementById('add-item');

        let itemCount = 1;

        function updateItemNumbers() {
            const items = container.querySelectorAll('.task-item');
            items.forEach((item, index) => {
                const numberSpan = item.querySelector('span');
                numberSpan.textContent = (index + 1) + '.';
            });

            // Показываем кнопки удаления только если пунктов больше 1
            const removeButtons = container.querySelectorAll('.remove-item');
            removeButtons.forEach(btn => {
                btn.style.display = items.length > 1 ? 'block' : 'none';
            });
        }

        function addTaskItem() {
            itemCount++;
            const newItem = document.createElement('div');
            newItem.className = 'flex items-center gap-2 task-item';
            newItem.innerHTML = `
            <span class="text-gray-500 font-mono text-sm w-8">${itemCount}.</span>
            <input 
                type="text" 
                name="task_items[]" 
                placeholder="Опишите конкретное действие..."
                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300"
            >
            <button type="button" class="remove-item px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-300">
                ✖
            </button>
        `;

            container.appendChild(newItem);
            updateItemNumbers();

            // Фокус на новом поле
            newItem.querySelector('input').focus();

            // Добавляем обработчик удаления
            newItem.querySelector('.remove-item').addEventListener('click', function () {
                newItem.remove();
                updateItemNumbers();
            });
        }

        // Обработчик добавления нового пункта
        addButton.addEventListener('click', addTaskItem);

        // Обработчик удаления первого пункта (если он есть)
        const firstRemoveBtn = container.querySelector('.remove-item');
        if (firstRemoveBtn) {
            firstRemoveBtn.addEventListener('click', function () {
                firstRemoveBtn.closest('.task-item').remove();
                updateItemNumbers();
            });
        }

        // Инициализация
        updatePreview();
        updateItemNumbers();

        // Добавление пункта по Enter
        container.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.matches('input[name="task_items[]"]')) {
                e.preventDefault();
                const currentInput = e.target;
                if (currentInput.value.trim()) {
                    addTaskItem();
                }
            }
        });
    });
</script>