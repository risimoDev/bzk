<?php
/**
 * Telegram Bot functionality for sending task notifications
 * Add your bot token and configure chat IDs in your .env file
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class TelegramBot
{
    private $bot_token;
    private $api_url;

    public function __construct()
    {
        // Get bot token from environment variables
        $this->bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        // Исправлено: убран лишний пробел между 'bot' и $this->bot_token
        $this->api_url = "https://api.telegram.org/bot{$this->bot_token}/";
    }

    /**
     * Send message to group chat as well
     */
    public function sendToGroupAndUser($user_chat_id, $message, $parse_mode = 'HTML', $reply_markup = null)
    {
        $results = [];

        // Send to user
        if (!empty($user_chat_id)) {
            $results['user'] = $this->sendMessage($user_chat_id, $message, $parse_mode, $reply_markup);
        }

        // Send to group chat if configured
        $group_chat_id = $_ENV['TELEGRAM_GROUP_CHAT_ID'] ?? '';
        if (!empty($group_chat_id)) {
            $results['group'] = $this->sendMessage($group_chat_id, $message, $parse_mode, $reply_markup);
        }

        return $results;
    }

    /**
     * Send message to Telegram chat
     */
    public function sendMessage($chat_id, $message, $parse_mode = 'HTML', $reply_markup = null)
    {
        if (empty($this->bot_token) || empty($chat_id)) {
            error_log('Telegram: Bot token or chat ID is missing');
            return false;
        }

        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode
        ];

        if ($reply_markup) {
            $data['reply_markup'] = json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url . 'sendMessage');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log('Telegram cURL error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($http_code !== 200) {
            error_log('Telegram API error: ' . $result);
            return false;
        }

        return json_decode($result, true);
    }

    /**
     * Отправить список задач (для админов/менеджеров)
     */
    public function sendTaskList($chat_id, $user_id = null, $scope = 'my')
    {
        global $pdo;

        // Только открытые задачи
        if ($scope === 'all') {
            $stmt = $pdo->prepare("SELECT id, title, status, priority FROM tasks WHERE status IN ('pending','in_progress') ORDER BY FIELD(priority,'urgent','high','medium','low'), created_at DESC LIMIT 10");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT id, title, status, priority FROM tasks WHERE assigned_to = ? AND status IN ('pending','in_progress') ORDER BY FIELD(priority,'urgent','high','medium','low'), created_at DESC LIMIT 10");
            $stmt->execute([(int) $user_id]);
        }
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tasks)) {
            $text = ($scope === 'all') ? "Задач не найдено." : "У вас нет активных задач.";
            return $this->sendMessage($chat_id, $text);
        }

        $keyboard = [];
        foreach ($tasks as $t) {
            $title = mb_substr($t['title'] ?? '', 0, 40);
            $keyboard[] = [
                [
                    'text' => "#{$t['id']} • {$title}",
                    'callback_data' => "task_view_{$t['id']}"
                ]
            ];
        }

        // Переключатели списков
        $keyboard[] = [
            ['text' => '📋 Мои', 'callback_data' => 'task_list_my'],
            ['text' => '📂 Все', 'callback_data' => 'task_list_all']
        ];

        $reply_markup = ['inline_keyboard' => $keyboard];
        $title = ($scope === 'all') ? 'Топ открытых задач' : 'Мои активные задачи';
        return $this->sendMessage($chat_id, "🧩 <b>{$title}</b>", 'HTML', $reply_markup);
    }

    /**
     * Отправить карточку задачи с кнопками статусов и ссылкой
     */
    public function sendTaskDetails($chat_id, $task_id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT t.*, u.name AS assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = ?");
        $stmt->execute([(int) $task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            return $this->sendMessage($chat_id, "Задача не найдена.");
        }

        $status_names = [
            'pending' => '⏳ В ожидании',
            'in_progress' => '🔄 В работе',
            'completed' => '✅ Завершено',
            'cancelled' => '❌ Отменено'
        ];
        $priority_names = [
            'low' => '🟢 Низкий',
            'medium' => '🟡 Средний',
            'high' => '🟠 Высокий',
            'urgent' => '🔴 Срочно'
        ];

        $message = "📋 <b>Задача #{$task['id']}</b>\n\n";
        $message .= "📝 <b>Заголовок:</b> " . ($task['title'] ?? '') . "\n";
        if (!empty($task['assigned_name']))
            $message .= "👤 <b>Исполнитель:</b> {$task['assigned_name']}\n";
        $message .= "🏷 <b>Приоритет:</b> " . ($priority_names[$task['priority']] ?? $task['priority']) . "\n";
        $message .= "🔄 <b>Статус:</b> " . ($status_names[$task['status']] ?? $task['status']) . "\n\n";
        if (!empty($task['description'])) {
            $desc = mb_substr($task['description'], 0, 500);
            $message .= "📄 <b>Описание:</b>\n{$desc}\n\n";
        }

        $reply_markup = $this->generateTaskStatusKeyboard($task['id'], $task['status']);
        // Добавим в самый низ кнопку-ссылку на раздел задач
        $reply_markup['inline_keyboard'][] = [
            [
                'text' => '🔗 Перейти к задачам',
                'url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bzkprint.ru') . '/admin/tasks'
            ]
        ];

        return $this->sendMessage($chat_id, $message, 'HTML', $reply_markup);
    }

    /**
     * Генерация inline-клавиатуры со статусами
     */
    private function generateTaskStatusKeyboard($task_id, $current_status)
    {
        $statuses = [
            'pending' => '⏳ В ожидании',
            'in_progress' => '🔄 В работе',
            'completed' => '✅ Завершено',
            'cancelled' => '❌ Отменено' // Исправлено: было '❌ Отмено'
        ];

        $keyboard = [];
        foreach (array_chunk($statuses, 2, true) as $row) {
            $row_buttons = [];
            foreach ($row as $key => $label) {
                $text = ($current_status === $key) ? $label . " ✅" : $label;
                $row_buttons[] = [
                    'text' => $text,
                    'callback_data' => "task_status_{$task_id}_{$key}"
                ];
            }
            $keyboard[] = $row_buttons;
        }

        return ['inline_keyboard' => $keyboard];
    }

    /**
     * Send task assignment notification
     */
    public function sendTaskAssignment($task_data, $assigned_user, $creator_user)
    {
        $priority_emoji = [
            'low' => '🟢',
            'medium' => '🟡',
            'high' => '🟠',
            'urgent' => '🔴'
        ];

        $emoji = $priority_emoji[$task_data['priority']] ?? '⚪';

        $message = "📋 <b>Новая задача назначена!</b>\n\n";
        $message .= "{$emoji} <b>Приоритет:</b> " . ucfirst($task_data['priority']) . "\n";
        $message .= "📝 <b>Заголовок:</b> {$task_data['title']}\n\n";

        if (!empty($task_data['task_items'])) {
            $message .= "📋 <b>Пункты для выполнения:</b>\n";
            $items = json_decode($task_data['task_items'], true);
            if (is_array($items)) {
                foreach ($items as $index => $item) {
                    $num = $index + 1;
                    $message .= "   {$num}. {$item}\n";
                }
            }
            $message .= "\n";
        }

        if (!empty($task_data['description'])) {
            $message .= "📄 <b>Описание:</b>\n{$task_data['description']}\n\n";
        }

        // --- НОВОЕ: Добавление информации о связанной бухгалтерской записи заказа ---
        if (!empty($task_data['related_order_accounting_id'])) {
            $order_type_text = ($task_data['related_order_source'] === 'site') ? 'заказа с сайта' : 'внешнего заказа';
            $order_id_to_link = ($task_data['related_order_source'] === 'site') ? $task_data['related_site_order_id'] : $task_data['related_external_order_id'];
            $order_details_url = ($task_data['related_order_source'] === 'site') ? "/admin/order/details.php?id={$task_data['related_site_order_id']}" : "/admin/order/external_details.php?id={$task_data['related_external_order_id']}";

            $message .= "📦 <b>Связанный {$order_type_text}:</b> #{$order_id_to_link} ";
            if (!empty($task_data['related_order_client_name'])) {
                $message .= "(Клиент: {$task_data['related_order_client_name']}) ";
            }
            $order_link = "https://{$_SERVER['HTTP_HOST']}{$order_details_url}";
            $message .= "\n🔗 Подробнее: {$order_link}\n\n";
        }
        // --- КОНЕЦ НОВОГО ---

        if (!empty($task_data['due_date'])) {
            $due_date = date('d.m.Y H:i', strtotime($task_data['due_date']));
            $message .= "⏰ <b>Срок выполнения:</b> {$due_date}\n";
        }

        $message .= "👤 <b>Создал:</b> {$creator_user['name']}\n";
        $message .= "🎯 <b>Исполнитель:</b> {$assigned_user['name']}\n";
        $message .= "🆔 <b>ID задачи:</b> #{$task_data['id']}\n\n";
        $message .= "🌐 Посмотреть все задачи: https://{$_SERVER['HTTP_HOST']}/admin/tasks";

        $reply_markup = $this->generateTaskStatusKeyboard($task_data['id'], $task_data['status']);

        return $this->sendToGroupAndUser($assigned_user['telegram_chat_id'], $message, 'HTML', $reply_markup);
    }

    /**
     * Send task assignment notification to all admins and managers
     */
    public function sendTaskAssignmentToAll($task_data, $creator_user)
    {
        global $pdo;

        // Получаем всех администраторов и менеджеров с настроенным Telegram ID
        $stmt = $pdo->prepare("
            SELECT id, name, telegram_chat_id 
            FROM users 
            WHERE role IN ('admin', 'manager') 
            AND is_blocked = 0 
            AND telegram_chat_id IS NOT NULL 
            AND telegram_chat_id != ''
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $priority_emoji = [
            'low' => '🟢',
            'medium' => '🟡',
            'high' => '🟠',
            'urgent' => '🔴'
        ];

        $emoji = $priority_emoji[$task_data['priority']] ?? '⚪';

        $message = "📋 <b>Новая общая задача!</b>\n\n";
        $message .= "{$emoji} <b>Приоритет:</b> " . ucfirst($task_data['priority']) . "\n";
        $message .= "📝 <b>Заголовок:</b> {$task_data['title']}\n\n";

        if (!empty($task_data['task_items'])) {
            $message .= "📋 <b>Пункты для выполнения:</b>\n";
            $items = json_decode($task_data['task_items'], true);
            if (is_array($items)) {
                foreach ($items as $index => $item) {
                    $num = $index + 1;
                    $message .= "   {$num}. {$item}\n";
                }
            }
            $message .= "\n";
        }

        if (!empty($task_data['description'])) {
            $message .= "📄 <b>Описание:</b>\n{$task_data['description']}\n\n";
        }

        // --- НОВОЕ: Добавление информации о связанной бухгалтерской записи заказа (дублируется из sendTaskAssignment) ---
        if (!empty($task_data['related_order_accounting_id'])) {
            $order_type_text = ($task_data['related_order_source'] === 'site') ? 'заказа с сайта' : 'внешнего заказа';
            $order_id_to_link = ($task_data['related_order_source'] === 'site') ? $task_data['related_site_order_id'] : $task_data['related_external_order_id'];
            $order_details_url = ($task_data['related_order_source'] === 'site') ? "/admin/order/details.php?id={$task_data['related_site_order_id']}" : "/admin/order/external_details.php?id={$task_data['related_external_order_id']}";

            $message .= "📦 <b>Связанный {$order_type_text}:</b> #{$order_id_to_link} ";
            if (!empty($task_data['related_order_client_name'])) {
                $message .= "(Клиент: {$task_data['related_order_client_name']}) ";
            }
            $order_link = "https://{$_SERVER['HTTP_HOST']}{$order_details_url}";
            $message .= "\n🔗 Подробнее: {$order_link}\n\n";
        }
        // --- КОНЕЦ НОВОГО ---

        if (!empty($task_data['due_date'])) {
            $due_date = date('d.m.Y H:i', strtotime($task_data['due_date']));
            $message .= "⏰ <b>Срок выполнения:</b> {$due_date}\n";
        }

        $message .= "👤 <b>Создал:</b> {$creator_user['name']}\n";
        $message .= "🎯 <b>Исполнитель:</b> Общая задача (для всех администраторов и менеджеров)\n";
        $message .= "🆔 <b>ID задачи:</b> #{$task_data['id']}\n\n";
        $message .= "🌐 Посмотреть все задачи: https://{$_SERVER['HTTP_HOST']}/admin/tasks";

        $reply_markup = $this->generateTaskStatusKeyboard($task_data['id'], $task_data['status']);

        // Отправляем уведомление каждому пользователю
        $results = [];
        foreach ($users as $user) {
            $result = $this->sendToGroupAndUser($user['telegram_chat_id'], $message, 'HTML', $reply_markup);
            $results[] = [
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'result' => $result
            ];
        }

        return $results;
    }

    /**
     * Handle callback queries (button presses)
     */
    public function handleCallbackQuery($callback_query)
    {
        file_put_contents(__DIR__ . "/callback_debug.log", print_r($callback_query, true), FILE_APPEND);

        global $pdo;

        $data = $callback_query['data'] ?? '';
        $chat_id = $callback_query['message']['chat']['id']
            ?? $callback_query['from']['id']
            ?? '';
        $message_id = $callback_query['message']['message_id'] ?? '';

        // Переключение списка задач
        if ($data === 'task_list_my' || $data === 'task_list_all') {
            // Определяем пользователя по chat_id
            $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_chat_id = ? AND role IN ('admin','manager') AND is_blocked = 0");
            $stmt->execute([(string) $chat_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $scope = ($data === 'task_list_all') ? 'all' : 'my';
            if ($user || $scope === 'all') {
                $this->answerCallbackQuery($callback_query['id'], 'Обновляю список…');
                $this->sendTaskList($chat_id, $user['id'] ?? null, $scope);
            } else {
                $this->answerCallbackQuery($callback_query['id'], 'Недоступно', true);
            }
            return;
        }

        // Просмотр задачи
        if (preg_match('/^task_view_(\d+)$/', $data, $m)) {
            $task_id = (int) $m[1];
            $this->answerCallbackQuery($callback_query['id'], 'Открываю задачу…');
            $this->sendTaskDetails($chat_id, $task_id);
            return;
        }

        if (preg_match('/^task_status_(\d+)_(\w+)$/', $data, $matches)) {
            $task_id = (int) $matches[1];
            $new_status = $matches[2];

            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                $old_status = $task['status'];

                $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $task_id]);

                $this->answerCallbackQuery($callback_query['id'], "Статус задачи обновлён!");

                // Обновляем клавиатуру
                $this->editMessageReplyMarkup($chat_id, $message_id, $task_id, $new_status);
            }
        }
    }

    /**
     * Edit message reply markup to update buttons
     */
    public function editMessageReplyMarkup($chat_id, $message_id, $task_id, $current_status)
    {
        $reply_markup = $this->generateTaskStatusKeyboard($task_id, $current_status);

        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'reply_markup' => json_encode($reply_markup, JSON_UNESCAPED_UNICODE)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url . 'editMessageReplyMarkup');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery($callback_query_id, $text = '', $show_alert = false)
    {
        if (empty($this->bot_token) || empty($callback_query_id)) {
            return false;
        }

        $data = [
            'callback_query_id' => $callback_query_id,
            'text' => $text,
            'show_alert' => $show_alert
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url . 'answerCallbackQuery');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);
    }
}

/**
 * Helper function to get TelegramBot instance
 */
function getTelegramBot()
{
    return new TelegramBot();
}

/**
 * Send task status change notification
 * Эта функция отправляет уведомление в Telegram о смене статуса задачи.
 * Уведомление отправляется исполнителю задачи (assigned_user) и в групповой чат (если настроен).
 *
 * @param int $task_id ID задачи
 * @param string $old_status Старый статус задачи (например, 'pending', 'in_progress')
 * @param string $new_status Новый статус задачи (например, 'in_progress', 'completed')
 * @return array|bool Результат отправки или false в случае ошибки/отсутствия данных
 */
function sendTaskStatusNotification($task_id, $old_status, $new_status)
{
    global $pdo;

    // Получаем данные задачи, исполнителя и создателя (для уведомления)
    // Также получаем информацию о связанной бухгалтерской записи заказа
    $stmt = $pdo->prepare("
        SELECT t.*,
               assigned.name as assigned_name, assigned.telegram_chat_id as assigned_chat_id,
               creator.name as creator_name,
               -- Информация из бухгалтерской записи
               oa.client_name as related_order_client_name,
               oa.source as related_order_source,
               oa.order_id as related_site_order_id, -- ID связанного заказа с сайта
               oa.external_order_id as related_external_order_id -- ID связанного внешнего заказа
        FROM tasks t
        LEFT JOIN users assigned ON t.assigned_to = assigned.id
        LEFT JOIN users creator ON t.created_by = creator.id
        LEFT JOIN orders_accounting oa ON t.related_order_accounting_id = oa.id -- Замените orders_accounting на реальное имя
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        error_log("sendTaskStatusNotification: Task with ID {$task_id} not found.");
        return false;
    }

    // Проверка пользовательских предпочтений
    $prefs = getUserNotificationPrefs((int) $task['assigned_to']);
    if (!$prefs['receive_task_status']) {
        return false;
    }

    $telegram = getTelegramBot();

    $status_names = [
        'pending' => '⏳ В ожидании',
        'in_progress' => '🔄 В работе',
        'completed' => '✅ Завершено',
        'cancelled' => '❌ Отменено'
    ];

    $old_status_name = $status_names[$old_status] ?? $old_status;
    $new_status_name = $status_names[$new_status] ?? $new_status;

    // Формируем сообщение
    $message = "📋 <b>Статус задачи изменён!</b>\n\n";
    $message .= "📝 <b>Заголовок:</b> {$task['title']}\n";
    $message .= "🆔 <b>ID задачи:</b> #{$task['id']}\n";
    $message .= "🔄 <b>Статус:</b> {$old_status_name} → {$new_status_name}\n\n";

    // --- НОВОЕ: Добавление информации о связанной бухгалтерской записи заказа ---
    if (!empty($task['related_order_accounting_id'])) {
        $order_type_text = ($task['related_order_source'] === 'site') ? 'заказа с сайта' : 'внешнего заказа';
        $order_id_to_link = ($task['related_order_source'] === 'site') ? $task['related_site_order_id'] : $task['related_external_order_id'];
        $order_details_url = ($task['related_order_source'] === 'site') ? "/admin/order/details.php?id={$task['related_site_order_id']}" : "/admin/order/external_details.php?id={$task['related_external_order_id']}";

        $message .= "📦 <b>Связанный {$order_type_text}:</b> #{$order_id_to_link} ";
        if (!empty($task['related_order_client_name'])) {
            $message .= "(Клиент: {$task['related_order_client_name']}) ";
        }
        $order_link = "https://{$_SERVER['HTTP_HOST']}{$order_details_url}";
        $message .= "\n🔗 Подробнее: {$order_link}\n\n";
    }
    // --- КОНЕЦ НОВОГО ---

    $message .= "👤 <b>Обновил:</b> {$task['creator_name']}\n";
    $message .= "🎯 <b>Исполнитель:</b> {$task['assigned_name']}\n\n";
    $message .= "🌐 Посмотреть задачу: https://{$_SERVER['HTTP_HOST']}/admin/tasks";

    // Канал доставки согласно предпочтениям
    $result = false;
    if (in_array($prefs['pref_channel'], ['telegram', 'both'], true) && !empty($task['assigned_chat_id'])) {
        $result = $telegram->sendToGroupAndUser($task['assigned_chat_id'], $message, 'HTML');
    }
    if (!$result && in_array($prefs['pref_channel'], ['email', 'both'], true)) {
        sendEmailFallbackToUserId((int) $task['assigned_to'], 'Статус задачи изменён', buildTaskEmailHtml($task, 'Статус задачи изменён'), strip_tags($message));
    }

    return $result;
}

/**
 * Send task assignment notification
 */
function sendTaskAssignmentNotification($task_id)
{
    global $pdo;

    // --- НОВОЕ: Изменен запрос для получения информации о связанной бухгалтерской записи заказа ---
    $stmt = $pdo->prepare("
        SELECT t.*,
               assigned.name as assigned_name, assigned.telegram_chat_id as assigned_chat_id,
               creator.name as creator_name,
               -- Информация из бухгалтерской записи
               oa.client_name as related_order_client_name,
               oa.source as related_order_source,
               oa.order_id as related_site_order_id, -- ID связанного заказа с сайта
               oa.external_order_id as related_external_order_id -- ID связанного внешнего заказа
        FROM tasks t
        LEFT JOIN users assigned ON t.assigned_to = assigned.id
        LEFT JOIN users creator ON t.created_by = creator.id
        LEFT JOIN orders_accounting oa ON t.related_order_accounting_id = oa.id -- Замените orders_accounting на реальное имя
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        return false;
    }

    $telegram = getTelegramBot();

    if ($task['assigned_to'] && $task['assigned_chat_id']) {
        // Задача назначена конкретному пользователю
        $assigned_user = [
            'name' => $task['assigned_name'],
            'telegram_chat_id' => $task['assigned_chat_id']
        ];
        $creator_user = ['name' => $task['creator_name']];

        // Учитываем предпочтения получателя
        $prefs = getUserNotificationPrefs((int) $task['assigned_to']);
        $res = false;
        if (in_array($prefs['pref_channel'], ['telegram', 'both'], true) && !empty($task['assigned_chat_id']) && $prefs['receive_task_created']) {
            $res = $telegram->sendTaskAssignment($task, $assigned_user, $creator_user);
        }
        if ((!$res || in_array($prefs['pref_channel'], ['email'], true)) && $prefs['receive_task_created']) {
            sendEmailFallbackToUserId(
                $task['assigned_to'],
                'Новая задача назначена',
                buildTaskEmailHtml($task, 'Новая задача назначена'),
                'Вам назначена новая задача'
            );
        }
        return $res;
    } else {
        // Общая задача - отправляем уведомления всем администраторам и менеджерам
        $creator_user = ['name' => $task['creator_name']];
        // Отправляем согласно предпочтениям пользователей
        $results = [];
        $stmt = $pdo->prepare("SELECT id, name, email, telegram_chat_id FROM users WHERE role IN ('admin','manager') AND is_blocked=0");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $u) {
            $prefs = getUserNotificationPrefs((int) $u['id']);
            if (!$prefs['receive_task_created'])
                continue;
            $sent = false;
            if (in_array($prefs['pref_channel'], ['telegram', 'both'], true) && !empty($u['telegram_chat_id'])) {
                $tasks_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bzkprint.ru') . '/admin/tasks';
                $kb = ['inline_keyboard' => [[['text' => '🔗 Перейти к задачам', 'url' => $tasks_url]]]];
                $sent = $telegram->sendToGroupAndUser($u['telegram_chat_id'], "📋 Новая общая задача", 'HTML', $kb);
            }
            if ((!$sent || in_array($prefs['pref_channel'], ['email'], true)) && !empty($u['email'])) {
                sendEmailFallbackToUser($u['email'], $u['name'], 'Новая общая задача', buildTaskEmailHtml($task, 'Новая общая задача'), 'Создана новая общая задача');
            }
            $results[] = ['user_id' => $u['id'], 'sent' => $sent];
        }
        return $results;
    }
}

/**
 * Уведомление о новом заказе с сайта (для админов/менеджеров) с фоллбеком на email.
 */
function sendNewSiteOrderNotification($order_id)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([(int) $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order)
            return false;

        $contact = json_decode($order['contact_info'] ?? '{}', true);
        $client_name = $contact['name'] ?? 'Клиент';
        $email = $contact['email'] ?? '';
        $phone = $contact['phone'] ?? '';
        $urgent = !empty($order['is_urgent']);

        // Найдём назначенного менеджера чата, если есть
        $stmt = $pdo->prepare("SELECT assigned_user_id FROM order_chats WHERE order_id = ? LIMIT 1");
        $stmt->execute([(int) $order_id]);
        $chat_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $assigned_user_id = $chat_row['assigned_user_id'] ?? null;

        $orders_link = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bzkprint.ru') . '/admin/orders';
        $order_link = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bzkprint.ru') . '/admin/order/details.php?id=' . (int) $order_id;

        $message = "🆕 <b>Новый заказ с сайта</b>\n\n";
        $message .= "#{$order_id} • Клиент: {$client_name}\n";
        if ($phone)
            $message .= "📞 {$phone}\n";
        if ($email)
            $message .= "✉️ {$email}\n";
        $message .= "💰 Сумма: " . number_format((float) $order['total_price'], 0, '', ' ') . " ₽\n";
        if ($urgent)
            $message .= "⚠️ Срочный заказ\n";
        $message .= "\n🔗 Открыть: {$order_link}";

        $telegram = getTelegramBot();

        // Сбор получателей
        $stmt = $pdo->prepare("SELECT id, name, email, telegram_chat_id FROM users WHERE role IN ('admin','manager') AND is_blocked=0");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $u) {
            $prefs = getUserNotificationPrefs((int) $u['id']);
            if (!$prefs['receive_new_order'])
                continue;
            $text = $message;
            if ($assigned_user_id && (int) $u['id'] === (int) $assigned_user_id) {
                $text = "✅ <b>Вы выбраны для этого заказа</b>\n\n" . $text;
            }

            if (in_array($prefs['pref_channel'], ['telegram', 'both'], true) && !empty($u['telegram_chat_id'])) {
                // добавим кнопку "Заказы"
                $reply_markup = ['inline_keyboard' => [[['text' => '📦 Заказы', 'url' => $orders_link]]]];
                $telegram->sendMessage($u['telegram_chat_id'], $text, 'HTML', $reply_markup);
            }
            if ((in_array($prefs['pref_channel'], ['email', 'both'], true) || empty($u['telegram_chat_id'])) && !empty($u['email'])) {
                // Email-фоллбек
                $subject = "Новый заказ на сайте #{$order_id}";
                $html = buildOrderEmailHtml($order_id, $client_name, $order['total_price'], $urgent, $order_link, $orders_link, $phone, $email);
                sendEmailFallbackToUser($u['email'], $u['name'], $subject, $html, strip_tags($text));
            }
        }

        return true;
    } catch (Exception $e) {
        error_log('sendNewSiteOrderNotification error: ' . $e->getMessage());
        return false;
    }
}

// Возвращает prefs пользователя с дефолтами
function getUserNotificationPrefs($user_id)
{
    global $pdo;
    $defaults = [
        'receive_task_created' => 1,
        'receive_new_order' => 1,
        'receive_task_status' => 1,
        'pref_channel' => 'both',
        'show_task_buttons' => 1,
    ];
    try {
        $stmt = $pdo->prepare("SELECT receive_task_created, receive_new_order, receive_task_status, pref_channel, show_task_buttons FROM notification_prefs WHERE user_id = ?");
        $stmt->execute([(int) $user_id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return array_merge($defaults, $row);
        }
    } catch (Exception $e) { /* ignore */
    }
    return $defaults;
}

// ===== Email helpers (fallback) =====
function getMailer()
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? '';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USERNAME'] ?? '';
    $mail->Password = $_ENV['SMTP_PASSWORD'] ?? '';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = (int) ($_ENV['SMTP_PORT'] ?? 465);
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@example.com', $_ENV['SMTP_FROM_NAME'] ?? 'BZK PRINT');
    $mail->isHTML(true);
    return $mail;
}

function sendEmailFallbackToUser($email, $name, $subject, $html, $alt)
{
    try {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            return false;
        $mail = getMailer();
        $mail->addAddress($email, $name);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $alt;
        return $mail->send();
    } catch (Exception $e) {
        error_log('sendEmailFallbackToUser error: ' . $e->getMessage());
        return false;
    }
}

function sendEmailFallbackToUserId($user_id, $subject, $html, $alt)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ? AND email IS NOT NULL AND email != ''");
    $stmt->execute([(int) $user_id]);
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return sendEmailFallbackToUser($u['email'], $u['name'], $subject, $html, $alt);
    }
    return false;
}

function buildTaskEmailHtml($task, $title)
{
    $tasks_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bzkprint.ru') . '/admin/tasks';
    $desc = htmlspecialchars($task['description'] ?? '');
    return "<html><body style='font-family:Arial,sans-serif'>
        <h2>" . htmlspecialchars($title) . "</h2>
        <p><strong>Заголовок:</strong> " . htmlspecialchars($task['title'] ?? '') . "</p>
        <p><strong>Описание:</strong><br>" . nl2br($desc) . "</p>
        <p><a href='{$tasks_url}'>Перейти к задачам</a></p>
    </body></html>";
}

function buildOrderEmailHtml($order_id, $client_name, $total_price, $urgent, $order_link, $orders_link, $phone, $email)
{
    $urgent_text = $urgent ? 'Да' : 'Нет';
    $price = number_format((float) $total_price, 0, '', ' ');
    return "<html><body style='font-family:Arial,sans-serif'>
        <h2>Новый заказ с сайта #{$order_id}</h2>
        <p><strong>Клиент:</strong> " . htmlspecialchars($client_name) . "</p>
        <p><strong>Телефон:</strong> " . htmlspecialchars($phone) . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
        <p><strong>Сумма:</strong> {$price} ₽</p>
        <p><strong>Срочный:</strong> {$urgent_text}</p>
        <p><a href='" . htmlspecialchars($order_link) . "'>Открыть заказ</a> | <a href='" . htmlspecialchars($orders_link) . "'>Все заказы</a></p>
    </body></html>";
}
?>