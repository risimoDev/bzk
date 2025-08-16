<?php
// admin/order/details.php
session_start();
$pageTitle = "Детали заказа";
include_once('../../includes/header.php');

// --- Добавлено: Подключение функций чата ---
include_once('../../includes/chat_functions.php');
// --- Конец добавленного кода ---

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');

$statuses = [
  'pending' => 'В ожидании',
  'processing' => 'В обработке',
  'shipped' => 'Отправлен',
  'delivered' => 'Доставлен',
  'cancelled' => 'Отменен',
  'completed' => 'Полностью готов'
];

$status_colors = [
  'pending' => 'bg-yellow-100 text-yellow-800',
  'processing' => 'bg-blue-100 text-blue-800',
  'shipped' => 'bg-purple-100 text-purple-800',
  'delivered' => 'bg-indigo-100 text-indigo-800',
  'cancelled' => 'bg-red-100 text-red-800',
  'completed' => 'bg-green-100 text-green-800'
];

$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: /admin/orders");
    exit();
}

// Получение информации о заказе
$stmt = $pdo->prepare("
    SELECT o.*, u.name AS user_name, u.email, u.phone 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: /admin/orders");
    exit();
}

// Получение товаров в заказе
$stmt = $pdo->prepare("
    SELECT oi.quantity, oi.price, p.name AS product_name, oi.attributes
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Функция для получения названий характеристик
function getAttributeNames($pdo, $attributes_json) {
  $attributes = json_decode($attributes_json, true);
  if (!is_array($attributes)) return '';
  
  $result = [];
  foreach ($attributes as $attribute_id => $value_id) {
      // Исправлено: ищем по value_id, а не attribute_id
      $stmt = $pdo->prepare("
          SELECT av.value, pa.name as attribute_name
          FROM attribute_values av 
          JOIN product_attributes pa ON av.attribute_id = pa.id 
          WHERE av.id = ? -- Здесь ищем по ID значения атрибута
      ");
      $stmt->execute([$value_id]); // Передаем $value_id
      $attr = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($attr) {
          $result[] = $attr['attribute_name'] . ': ' . $attr['value'];
      }
  }
  return implode(', ', $result);
}

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

// Обработка смены назначенного пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_user'])) {
    $new_user_id = intval($_POST['assigned_user_id'] ?? 0);
    if ($chat && $new_user_id > 0) {
        if (assign_user_to_chat($pdo, $chat['id'], $new_user_id)) {
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Менеджер успешно назначен.'];
            // Обновляем данные чата
            $chat = get_chat_by_order_id($pdo, $order_id);
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при назначении менеджера.'];
        }
    }
    // Перенаправляем, чтобы избежать повторной отправки при F5
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Получаем сообщения (если чат существует)
$messages = [];
if ($chat) {
    $messages = get_chat_messages($pdo, $chat['id']);
    // Помечаем сообщения как прочитанные
    mark_messages_as_read($pdo, $chat['id'], $_SESSION['user_id']);
}

// Получаем список менеджеров и админов для выпадающего списка
$managers_admins = get_managers_and_admins($pdo);
// --- Конец добавленного кода ---

// Обработка изменения статуса заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
  $new_status = $_POST['update_status'] ?? null;
  
  if ($new_status && in_array($new_status, array_keys($statuses))) {
      $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
      $stmt->execute([$new_status, $order_id]);
      
      // Обновляем данные заказа
      $order['status'] = $new_status;
      
      $_SESSION['notifications'][] = [
          'type' => 'success',
          'message' => 'Статус заказа успешно изменен.'
      ];
  } else {
      $_SESSION['notifications'][] = [
          'type' => 'error',
          'message' => 'Не удалось изменить статус заказа.'
      ];
  }
  // Перенаправляем, чтобы избежать повторной отправки при F5
  header("Location: " . $_SERVER['REQUEST_URI']);
  exit();
}

?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin/orders" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">
          Все заказы
        </a>
      </div>
    </div>

    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Детали заказа #<?php echo htmlspecialchars($order['id']); ?></h1>
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
      <!-- Основная информация -->
      <div class="lg:col-span-2 space-y-8">
        <!-- Информация о заказе -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4 mb-6">
            <div>
              <h2 class="text-2xl font-bold text-gray-800 mb-2">Информация о заказе</h2>
              <div class="flex items-center">
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                  <?php echo htmlspecialchars($statuses[$order['status']] ?? 'Неизвестно'); ?>
                </span>
                <span class="ml-3 text-gray-600">
                  Создан: <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                </span>
              </div>
            </div>
            
            <!-- Форма изменения статуса -->
            <form action="" method="POST" class="flex items-center">
              <select name="update_status" onchange="this.form.submit()" class="rounded-l-lg border-gray-300 focus:border-[#118568] focus:ring-[#17B890]">
                <?php foreach ($statuses as $status_key => $status_name): ?>
                  <option value="<?php echo $status_key; ?>" <?php echo $order['status'] === $status_key ? 'selected' : ''; ?>>
                    <?php echo $status_name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="px-4 py-2 bg-[#118568] text-white rounded-r-lg hover:bg-[#0f755a] transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </button>
            </form>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gray-50 rounded-2xl p-4">
              <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Клиент
              </h3>
              <div class="space-y-2">
                <p class="text-gray-700">
                  <span class="font-medium">Имя:</span> 
                  <?php echo htmlspecialchars($order['user_name'] ?? 'Не указано'); ?>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Email:</span> 
                  <?php echo htmlspecialchars($order['email'] ?? 'Не указано'); ?>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Телефон:</span> 
                  <?php echo htmlspecialchars($order['phone'] ?? 'Не указано'); ?>
                </p>
              </div>
            </div>

            <div class="bg-gray-50 rounded-2xl p-4">
              <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0L5 15.243V19a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4m8 0V7a4 4 0 00-8 0v4m8 0h-8" />
                </svg>
                Доставка
              </h3>
              <div class="space-y-2">
                <p class="text-gray-700">
                  <span class="font-medium">Адрес:</span> 
                  <?php echo htmlspecialchars($order['shipping_address'] ?? 'Не указано'); ?>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Контакт:</span> 
                  <?php 
                  $contact_info = json_decode($order['contact_info'], true);
                  echo htmlspecialchars($contact_info['name'] ?? 'Не указано');
                  ?>
                </p>
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
                    <div class="mb-3">
                      <div class="text-sm text-gray-600 mb-1">Характеристики:</div>
                      <div class="flex flex-wrap gap-2">
                        <?php 
                        $attributes_list = explode(', ', getAttributeNames($pdo, $item['attributes']));
                        foreach ($attributes_list as $attr): 
                          if (!empty($attr)):
                        ?>
                          <span class="px-2 py-1 bg-[#DEE5E5] text-gray-700 text-xs rounded-full">
                            <?php echo htmlspecialchars($attr); ?>
                          </span>
                        <?php 
                          endif;
                        endforeach; 
                        ?>
                      </div>
                    </div>
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
          
          <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="flex justify-end">
              <div class="text-right">
                <div class="text-gray-600">Итого:</div>
                <div class="text-2xl font-bold text-[#118568]">
                  <?php echo number_format($order['total_price'], 2, '.', ' '); ?> ₽
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Боковая панель -->
      <div class="space-y-8">
        <!-- Сводка заказа -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Сводка заказа</h2>
          
          <div class="space-y-4">
            <div class="flex justify-between">
              <span class="text-gray-600">Товары:</span>
              <span class="font-medium"><?php echo array_sum(array_column($order_items, 'quantity')); ?> шт.</span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-gray-600">Стоимость товаров:</span>
              <span class="font-medium"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> ₽</span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-gray-600">Доставка:</span>
              <span class="font-medium">Бесплатно</span>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
              <div class="flex justify-between text-lg font-bold">
                <span>К оплате:</span>
                <span class="text-[#118568]"><?php echo number_format($order['total_price'], 2, '.', ' '); ?> ₽</span>
              </div>
            </div>
          </div>
        </div>

        <!-- История статусов -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">История статусов</h2>
          
          <div class="space-y-4">
            <div class="flex items-start">
              <div class="w-8 h-8 rounded-full bg-[#118568] flex items-center justify-center mr-3 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <div>
                <div class="font-medium text-gray-800">Заказ создан</div>
                <div class="text-sm text-gray-600"><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></div>
              </div>
            </div>
            
            <div class="flex items-start">
              <div class="w-8 h-8 rounded-full <?php echo $order['status'] === 'pending' ? 'bg-[#118568]' : 'bg-gray-300'; ?> flex items-center justify-center mr-3 flex-shrink-0">
                <?php if ($order['status'] === 'pending'): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                <?php endif; ?>
              </div>
              <div>
                <div class="font-medium text-gray-800">В ожидании</div>
                <div class="text-sm text-gray-600">Ожидание подтверждения</div>
              </div>
            </div>
            
            <div class="flex items-start">
              <div class="w-8 h-8 rounded-full <?php echo in_array($order['status'], ['processing', 'shipped', 'delivered', 'completed']) ? 'bg-[#118568]' : 'bg-gray-300'; ?> flex items-center justify-center mr-3 flex-shrink-0">
                <?php if (in_array($order['status'], ['processing', 'shipped', 'delivered', 'completed'])): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                <?php endif; ?>
              </div>
              <div>
                <div class="font-medium text-gray-800">В обработке</div>
                <div class="text-sm text-gray-600">Заказ находится в работе</div>
              </div>
            </div>
            
            <div class="flex items-start">
              <div class="w-8 h-8 rounded-full <?php echo in_array($order['status'], ['shipped', 'delivered', 'completed']) ? 'bg-[#118568]' : 'bg-gray-300'; ?> flex items-center justify-center mr-3 flex-shrink-0">
                <?php if (in_array($order['status'], ['shipped', 'delivered', 'completed'])): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                <?php endif; ?>
              </div>
              <div>
                <div class="font-medium text-gray-800">Отправлен</div>
                <div class="text-sm text-gray-600">Заказ передан в доставку</div>
              </div>
            </div>
            
            <div class="flex items-start">
              <div class="w-8 h-8 rounded-full <?php echo $order['status'] === 'completed' ? 'bg-[#118568]' : 'bg-gray-300'; ?> flex items-center justify-center mr-3 flex-shrink-0">
                <?php if ($order['status'] === 'completed'): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                <?php endif; ?>
              </div>
              <div>
                <div class="font-medium text-gray-800">Завершен</div>
                <div class="text-sm text-gray-600">Заказ успешно выполнен</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- --- Добавлено: Новый раздел с чатом и управлением --- -->
    <div class="mt-12 bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="p-6 border-b border-[#DEE5E5]">
        <h2 class="text-2xl font-bold text-gray-800">Чат и управление заказом</h2>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 p-6">
        <!-- Управление чатом -->
        <div class="lg:col-span-1 space-y-6">
          <!-- Назначение менеджера -->
          <div class="bg-gray-50 rounded-2xl p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Назначить менеджера
            </h3>
            
            <?php if ($chat): ?>
              <form action="" method="POST" class="space-y-4">
                <select name="assigned_user_id" 
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
                  <option value="">Не назначен</option>
                  <?php foreach ($managers_admins as $user): ?>
                    <option value="<?php echo $user['id']; ?>" 
                      <?php echo ($chat['assigned_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($user['name']); ?> 
                      (<?php echo $user['role'] === 'admin' ? 'Админ' : 'Менеджер'; ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="assign_user" 
                        class="w-full px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300">
                  Назначить
                </button>
              </form>
              
              <?php if ($chat['assigned_user_name']): ?>
              <div class="mt-4 p-3 bg-[#DEE5E5] rounded-lg">
                <p class="text-sm text-gray-700">
                  <span class="font-medium">Текущий менеджер:</span> 
                  <span class="text-[#118568]"><?php echo htmlspecialchars($chat['assigned_user_name']); ?></span>
                  <?php if ($chat['assigned_user_role'] === 'admin'): ?>
                    <span class="px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded">(Админ)</span>
                  <?php endif; ?>
                </p>
              </div>
              <?php endif; ?>
            <?php else: ?>
              <p class="text-gray-600">Чат для этого заказа еще не создан.</p>
            <?php endif; ?>
          </div>
          
          <!-- Статистика чата -->
          <?php if ($chat): ?>
          <div class="bg-gray-50 rounded-2xl p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m4-8h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9a2 2 0 012-2zm8-4V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              Статистика чата
            </h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Сообщений:</span>
                <span class="font-medium"><?php echo count($messages); ?></span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Создан:</span>
                <span><?php echo date('d.m.Y H:i', strtotime($chat['created_at'])); ?></span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Обновлен:</span>
                <span><?php echo date('d.m.Y H:i', strtotime($chat['updated_at'])); ?></span>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Чат -->
        <div class="lg:col-span-2">
          <div class="bg-gray-50 rounded-2xl flex flex-col h-full">
            <!-- Заголовок чата -->
            <div class="p-4 border-b border-[#DEE5E5] bg-white rounded-t-2xl">
              <h3 class="text-lg font-bold text-gray-800">Чат по заказу #<?php echo $order_id; ?></h3>
            </div>
            
            <!-- Сообщения -->
            <div id="admin-chat-messages" class="flex-grow p-4 overflow-y-auto max-h-96 bg-white">
              <?php if (empty($messages)): ?>
                <div class="text-center text-gray-500 py-8">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                  </svg>
                  <p>Сообщений пока нет</p>
                  <p class="text-xs mt-1">Напишите первое сообщение клиенту</p>
                </div>
              <?php else: ?>
                <?php foreach ($messages as $message): 
                  $is_own_message = $message['user_id'] == $_SESSION['user_id'];
                  $message_time = date('H:i', strtotime($message['created_at']));
                ?>
                  <div class="mb-4 <?php echo $is_own_message ? 'text-right' : ''; ?>">
                    <?php if (!$is_own_message): ?>
                      <div class="text-xs text-gray-500 mb-1">
                        <?php echo htmlspecialchars($message['sender_name']); ?> 
                        <?php if ($message['sender_role'] === 'admin'): ?>
                          <span class="px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded">(Админ)</span>
                        <?php elseif ($message['sender_role'] === 'manager'): ?>
                          <span class="px-1 py-0.5 bg-purple-100 text-purple-800 text-xs rounded">(Менеджер)</span>
                        <?php else: ?>
                          <span class="px-1 py-0.5 bg-blue-100 text-blue-800 text-xs rounded">(Клиент)</span>
                        <?php endif; ?>
                        <span class="ml-1"><?php echo $message_time; ?></span>
                      </div>
                    <?php endif; ?>
                    
                    <div class="inline-block max-w-xs md:max-w-md px-4 py-2 rounded-2xl 
                      <?php 
                        if ($is_own_message) {
                          echo 'bg-[#118568] text-white rounded-tr-none';
                        } else {
                          echo 'bg-gray-200 text-gray-800 rounded-tl-none';
                        }
                      ?>">
                      <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                    </div>
                    
                    <?php if ($is_own_message): ?>
                      <div class="text-xs text-gray-500 mt-1 text-right"><?php echo $message_time; ?></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            
            <!-- Форма отправки сообщения -->
            <div class="p-4 border-t border-[#DEE5E5] bg-white rounded-b-2xl">
              <?php if ($chat): ?>
              <form id="admin-chat-form" class="flex gap-2">
                <input type="text" id="admin-message-text" 
                       class="flex-grow px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 text-sm"
                       placeholder="Написать клиенту..." required maxlength="1000">
                <button type="submit" id="admin-send-button" 
                        class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 flex items-center justify-center">
                  <svg id="admin-send-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                  </svg>
                  <svg id="admin-sending-icon" class="h-5 w-5 hidden animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </button>
              </form>
              <p class="text-xs text-gray-500 mt-2 text-center">Нажмите Enter для отправки</p>
              <?php else: ?>
              <p class="text-gray-500 text-center">Чат недоступен</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- --- Конец добавленного кода --- -->

  </div>
</main>

<!-- Добавлено: JavaScript для чата -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Чат для админки ---
    const adminForm = document.getElementById('admin-chat-form');
    const adminInput = document.getElementById('admin-message-text');
    const adminSendButton = document.getElementById('admin-send-button');
    const adminSendIcon = document.getElementById('admin-send-icon');
    const adminSendingIcon = document.getElementById('admin-sending-icon');
    const adminMessagesContainer = document.getElementById('admin-chat-messages');
    
    if (adminForm) {
        adminForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Предотвращаем стандартную отправку формы
            
            const messageText = adminInput.value.trim();
            if (!messageText) {
                alert('Сообщение не может быть пустым!');
                return;
            }
            
            if (messageText.length > 1000) {
                alert('Сообщение слишком длинное (максимум 1000 символов)!');
                return;
            }
            
            // Блокируем кнопку и показываем индикатор отправки
            adminSendButton.disabled = true;
            adminSendIcon.classList.add('hidden');
            adminSendingIcon.classList.remove('hidden');
            
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
                    updateAdminMessages(data.messages);
                    // Очищаем поле ввода
                    adminInput.value = '';
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
                adminSendButton.disabled = false;
                adminSendIcon.classList.remove('hidden');
                adminSendingIcon.classList.add('hidden');
            });
        });
    }
    
function updateAdminMessages(messages) {
    if (messages.length === 0) {
        adminMessagesContainer.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p>Сообщений пока нет</p>
                <p class="text-xs mt-1">Напишите первое сообщение клиенту</p>
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
            messagesHtml += `<div class="text-xs text-gray-500 mt-1 text-right">${messageTime}</div>`;
        }
        
        // Закрываем контейнер сообщения
        messagesHtml += `</div>`;
    });
    
    adminMessagesContainer.innerHTML = messagesHtml;
    // Прокручиваем вниз
    adminMessagesContainer.scrollTop = adminMessagesContainer.scrollHeight;
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

<?php include_once('../../includes/footer.php'); ?>