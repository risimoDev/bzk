<?php
/**
 * Telegram Bot functionality for sending task notifications
 * Add your bot token and configure chat IDs in your .env file
 */

class TelegramBot
{
    private $bot_token;
    private $api_url;

    public function __construct()
    {
        // Get bot token from environment variables
        $this->bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        $this->api_url = "https://api.telegram.org/bot{$this->bot_token}/";
    }

    /**
     * Send message to group chat as well
     */
    public function sendToGroupAndUser($user_chat_id, $message, $parse_mode = 'HTML')
    {
        $results = [];

        // Send to user
        if (!empty($user_chat_id)) {
            $results['user'] = $this->sendMessage($user_chat_id, $message, $parse_mode);
        }

        // Send to group chat if configured
        $group_chat_id = $_ENV['TELEGRAM_GROUP_CHAT_ID'] ?? '';
        if (!empty($group_chat_id)) {
            $results['group'] = $this->sendMessage($group_chat_id, $message, $parse_mode);
        }

        return $results;
    }

    /**
     * Send message to Telegram chat
     */
    public function sendMessage($chat_id, $message, $parse_mode = 'HTML')
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

        // Add task items if available
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

        if (!empty($task_data['due_date'])) {
            $due_date = date('d.m.Y H:i', strtotime($task_data['due_date']));
            $message .= "⏰ <b>Срок выполнения:</b> {$due_date}\n";
        }

        $message .= "👤 <b>Создал:</b> {$creator_user['name']}\n";
        $message .= "🎯 <b>Исполнитель:</b> {$assigned_user['name']}\n";
        $message .= "🆔 <b>ID задачи:</b> #{$task_data['id']}\n\n";
        $message .= "🌐 Посмотреть все задачи: https://{$_SERVER['HTTP_HOST']}/admin/tasks";

        return $this->sendToGroupAndUser($assigned_user['telegram_chat_id'], $message);
    }

    /**
     * Send task status update notification
     */
    public function sendTaskStatusUpdate($task_data, $user, $old_status, $new_status)
    {
        if (empty($user['telegram_chat_id'])) {
            return false;
        }

        $status_emoji = [
            'pending' => '⏳',
            'in_progress' => '🔄',
            'completed' => '✅',
            'cancelled' => '❌'
        ];

        $status_names = [
            'pending' => 'В ожидании',
            'in_progress' => 'В работе',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена'
        ];

        $old_emoji = $status_emoji[$old_status] ?? '⚪';
        $new_emoji = $status_emoji[$new_status] ?? '⚪';
        $old_name = $status_names[$old_status] ?? $old_status;
        $new_name = $status_names[$new_status] ?? $new_status;

        $message = "🔄 <b>Статус задачи изменен!</b>\n\n";
        $message .= "📝 <b>Задача:</b> {$task_data['title']}\n";
        $message .= "🆔 <b>ID:</b> #{$task_data['id']}\n\n";
        $message .= "{$old_emoji} <b>Было:</b> {$old_name}\n";
        $message .= "{$new_emoji} <b>Стало:</b> {$new_name}\n\n";
        $message .= "🌐 Посмотреть задачу: https://{$_SERVER['HTTP_HOST']}/admin/tasks";

        return $this->sendMessage($user['telegram_chat_id'], $message);
    }

    /**
     * Send general task notification to admins
     */
    public function sendGeneralTaskNotification($task_data, $creator_user, $admin_chat_ids = [])
    {
        if (empty($admin_chat_ids)) {
            // Get admin chat IDs from database if not provided
            global $pdo;
            $stmt = $pdo->prepare("SELECT telegram_chat_id FROM users WHERE role IN ('admin', 'manager') AND telegram_chat_id IS NOT NULL");
            $stmt->execute();
            $admin_chat_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $priority_emoji = [
            'low' => '🟢',
            'medium' => '🟡',
            'high' => '🟠',
            'urgent' => '🔴'
        ];

        $emoji = $priority_emoji[$task_data['priority']] ?? '⚪';

        $message = "📢 <b>Создана общая задача!</b>\n\n";
        $message .= "{$emoji} <b>Приоритет:</b> " . ucfirst($task_data['priority']) . "\n";
        $message .= "📝 <b>Заголовок:</b> {$task_data['title']}\n\n";

        // Add task items if available
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

        if (!empty($task_data['due_date'])) {
            $due_date = date('d.m.Y H:i', strtotime($task_data['due_date']));
            $message .= "⏰ <b>Срок выполнения:</b> {$due_date}\n";
        }

        $message .= "👤 <b>Создал:</b> {$creator_user['name']}\n";
        $message .= "🆔 <b>ID задачи:</b> #{$task_data['id']}\n\n";
        $message .= "🌐 Посмотреть все задачи: https://{$_SERVER['HTTP_HOST']}/admin/tasks";

        $results = [];

        // Send to all admin users
        foreach ($admin_chat_ids as $chat_id) {
            $results[] = $this->sendMessage($chat_id, $message);
        }

        // Also send to group chat if configured
        $group_chat_id = $_ENV['TELEGRAM_GROUP_CHAT_ID'] ?? '';
        if (!empty($group_chat_id)) {
            $results['group'] = $this->sendMessage($group_chat_id, $message);
        }

        return $results;
    }

    /**
     * Send task comment notification
     */
    public function sendTaskComment($task_data, $comment_data, $recipient_user, $comment_user)
    {
        if (empty($recipient_user['telegram_chat_id'])) {
            return false;
        }

        $message = "💬 <b>Новый комментарий к задаче!</b>\n\n";
        $message .= "📝 <b>Задача:</b> {$task_data['title']}\n";
        $message .= "🆔 <b>ID:</b> #{$task_data['id']}\n\n";
        $message .= "👤 <b>Автор:</b> {$comment_user['name']}\n";
        $message .= "💬 <b>Комментарий:</b>\n{$comment_data['comment']}\n\n";
        $message .= "🌐 Посмотреть задачу: https://{$_SERVER['HTTP_HOST']}/admin/tasks";

        return $this->sendMessage($recipient_user['telegram_chat_id'], $message);
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
 * Send task assignment notification
 */
function sendTaskAssignmentNotification($task_id)
{
    global $pdo;

    // Get task data with user information
    $stmt = $pdo->prepare("
        SELECT t.*, 
               assigned.name as assigned_name, assigned.telegram_chat_id as assigned_chat_id,
               creator.name as creator_name
        FROM tasks t
        LEFT JOIN users assigned ON t.assigned_to = assigned.id
        LEFT JOIN users creator ON t.created_by = creator.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        return false;
    }

    $telegram = getTelegramBot();

    // Send to assigned user if specific assignment
    if ($task['assigned_to'] && $task['assigned_chat_id']) {
        $assigned_user = [
            'name' => $task['assigned_name'],
            'telegram_chat_id' => $task['assigned_chat_id']
        ];
        $creator_user = ['name' => $task['creator_name']];

        return $telegram->sendTaskAssignment($task, $assigned_user, $creator_user);
    }
    // Send to all admins/managers if general task
    else {
        $creator_user = ['name' => $task['creator_name']];
        return $telegram->sendGeneralTaskNotification($task, $creator_user);
    }
}

/**
 * Send task status update notification
 */
function sendTaskStatusNotification($task_id, $old_status, $new_status)
{
    global $pdo;

    // Get task data with assigned user information
    $stmt = $pdo->prepare("
        SELECT t.*, u.name as assigned_name, u.telegram_chat_id
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task || !$task['telegram_chat_id']) {
        return false;
    }

    $telegram = getTelegramBot();
    $user = [
        'name' => $task['assigned_name'],
        'telegram_chat_id' => $task['telegram_chat_id']
    ];

    return $telegram->sendTaskStatusUpdate($task, $user, $old_status, $new_status);
}
?>