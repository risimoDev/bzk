<?php
session_start();
$pageTitle = "Карточка клиента";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Обработка одобрения корпоративного аккаунта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_corporate'])) {
    $request_id = intval($_POST['request_id']);
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    // Получаем данные запроса
    $stmt = $pdo->prepare("SELECT * FROM corporate_account_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        // Обновляем статус запроса
        $stmt = $pdo->prepare("UPDATE corporate_account_requests SET status = 'approved', admin_notes = ? WHERE id = ?");
        $stmt->execute([$admin_notes, $request_id]);
        
        // Обновляем пользователя как корпоративного
        $stmt = $pdo->prepare("UPDATE users SET is_corporate = 1, company_name = ?, inn = ?, kpp = ?, legal_address = ? WHERE id = ?");
        $stmt->execute([$request['company_name'], $request['inn'], $request['kpp'], $request['legal_address'], $request['user_id']]);
        
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Корпоративный аккаунт одобрен.'];
    }
    header("Location: client_card.php?id=" . $request['user_id']);
    exit();
}

// Обработка отклонения корпоративного аккаунта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_corporate'])) {
    $request_id = intval($_POST['request_id']);
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    // Обновляем статус запроса
    $stmt = $pdo->prepare("UPDATE corporate_account_requests SET status = 'rejected', admin_notes = ? WHERE id = ?");
    $stmt->execute([$admin_notes, $request_id]);
    
    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Запрос на корпоративный аккаунт отклонен.'];
    header("Location: client_card.php?id=" . $_GET['id']);
    exit();
}

// Обработка одобрения удаления аккаунта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_deletion'])) {
    $request_id = intval($_POST['deletion_request_id']);
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    // Получаем данные запроса
    $stmt = $pdo->prepare("SELECT * FROM account_deletion_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        // Обновляем статус запроса
        $stmt = $pdo->prepare("UPDATE account_deletion_requests SET status = 'approved', admin_notes = ? WHERE id = ?");
        $stmt->execute([$admin_notes, $request_id]);
        
        // Здесь можно добавить логику фактического удаления аккаунта или пометки его как удаленного
        // Для безопасности мы просто помечаем аккаунт как удаленный, а не удаляем его физически
        $stmt = $pdo->prepare("UPDATE users SET is_blocked = 1 WHERE id = ?");
        $stmt->execute([$request['user_id']]);
        
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Запрос на удаление аккаунта одобрен.'];
    }
    header("Location: client_card.php?id=" . $request['user_id']);
    exit();
}

// Обработка отклонения удаления аккаунта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_deletion'])) {
    $request_id = intval($_POST['deletion_request_id']);
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    // Обновляем статус запроса
    $stmt = $pdo->prepare("UPDATE account_deletion_requests SET status = 'rejected', admin_notes = ? WHERE id = ?");
    $stmt->execute([$admin_notes, $request_id]);
    
    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Запрос на удаление аккаунта отклонен.'];
    header("Location: client_card.php?id=" . $_GET['id']);
    exit();
}

// Получение ID клиента из параметров
$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($client_id <= 0) {
    header("Location: /admin/users");
    exit();
}

// Получение данных клиента
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    header("Location: /admin/users");
    exit();
}

// Получение всех заказов клиента
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение последнего заказа клиента
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$client_id]);
$last_order = $stmt->fetch(PDO::FETCH_ASSOC);

// Получение всех сообщений из формы контактов от этого клиента
$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE email = ? OR phone = ? ORDER BY created_at DESC");
$stmt->execute([$client->email, $client->phone]);
$contact_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение всех чатов по заказам клиента
$order_ids = array_column($orders, 'id');
$chat_messages = [];
if (!empty($order_ids)) {
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT cm.*, u.name as sender_name, oc.order_id 
                          FROM chat_messages cm 
                          JOIN users u ON cm.user_id = u.id 
                          JOIN order_chats oc ON cm.chat_id = oc.id 
                          WHERE oc.order_id IN ($placeholders) 
                          ORDER BY cm.created_at DESC");
    $stmt->execute($order_ids);
    $chat_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение запросов на корпоративный аккаунт
$stmt = $pdo->prepare("SELECT * FROM corporate_account_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$corporate_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение запросов на удаление аккаунта
$stmt = $pdo->prepare("SELECT * FROM account_deletion_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$account_deletion_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include_once('../includes/header.php'); ?>

<main class="container mx-auto px-4 py-8 max-w-7xl">
  <!-- Breadcrumbs -->
  <div class="flex justify-between items-center mb-8">
    <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
    <?php echo backButton(); ?>
  </div>

  <div class="text-center mb-12">
    <h1 class="text-4xl font-bold text-gray-800 mb-4">Карточка клиента</h1>
    <p class="text-lg text-gray-600">Подробная информация о клиенте</p>
    <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] mx-auto mt-4"></div>
  </div>

  <!-- Основная информация о клиенте -->
  <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($client['name']); ?></h2>
        <p class="text-gray-600">ID: <?php echo $client['id']; ?></p>
      </div>
      <div class="mt-4 md:mt-0">
        <?php if ($client['is_blocked']): ?>
          <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm">Заблокирован</span>
        <?php else: ?>
          <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">Активен</span>
        <?php endif; ?>
        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm ml-2">
          <?php 
          $roles = [
            'admin' => 'Администратор',
            'manager' => 'Менеджер',
            'user' => 'Клиент'
          ];
          echo $roles[$client['role']] ?? $client['role'];
          ?>
        </span>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Контактная информация</h3>
        <div class="space-y-2">
          <p><span class="font-medium">Email:</span> <?php echo htmlspecialchars($client['email']); ?></p>
          <p><span class="font-medium">Телефон:</span> <?php echo htmlspecialchars($client['phone'] ?? 'Не указан'); ?></p>
          <p><span class="font-medium">Дата рождения:</span> 
            <?php echo !empty($client['birthday']) ? date('d.m.Y', strtotime($client['birthday'])) : 'Не указана'; ?>
          </p>
          <p><span class="font-medium">Адрес доставки:</span> 
            <?php echo htmlspecialchars($client['shipping_address'] ?? 'Не указан'); ?>
          </p>
        </div>
      </div>

      <div>
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Дополнительная информация</h3>
        <div class="space-y-2">
          <p><span class="font-medium">Дата регистрации:</span> 
            <?php echo date('d.m.Y H:i', strtotime($client['created_at'])); ?>
          </p>
          <p><span class="font-medium">Последняя активность:</span> 
            <?php echo !empty($client['last_activity']) ? date('d.m.Y H:i', strtotime($client['last_activity'])) : 'Неизвестно'; ?>
          </p>
          <p><span class="font-medium">Статус онлайн:</span> 
            <?php echo $client['is_online'] ? 'Онлайн' : 'Офлайн'; ?>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Последний заказ -->
  <?php if ($last_order): ?>
  <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Последний заказ</h2>
    <div class="border border-gray-200 rounded-xl p-4">
      <div class="flex justify-between items-center mb-3">
        <div>
          <p class="font-medium">Заказ #<?php echo $last_order['id']; ?></p>
          <p class="text-gray-600 text-sm"><?php echo date('d.m.Y H:i', strtotime($last_order['created_at'])); ?></p>
        </div>
        <span class="px-3 py-1 rounded-full text-sm 
          <?php
          $status_classes = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'processing' => 'bg-blue-100 text-blue-800',
            'shipped' => 'bg-indigo-100 text-indigo-800',
            'delivered' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'completed' => 'bg-green-100 text-green-800'
          ];
          $status_texts = [
            'pending' => 'В ожидании',
            'processing' => 'В обработке',
            'shipped' => 'Отправлен',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменен',
            'completed' => 'Завершен'
          ];
          echo $status_classes[$last_order['status']] ?? 'bg-gray-100 text-gray-800';
          ?>">
          <?php echo $status_texts[$last_order['status']] ?? $last_order['status']; ?>
        </span>
      </div>
      <p class="font-medium">Сумма: <?php echo number_format($last_order['total_price'], 2, '.', ' '); ?> ₽</p>
      <p class="text-gray-600 text-sm mt-2">Адрес доставки: <?php echo htmlspecialchars($last_order['shipping_address']); ?></p>
      <div class="mt-4">
        <a href="order/details.php?id=<?php echo $last_order['id']; ?>" 
           class="inline-flex items-center px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-200">
          Подробнее
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Все заказы клиента -->
  <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Все заказы клиента (<?php echo count($orders); ?>)</h2>
    <?php if (!empty($orders)): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($orders as $order): ?>
              <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $order['id']; ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <?php echo number_format($order['total_price'], 2, '.', ' '); ?> ₽
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                    <?php echo $status_classes[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                    <?php echo $status_texts[$order['status']] ?? $order['status']; ?>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <a href="order/details.php?id=<?php echo $order['id']; ?>" 
                     class="text-[#118568] hover:text-[#0f755a]">Просмотр</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-600">У клиента пока нет заказов.</p>
    <?php endif; ?>
  </div>

  <!-- Сообщения из формы контактов -->
  <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Сообщения из формы контактов (<?php echo count($contact_messages); ?>)</h2>
    <?php if (!empty($contact_messages)): ?>
      <div class="space-y-4">
        <?php foreach ($contact_messages as $message): ?>
          <div class="border border-gray-200 rounded-xl p-4">
            <div class="flex justify-between items-start">
              <div>
                <p class="font-medium"><?php echo htmlspecialchars($message['name']); ?></p>
                <p class="text-gray-600 text-sm"><?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?></p>
              </div>
              <span class="px-2 py-1 rounded-full text-xs 
                <?php echo $message['status'] === 'read' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                <?php echo $message['status'] === 'read' ? 'Прочитано' : 'Новое'; ?>
              </span>
            </div>
            <p class="mt-2"><?php echo htmlspecialchars($message['message']); ?></p>
            <div class="mt-3 flex flex-wrap gap-2">
              <?php if (!empty($message['phone'])): ?>
                <span class="text-sm text-gray-600">Телефон: <?php echo htmlspecialchars($message['phone']); ?></span>
              <?php endif; ?>
              <?php if (!empty($message['preferred_contact'])): ?>
                <span class="text-sm text-gray-600">
                  Предпочтительный контакт: <?php echo htmlspecialchars($message['preferred_contact']); ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-gray-600">У клиента пока нет сообщений через форму контактов.</p>
    <?php endif; ?>
  </div>

  <!-- Сообщения в чатах по заказам -->
  <div class="bg-white rounded-2xl shadow-xl p-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Сообщения в чатах по заказам (<?php echo count($chat_messages); ?>)</h2>
    <?php if (!empty($chat_messages)): ?>
      <div class="space-y-4 max-h-96 overflow-y-auto">
        <?php foreach ($chat_messages as $msg): ?>
          <div class="border border-gray-200 rounded-xl p-4">
            <div class="flex justify-between items-start">
              <div>
                <p class="font-medium">
                  <?php echo htmlspecialchars($msg['sender_name']); ?> 
                  <span class="text-gray-600 text-sm">(Заказ #<?php echo $msg['order_id']; ?>)</span>
                </p>
                <p class="text-gray-600 text-sm"><?php echo date('d.m.Y H:i', strtotime($msg['created_at'])); ?></p>
              </div>
            </div>
            <p class="mt-2"><?php echo htmlspecialchars($msg['message']); ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-gray-600">У клиента пока нет сообщений в чатах по заказам.</p>
    <?php endif; ?>
  </div>

  <!-- Запросы на корпоративный аккаунт -->
  <div class="bg-white rounded-2xl shadow-xl p-6 mt-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Запросы на корпоративный аккаунт (<?php echo count($corporate_requests); ?>)</h2>
    <?php if (!empty($corporate_requests)): ?>
      <div class="space-y-6">
        <?php foreach ($corporate_requests as $request): ?>
          <div class="border border-gray-200 rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($request['company_name']); ?></h3>
                <p class="text-gray-600 text-sm"><?php echo date('d.m.Y H:i', strtotime($request['created_at'])); ?></p>
              </div>
              <span class="px-3 py-1 rounded-full text-sm 
                <?php
                $status_classes = [
                  'pending' => 'bg-yellow-100 text-yellow-800',
                  'approved' => 'bg-green-100 text-green-800',
                  'rejected' => 'bg-red-100 text-red-800'
                ];
                $status_texts = [
                  'pending' => 'Ожидает рассмотрения',
                  'approved' => 'Одобрен',
                  'rejected' => 'Отклонен'
                ];
                echo $status_classes[$request['status']] ?? 'bg-gray-100 text-gray-800';
                ?>">
                <?php echo $status_texts[$request['status']] ?? $request['status']; ?>
              </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <p class="font-medium">ИНН:</p>
                <p><?php echo htmlspecialchars($request['inn']); ?></p>
              </div>
              <?php if (!empty($request['kpp'])): ?>
              <div>
                <p class="font-medium">КПП:</p>
                <p><?php echo htmlspecialchars($request['kpp']); ?></p>
              </div>
              <?php endif; ?>
            </div>

            <div class="mb-4">
              <p class="font-medium">Юридический адрес:</p>
              <p><?php echo htmlspecialchars($request['legal_address']); ?></p>
            </div>

            <?php if (!empty($request['admin_notes'])): ?>
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
              <p class="font-medium">Комментарий администратора:</p>
              <p><?php echo htmlspecialchars($request['admin_notes']); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($request['status'] === 'pending'): ?>
            <div class="border-t border-gray-200 pt-4">
              <form method="POST" class="space-y-4">
                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                <div>
                  <label class="block text-gray-700 font-medium mb-2">Комментарий администратора</label>
                  <textarea name="admin_notes" rows="3" 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                            placeholder="Добавьте комментарий к решению"></textarea>
                </div>
                <div class="flex gap-3">
                  <button type="submit" name="approve_corporate"
                          class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors duration-300 font-medium">
                    Одобрить
                  </button>
                  <button type="submit" name="reject_corporate"
                          class="px-6 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 font-medium">
                    Отклонить
                  </button>
                </div>
              </form>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-gray-600">У клиента пока нет запросов на корпоративный аккаунт.</p>
    <?php endif; ?>
  </div>

  <!-- Запросы на удаление аккаунта -->
  <div class="bg-white rounded-2xl shadow-xl p-6 mt-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Запросы на удаление аккаунта (<?php echo count($account_deletion_requests); ?>)</h2>
    <?php if (!empty($account_deletion_requests)): ?>
      <div class="space-y-6">
        <?php foreach ($account_deletion_requests as $request): ?>
          <div class="border border-gray-200 rounded-xl p-6">
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="text-lg font-bold text-gray-800">Запрос на удаление аккаунта</h3>
                <p class="text-gray-600 text-sm"><?php echo date('d.m.Y H:i', strtotime($request['created_at'])); ?></p>
              </div>
              <span class="px-3 py-1 rounded-full text-sm 
                <?php
                $status_classes = [
                  'pending' => 'bg-yellow-100 text-yellow-800',
                  'approved' => 'bg-green-100 text-green-800',
                  'rejected' => 'bg-red-100 text-red-800'
                ];
                $status_texts = [
                  'pending' => 'Ожидает рассмотрения',
                  'approved' => 'Одобрен',
                  'rejected' => 'Отклонен'
                ];
                echo $status_classes[$request['status']] ?? 'bg-gray-100 text-gray-800';
                ?>">
                <?php echo $status_texts[$request['status']] ?? $request['status']; ?>
              </span>
            </div>

            <?php if (!empty($request['reason'])): ?>
            <div class="mb-4">
              <p class="font-medium">Причина удаления:</p>
              <p><?php echo htmlspecialchars($request['reason']); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($request['admin_notes'])): ?>
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
              <p class="font-medium">Комментарий администратора:</p>
              <p><?php echo htmlspecialchars($request['admin_notes']); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($request['status'] === 'pending'): ?>
            <div class="border-t border-gray-200 pt-4">
              <form method="POST" class="space-y-4">
                <input type="hidden" name="deletion_request_id" value="<?php echo $request['id']; ?>">
                <div>
                  <label class="block text-gray-700 font-medium mb-2">Комментарий администратора</label>
                  <textarea name="admin_notes" rows="3" 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                            placeholder="Добавьте комментарий к решению"></textarea>
                </div>
                <div class="flex gap-3">
                  <button type="submit" name="approve_deletion"
                          class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors duration-300 font-medium">
                    Одобрить удаление
                  </button>
                  <button type="submit" name="reject_deletion"
                          class="px-6 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 font-medium">
                    Отклонить
                  </button>
                </div>
              </form>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-gray-600">У клиента пока нет запросов на удаление аккаунта.</p>
    <?php endif; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>