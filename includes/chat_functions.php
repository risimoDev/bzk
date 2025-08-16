<?php
// includes/chat_functions.php

/**
 * Создает чат для заказа и назначает случайного менеджера/админа.
 *
 * @param PDO $pdo
 * @param int $order_id
 * @return int|false ID созданного чата или false в случае ошибки
 */
function create_chat_for_order($pdo, $order_id) {
    try {
        $pdo->beginTransaction();

        // 1. Проверяем, существует ли уже чат для этого заказа
        $stmt_check = $pdo->prepare("SELECT id FROM order_chats WHERE order_id = ?");
        $stmt_check->execute([$order_id]);
        if ($stmt_check->fetch()) {
            // Чат уже существует, откатываем транзакцию
            $pdo->rollBack();
            error_log("CHAT DEBUG: Chat for order $order_id already exists.");
            return false;
        }

        // 2. Получаем случайного менеджера или админа
        $stmt_manager = $pdo->prepare("
            SELECT id FROM users 
            WHERE role IN ('manager', 'admin') 
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt_manager->execute();
        $manager = $stmt_manager->fetch(PDO::FETCH_ASSOC);
        $assigned_user_id = $manager ? $manager['id'] : null;

        // 3. Создаем чат
        $stmt_create = $pdo->prepare("
            INSERT INTO order_chats (order_id, assigned_user_id) 
            VALUES (?, ?)
        ");
        $stmt_create->execute([$order_id, $assigned_user_id]);
        $chat_id = $pdo->lastInsertId();

        $pdo->commit();
        error_log("CHAT DEBUG: Chat created successfully with ID: $chat_id for order_id: $order_id");
        return $chat_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("CHAT ERROR creating chat for order $order_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Отправляет сообщение в чат.
 *
 * @param PDO $pdo
 * @param int $chat_id
 * @param int $user_id
 * @param string $message_text
 * @return int|false ID созданного сообщения или false в случае ошибки
 */
function send_message($pdo, $chat_id, $user_id, $message_text) {
    error_log("CHAT DEBUG: send_message called with chat_id=$chat_id, user_id=$user_id, message_length=" . strlen($message_text));
    
    if (empty(trim($message_text))) {
        error_log("CHAT ERROR: Attempt to send empty message.");
        return false; // Не отправляем пустые сообщения
    }
    
    // Ограничиваем длину сообщения
    $message_text = substr(trim($message_text), 0, 1000);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (chat_id, user_id, message) 
            VALUES (?, ?, ?)
        ");
        error_log("CHAT DEBUG: Preparing to execute INSERT query");
        $result = $stmt->execute([$chat_id, $user_id, $message_text]);
        
        if ($result) {
            $insert_id = $pdo->lastInsertId();
            error_log("CHAT INFO: Message inserted successfully with ID: " . $insert_id);
            
            // Проверим, действительно ли сообщение сохранилось
            $stmt_check = $pdo->prepare("SELECT id FROM chat_messages WHERE id = ?");
            $stmt_check->execute([$insert_id]);
            $saved_msg = $stmt_check->fetch();
            if ($saved_msg) {
                error_log("CHAT DEBUG: Confirmed message saved in DB with ID: " . $insert_id);
            } else {
                error_log("CHAT ERROR: Message with ID $insert_id was not found in DB after insertion!");
            }
            
            return $insert_id;
        } else {
            $error_info = $stmt->errorInfo();
            error_log("CHAT ERROR: Failed to insert message. Error info: " . print_r($error_info, true));
            return false;
        }
    } catch (Exception $e) {
        error_log("CHAT ERROR sending message to chat $chat_id by user $user_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Получает сообщения из чата, отсортированные по дате.
 *
 * @param PDO $pdo
 * @param int $chat_id
 * @param int $limit (опционально) Ограничение количества сообщений
 * @param int $offset (опционально) Смещение для пагинации
 * @return array Массив сообщений
 */
function get_chat_messages($pdo, $chat_id, $limit = 50, $offset = 0) {
    try {
        // --- ИСПРАВЛЕНО: Используем bindValue с PDO::PARAM_INT ---
        $sql = "
            SELECT cm.*, u.name as sender_name, u.role as sender_role
            FROM chat_messages cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.chat_id = ?
            ORDER BY cm.created_at ASC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $chat_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("CHAT DEBUG (get_chat_messages): Fetched " . count($result) . " messages for chat_id=$chat_id");
        return $result;
    } catch (Exception $e) {
        error_log("CHAT ERROR fetching messages for chat $chat_id: " . $e->getMessage());
        return [];
    }
}

/**
 * Получает информацию о чате по ID заказа.
 *
 * @param PDO $pdo
 * @param int $order_id
 * @return array|null Ассоциативный массив с данными чата или null
 */
function get_chat_by_order_id($pdo, $order_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT oc.*, u.name as assigned_user_name, u.role as assigned_user_role
            FROM order_chats oc
            LEFT JOIN users u ON oc.assigned_user_id = u.id
            WHERE oc.order_id = ?
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("CHAT ERROR fetching chat for order $order_id: " . $e->getMessage());
        return null;
    }
}

/**
 * Назначает менеджера/админа на чат.
 *
 * @param PDO $pdo
 * @param int $chat_id
 * @param int $user_id ID менеджера/админа
 * @return bool Успешность операции
 */
function assign_user_to_chat($pdo, $chat_id, $user_id) {
    // Проверяем, что пользователь - менеджер или админ
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !in_array($user['role'], ['manager', 'admin'])) {
        error_log("CHAT ERROR: Cannot assign user $user_id to chat $chat_id: User is not a manager or admin.");
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE order_chats SET assigned_user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([$user_id, $chat_id]);
        if ($result) {
            error_log("CHAT DEBUG: User $user_id successfully assigned to chat $chat_id");
        } else {
            error_log("CHAT ERROR: Failed to assign user $user_id to chat $chat_id");
        }
        return $result;
    } catch (Exception $e) {
        error_log("CHAT ERROR assigning user $user_id to chat $chat_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Получает список менеджеров и админов для выпадающего списка.
 *
 * @param PDO $pdo
 * @return array Массив пользователей с ролями manager/admin
 */
function get_managers_and_admins($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('manager', 'admin') ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("CHAT ERROR fetching managers and admins: " . $e->getMessage());
        return [];
    }
}

/**
 * Помечает сообщения в чате как прочитанные для конкретного пользователя.
 * Помечает все сообщения в чате, созданные до текущего момента, как прочитанные.
 *
 * @param PDO $pdo
 * @param int $chat_id
 * @param int $user_id
 * @return void
 */
function mark_messages_as_read($pdo, $chat_id, $user_id) {
    try {
        // Получаем ID сообщений в чате, которые еще не прочитаны пользователем
        $stmt_unread = $pdo->prepare("
            SELECT cm.id 
            FROM chat_messages cm
            LEFT JOIN chat_message_reads cmr ON cm.id = cmr.message_id AND cmr.user_id = ?
            WHERE cm.chat_id = ? AND cm.user_id != ? AND cmr.id IS NULL
        ");
        $stmt_unread->execute([$user_id, $chat_id, $user_id]);
        $unread_message_ids = $stmt_unread->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!empty($unread_message_ids)) {
            // Подготавливаем данные для множественной вставки
            $placeholders = str_repeat('(?,?),', count($unread_message_ids) - 1) . '(?,?)';
            $values = [];
            foreach ($unread_message_ids as $msg_id) {
                $values[] = $msg_id;
                $values[] = $user_id;
            }
            
            $sql = "INSERT IGNORE INTO chat_message_reads (message_id, user_id) VALUES $placeholders";
            $stmt_insert = $pdo->prepare($sql);
            $stmt_insert->execute($values);
            error_log("CHAT DEBUG: Marked " . count($unread_message_ids) . " messages as read for user $user_id in chat $chat_id");
        }
    } catch (Exception $e) {
        error_log("CHAT ERROR marking messages as read in chat $chat_id for user $user_id: " . $e->getMessage());
    }
}

/**
 * Получает количество непрочитанных сообщений в чате для пользователя.
 *
 * @param PDO $pdo
 * @param int $chat_id
 * @param int $user_id
 * @return int Количество непрочитанных сообщений
 */
function get_unread_message_count($pdo, $chat_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(cm.id) 
            FROM chat_messages cm
            LEFT JOIN chat_message_reads cmr ON cm.id = cmr.message_id AND cmr.user_id = ?
            WHERE cm.chat_id = ? AND cm.user_id != ? AND cmr.id IS NULL
        ");
        $stmt->execute([$user_id, $chat_id, $user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("CHAT ERROR counting unread messages in chat $chat_id for user $user_id: " . $e->getMessage());
        return 0;
    }
}

?>