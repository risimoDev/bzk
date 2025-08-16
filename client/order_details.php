<?php
// client/order_details.php
session_start();
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/session_check.php'; // Для проверки авторизации
include_once __DIR__ . '/../includes/chat_functions.php'; // Для функций чата

// Проверка авторизации
if (!$is_logged_in) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пожалуйста, войдите в систему.'];
    header("Location: /login");
    exit();
}

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header("Location: /client/orders");
    exit();
}

// Получаем информацию о заказе и проверяем, что он принадлежит текущему пользователю
$stmt = $pdo->prepare("
    SELECT o.*, oa.status as payment_status, oa.estimated_expense
    FROM orders o
    LEFT JOIN orders_accounting oa ON o.id = oa.order_id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: /client/orders");
    exit();
}

$pageTitle = "Заказ #" . $order['id'];
include_once __DIR__ . '/../includes/header.php';

// Получаем товары в заказе
$stmt_items = $pdo->prepare("
    SELECT oi.*, p.name as product_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt_items->execute([$order_id]);
$order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// --- Добавлено: Интеграция чата ---
// Получаем информацию о чате
$chat = get_chat_by_order_id($pdo, $order_id);
if (!$chat) {
    // Если чат не существует (например, для старых заказов), создаем его
    $chat_id = create_chat_for_order($pdo, $order_id);
    if ($chat_id) {
        $chat = get_chat_by_order_id($pdo, $order_id);
    }
}

// Получаем сообщения (если чат существует)
$messages = [];
if ($chat) {
    $messages = get_chat_messages($pdo, $chat['id']);
    // Помечаем сообщения как прочитанные
    mark_messages_as_read($pdo, $chat['id'], $_SESSION['user_id']);
}
// --- Конец добавленного кода ---
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <?php echo backButton(); ?>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Детали заказа #<?php echo htmlspecialchars($order['id']); ?></h1>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
    </div>

    <!-- Уведомления -->
    <?php if (isset($_SESSION['notifications'])): ?>
        <?php foreach ($_SESSION['notifications'] as $notification): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <?php echo htmlspecialchars($notification['message']); ?>
            </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['notifications']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Левая колонка: Детали заказа -->
      <div class="lg:col-span-2 space-y-8">
        <!-- Информация о заказе -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Информация о заказе</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Дата и статус
              </h3>
              <div class="space-y-2">
                <p class="text-gray-700">
                  <span class="font-medium">Создан:</span> 
                  <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Статус:</span> 
                  <span class="px-2 py-1 rounded-full text-xs font-medium 
                    <?php 
                    $status_classes = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'processing' => 'bg-blue-100 text-blue-800',
                        'shipped' => 'bg-purple-100 text-purple-800',
                        'delivered' => 'bg-indigo-100 text-indigo-800',
                        'cancelled' => 'bg-red-100 text-red-800',
                        'completed' => 'bg-green-100 text-green-800'
                    ];
                    echo $status_classes[$order['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>">
                    <?php 
                    $status_names = [
                        'pending' => 'В ожидании',
                        'processing' => 'В обработке',
                        'shipped' => 'Отправлен',
                        'delivered' => 'Доставлен',
                        'cancelled' => 'Отменен',
                        'completed' => 'Завершен'
                    ];
                    echo $status_names[$order['status']] ?? $order['status'];
                    ?>
                  </span>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Оплата:</span> 
                  <span class="px-2 py-1 rounded-full text-xs font-medium 
                    <?php 
                    $payment_status_classes = [
                        'unpaid' => 'bg-red-100 text-red-800',
                        'partial' => 'bg-yellow-100 text-yellow-800',
                        'paid' => 'bg-green-100 text-green-800'
                    ];
                    echo $payment_status_classes[$order['payment_status']] ?? 'bg-gray-100 text-gray-800';
                    ?>">
                    <?php 
                    $payment_status_names = [
                        'unpaid' => 'Не оплачен',
                        'partial' => 'Частично оплачен',
                        'paid' => 'Оплачен'
                    ];
                    echo $payment_status_names[$order['payment_status']] ?? $order['payment_status'];
                    ?>
                  </span>
                </p>
              </div>
            </div>
            
            <div>
              <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0L5 15.243V19a2 2 0 01-2 2H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2h-3.586l-4.243-4.243z" />
                </svg>
                Контактная информация
              </h3>
              <?php 
              $contact_info = json_decode($order['contact_info'], true);
              ?>
              <div class="space-y-2">
                <p class="text-gray-700">
                  <span class="font-medium">Имя:</span> 
                  <?php echo htmlspecialchars($contact_info['name'] ?? 'Не указано'); ?>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Email:</span> 
                  <?php echo htmlspecialchars($contact_info['email'] ?? 'Не указано'); ?>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Телефон:</span> 
                  <?php echo htmlspecialchars($contact_info['phone'] ?? 'Не указано'); ?>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Адрес:</span> 
                  <?php echo htmlspecialchars($order['shipping_address']); ?>
                </p>
              </div>
            </div>
          </div>
          
          <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="flex justify-between items-center">
              <div class="text-gray-700">
                <span class="font-medium">Общая сумма:</span>
              </div>
              <div class="text-2xl font-bold text-[#118568]">
                <?php echo number_format($order['total_price'], 2, '.', ' '); ?> ₽
              </div>
            </div>
          </div>
        </div>

        <!-- Товары в заказе -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Товары в заказе</h2>
          
          <div class="space-y-4">
            <?php foreach ($order_items as $item): ?>
              <div class="flex flex-col sm:flex-row gap-4 p-4 bg-gray-50 rounded-2xl hover:bg-gray-100 transition-colors duration-300">
                <div class="flex-grow">
                  <h3 class="font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                  
                  <?php if (!empty($item['attributes'])): ?>
                    <?php 
                    $attributes = json_decode($item['attributes'], true);
                    if (is_array($attributes) && !empty($attributes)): ?>
                      <div class="mb-3">
                        <div class="text-sm text-gray-600 mb-1">Характеристики:</div>
                        <div class="flex flex-wrap gap-2">
                          <?php foreach ($attributes as $attr_id => $value_id): 
                              // Получаем название атрибута и значение (упрощенно)
                              $stmt_attr = $pdo->prepare("
                                  SELECT pa.name as attr_name, av.value as value_name
                                  FROM product_attributes pa
                                  JOIN attribute_values av ON av.attribute_id = pa.id
                                  WHERE pa.id = ? AND av.id = ?
                              ");
                              $stmt_attr->execute([$attr_id, $value_id]);
                              $attr_value = $stmt_attr->fetch(PDO::FETCH_ASSOC);
                              if ($attr_value):
                          ?>
                            <span class="px-2 py-1 bg-[#DEE5E5] text-gray-700 text-xs rounded-full">
                              <?php echo htmlspecialchars($attr_value['attr_name']); ?>: 
                              <?php echo htmlspecialchars($attr_value['value_name']); ?>
                            </span>
                          <?php 
                              endif;
                          endforeach; 
                          ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <div class="flex flex-wrap items-center gap-4 text-sm">
                    <span class="font-medium">Количество: <?php echo htmlspecialchars($item['quantity']); ?> шт.</span>
                    <span class="font-medium">Цена за единицу: <?php echo number_format($item['price'] / $item['quantity'], 2, '.', ' '); ?> ₽</span>
                  </div>
                </div>
                
                <div class="flex items-center sm:items-end sm:flex-col sm:justify-end">
                  <div class="text-lg font-bold text-[#118568]">
                    <?php echo number_format($item['price'], 2, '.', ' '); ?> ₽
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Правая колонка: Чат -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-3xl shadow-2xl flex flex-col h-full">
          <!-- Заголовок чата -->
          <div class="p-6 border-b border-[#DEE5E5]">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Чат по заказу</h2>
            <?php if ($chat && $chat['assigned_user_name']): ?>
            <div class="flex items-center text-sm text-gray-600">
              <span>Ваш менеджер: </span>
              <span class="ml-1 font-medium text-[#118568]">
                <?php echo htmlspecialchars($chat['assigned_user_name']); ?>
                <?php if ($chat['assigned_user_role'] === 'admin'): ?>
                  <span class="ml-1 px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded">(Админ)</span>
                <?php endif; ?>
              </span>
            </div>
            <?php else: ?>
            <div class="text-sm text-gray-500">Ожидание назначения менеджера...</div>
            <?php endif; ?>
          </div>
          
          <!-- Сообщения -->
<div id="chat-messages" class="flex-grow p-4 overflow-y-auto max-h-96 bg-white">
  <?php if (empty($messages)): ?>
    <div class="text-center text-gray-500 py-8">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
      </svg>
      <p>Сообщений пока нет</p>
      <p class="text-xs mt-1">Напишите первое сообщение</p>
    </div>
  <?php else: ?>
    <?php foreach ($messages as $message): 
      $is_own_message = $message['user_id'] == $_SESSION['user_id'];
      $message_time = date('H:i', strtotime($message['created_at']));
      // Определяем роль отправителя для цветовой индикации
      $sender_role_class = '';
      if ($message['sender_role'] === 'admin') {
          $sender_role_class = 'bg-red-100 text-red-800';
      } elseif ($message['sender_role'] === 'manager') {
          $sender_role_class = 'bg-purple-100 text-purple-800';
      } else {
          $sender_role_class = 'bg-blue-100 text-blue-800';
      }
    ?>
      <div class="mb-4 <?php echo $is_own_message ? 'text-right' : 'text-left'; ?>">
        <?php if (!$is_own_message): ?>
          <!-- Информация об отправителе для чужих сообщений -->
          <div class="text-xs text-gray-500 mb-1 flex items-center <?php echo $is_own_message ? 'justify-end' : 'justify-start'; ?>">
            <span><?php echo htmlspecialchars($message['sender_name']); ?></span>
            <span class="ml-1 px-1 py-0.5 <?php echo $sender_role_class; ?> text-xs rounded">
              <?php 
              echo $message['sender_role'] === 'admin' ? 'Админ' : 
                   ($message['sender_role'] === 'manager' ? 'Менеджер' : 'Клиент'); 
              ?>
            </span>
            <span class="ml-1"><?php echo $message_time; ?></span>
          </div>
        <?php endif; ?>
        
        <!-- Текст сообщения -->
        <div class="inline-block max-w-xs md:max-w-md px-4 py-3 rounded-2xl
          <?php 
            if ($is_own_message) {
              // Стили для сообщений пользователя (справа, зеленый)
              echo ' bg-[#118568] text-white rounded-tr-none';
            } else {
              // Стили для сообщений других пользователей (слева, серый)
              echo ' bg-gray-200 text-gray-800 rounded-tl-none';
            }
          ?>">
          <?php echo nl2br(htmlspecialchars($message['message'])); ?>
        </div>
        
        <?php if ($is_own_message): ?>
          <!-- Время для своих сообщений справа под сообщением -->
          <div class="text-xs text-gray-500 mt-1 text-right"><?php echo $message_time; ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
          
          <!-- Форма отправки сообщения -->
          <div class="p-4 border-t border-[#DEE5E5]">
            <form id="chat-form" class="flex gap-2">
              <input type="text" id="message-text" 
                     class="flex-grow px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 text-sm"
                     placeholder="Введите сообщение..." required maxlength="1000">
              <button type="submit" id="send-button" 
                      class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 flex items-center justify-center">
                <svg id="send-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                <svg id="sending-icon" class="h-5 w-5 hidden animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </button>
            </form>
            <p class="text-xs text-gray-500 mt-2 text-center">Нажмите Enter для отправки</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('chat-form');
    const input = document.getElementById('message-text');
    const sendButton = document.getElementById('send-button');
    const sendIcon = document.getElementById('send-icon');
    const sendingIcon = document.getElementById('sending-icon');
    const messagesContainer = document.getElementById('chat-messages');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Предотвращаем стандартную отправку формы
            
            const messageText = input.value.trim();
            if (!messageText) {
                alert('Сообщение не может быть пустым!');
                return;
            }
            
            if (messageText.length > 1000) {
                alert('Сообщение слишком длинное (максимум 1000 символов)!');
                return;
            }
            
            // Блокируем кнопку и показываем индикатор отправки
            sendButton.disabled = true;
            sendIcon.classList.add('hidden');
            sendingIcon.classList.remove('hidden');
            
            // Отправляем AJAX-запрос
            fetch('/ajax/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=<?php echo $order_id; ?>&message_text=' + encodeURIComponent(messageText)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Обновляем список сообщений
                    updateMessages(data.messages);
                    // Очищаем поле ввода
                    input.value = '';
                } else {
                    alert('Ошибка: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при отправке сообщения.');
            })
            .finally(() => {
                // Разблокируем кнопку и скрываем индикатор отправки
                sendButton.disabled = false;
                sendIcon.classList.remove('hidden');
                sendingIcon.classList.add('hidden');
            });
        });
    }
    
function updateMessages(messages) {
    if (messages.length === 0) {
        messagesContainer.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p>Сообщений пока нет</p>
                <p class="text-xs mt-1">Напишите первое сообщение</p>
            </div>
        `;
        return;
    }
    
    let messagesHtml = '';
    const currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    
    messages.forEach(msg => {
        const isOwnMessage = msg.user_id == currentUserId;
        const messageTime = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        // Открываем контейнер сообщения
        messagesHtml += `<div class="mb-4 ${isOwnMessage ? 'text-right' : ''}">`;
        
        // Для сообщений других пользователей показываем информацию об отправителе
        if (!isOwnMessage) {
            messagesHtml += `
                <div class="text-xs text-gray-500 mb-1">
                    ${escapeHtml(msg.sender_name)} 
            `;
            
            if (msg.sender_role === 'admin') {
                messagesHtml += `<span class="px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded">(Админ)</span>`;
            } else if (msg.sender_role === 'manager') {
                messagesHtml += `<span class="px-1 py-0.5 bg-purple-100 text-purple-800 text-xs rounded">(Менеджер)</span>`;
            } else {
                messagesHtml += `<span class="px-1 py-0.5 bg-blue-100 text-blue-800 text-xs rounded">(Клиент)</span>`;
            }
            
            messagesHtml += `<span class="ml-1">${messageTime}</span>`;
            messagesHtml += `</div>`; // Закрываем блок с информацией об отправителе
        }
        
        // Добавляем само сообщение
        messagesHtml += `
            <div class="inline-block max-w-xs md:max-w-md px-4 py-2 rounded-2xl 
                ${isOwnMessage ? 'bg-[#118568] text-white rounded-tr-none' : 'bg-gray-200 text-gray-800 rounded-tl-none'}">
                ${nl2br(escapeHtml(msg.message))}
            </div>
        `;
        
        // Для своих сообщений показываем время отправки снизу
        if (isOwnMessage) {
            messagesHtml += `<div class="text-xs text-gray-500 mt-1">${messageTime}</div>`;
        }
        
        // Закрываем контейнер сообщения
        messagesHtml += `</div>`;
    });
    
    messagesContainer.innerHTML = messagesHtml;
    // Прокручиваем вниз
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}
    
    // Вспомогательные функции
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '<',
            '>': '>',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function nl2br(str) {
        return str.replace(/\n/g, '<br>');
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>