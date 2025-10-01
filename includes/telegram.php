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
     * Генерация inline-клавиатуры со статусами
     */
    private function generateTaskStatusKeyboard($task_id, $current_status)
    {
        $statuses = [
            'pending'     => '⏳ В ожидании',
            'in_progress' => '🔄 В работе',
            'completed'   => '✅ Завершено',
            'cancelled'   => '❌ Отменено'
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

        if (preg_match('/^task_status_(\d+)_(\w+)$/', $data, $matches)) {
            $task_id = (int)$matches[1];
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
 * Send task assignment notification
 */
function sendTaskAssignmentNotification($task_id)
{
    global $pdo;

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

    if ($task['assigned_to'] && $task['assigned_chat_id']) {
        $assigned_user = [
            'name' => $task['assigned_name'],
            'telegram_chat_id' => $task['assigned_chat_id']
        ];
        $creator_user = ['name' => $task['creator_name']];

        return $telegram->sendTaskAssignment($task, $assigned_user, $creator_user);
    } else {
        $creator_user = ['name' => $task['creator_name']];
        return $telegram->sendTaskAssignment($task, ['name' => ''], $creator_user);
    }
}
