<?php
// client/order_details.php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/chat_functions.php';

$user_id = $_SESSION['user_id'] ?? null;
if (empty($user_id) || (isset($is_logged_in) && !$is_logged_in)) {
  $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пожалуйста, войдите в систему.'];
  header("Location: /login");
  exit();
}

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($order_id <= 0) {
  header("Location: /client/orders");
  exit();
}

$stmt = $pdo->prepare("
    SELECT o.*, oa.status AS payment_status, oa.estimated_expense
    FROM orders o
    LEFT JOIN orders_accounting oa ON o.id = oa.order_id
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
  $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заказ не найден.'];
  header("Location: /client/orders");
  exit();
}

$pageTitle = "Заказ #" . $order['id'];

// Позиции (с учетом кастомных)
$stmt_items = $pdo->prepare("
    SELECT 
      oi.id AS item_id, oi.product_id, oi.is_custom, oi.item_name, oi.item_note,
      oi.quantity, oi.unit_price, oi.price, oi.attributes,
      p.name AS product_name, p.unit
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$stmt_items->execute([$order_id]);
$items_raw = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Собираем value_id'ы для атрибутов (только для позиций из каталога)
$value_ids = [];
$item_attributes_map = [];
foreach ($items_raw as $r) {
  $attrs = [];
  if (empty($r['is_custom']) && !empty($r['attributes'])) {
    $decoded = json_decode($r['attributes'], true);
    if (is_array($decoded)) {
      foreach ($decoded as $attr_id => $value_id) {
        $attr_id = (int) $attr_id;
        $value_id = (int) $value_id;
        if ($value_id > 0) {
          $attrs[$attr_id] = $value_id;
          $value_ids[$value_id] = $value_id;
        }
      }
    }
  }
  $item_attributes_map[$r['item_id']] = $attrs;
}

$value_map = [];
if (!empty($value_ids)) {
  $placeholders = implode(',', array_fill(0, count($value_ids), '?'));
  $sql = "
        SELECT av.id AS value_id, av.attribute_id, av.value AS value_name, pa.name AS attribute_name
        FROM attribute_values av
        LEFT JOIN product_attributes pa ON av.attribute_id = pa.id
        WHERE av.id IN ($placeholders)
    ";
  $stmt_vals = $pdo->prepare($sql);
  $stmt_vals->execute(array_values($value_ids));
  foreach ($stmt_vals->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $value_map[(int) $v['value_id']] = [
      'attribute_id' => (int) $v['attribute_id'],
      'attribute_name' => $v['attribute_name'],
      'value' => $v['value_name']
    ];
  }
}

$order_items = [];
foreach ($items_raw as $r) {
  $attrs_parsed = [];
  if ((int) $r['is_custom'] === 0) {
    $attrs_for_item = $item_attributes_map[$r['item_id']] ?? [];
    foreach ($attrs_for_item as $attr_id => $value_id) {
      if (isset($value_map[$value_id])) {
        $attrs_parsed[] = [
          'attribute_id' => $value_map[$value_id]['attribute_id'],
          'attribute_name' => $value_map[$value_id]['attribute_name'],
          'value_id' => $value_id,
          'value' => $value_map[$value_id]['value']
        ];
      } else {
        $attrs_parsed[] = [
          'attribute_id' => $attr_id,
          'attribute_name' => null,
          'value_id' => $value_id,
          'value' => null
        ];
      }
    }
  }

  $order_items[] = [
    'item_id' => (int) $r['item_id'],
    'is_custom' => (int) $r['is_custom'] === 1,
    'product_id' => $r['product_id'] ? (int) $r['product_id'] : null,
    'product_name' => $r['product_name'] ?: null,
    'item_name' => $r['item_name'] ?: null,
    'item_note' => $r['item_note'] ?: null,
    'quantity' => (int) $r['quantity'],
    'unit_price' => $r['unit_price'] !== null ? (float) $r['unit_price'] : null,
    'price' => (float) $r['price'],
    'unit' => $r['unit'] ?: 'шт.',
    'attributes' => $attrs_parsed
  ];
}

// Чат
$chat = get_chat_by_order_id($pdo, $order_id);
if (!$chat) {
  $created_chat_id = create_chat_for_order($pdo, $order_id);
  if ($created_chat_id)
    $chat = get_chat_by_order_id($pdo, $order_id);
}
$messages = [];
$messages_total = 0;
$messages_limit = 50;
$show_all = isset($_GET['show_all_messages']) && $_GET['show_all_messages'] == '1';
if ($chat) {
  $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE chat_id = ?");
  $stmt_count->execute([$chat['id']]);
  $messages_total = (int) $stmt_count->fetchColumn();
  $offset = $show_all ? 0 : max(0, $messages_total - $messages_limit);
  $limit = $show_all ? max(500, $messages_total) : $messages_limit;
  $messages = get_chat_messages($pdo, $chat['id'], $limit, $offset);
  mark_messages_as_read($pdo, $chat['id'], $user_id);
}

// Промокод
$stmt_order_promo = $pdo->prepare("SELECT * FROM order_promocodes WHERE order_id = ? LIMIT 1");
$stmt_order_promo->execute([$order_id]);
$order_promo = $stmt_order_promo->fetch(PDO::FETCH_ASSOC);

// CSRF для чата
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Финансы
$contact_info = json_decode($order['contact_info'] ?? '{}', true);
if (!is_array($contact_info))
  $contact_info = [];
$original_total_price = $contact_info['original_total_price'] ?? $order['total_price'];
$is_urgent_from_contact = !empty($contact_info['is_urgent']) || !empty($order['is_urgent']);
$urgent_fee = $contact_info['urgent_fee'] ?? 0;

// Готовность: история изменений (для информирования клиента)
$ready_date = $order['ready_date'] ?? null;
$ready_changes_count = 0;
$ready_last = null;
try {
  $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM order_ready_history WHERE order_id = ?");
  $stmt_cnt->execute([$order_id]);
  $ready_changes_count = (int) $stmt_cnt->fetchColumn();
  if ($ready_changes_count > 0) {
    $stmt_last = $pdo->prepare("SELECT * FROM order_ready_history WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_last->execute([$order_id]);
    $ready_last = $stmt_last->fetch(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) { /* ignore */
}
?>
<?php include_once('../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div><?php echo generateBreadcrumbs($pageTitle ?? ''); ?></div>
      <div><?php echo backButton(); ?></div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Детали заказа #<?php echo htmlspecialchars($order['id']); ?>
      </h1>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
    </div>

    <?php if (!empty($_SESSION['notifications'])): ?>
      <?php foreach ($_SESSION['notifications'] as $notification): ?>
        <div
          class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
          <?php echo htmlspecialchars($notification['message']); ?>
        </div>
      <?php endforeach;
      unset($_SESSION['notifications']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div class="lg:col-span-2 space-y-8">
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Информация о заказе</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="font-bold text-gray-800 mb-3">Дата и статус</h3>
              <div class="space-y-2">
                <?php if ($is_urgent_from_contact): ?>
                  <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">Срочный заказ
                    (+50%)</span>
                <?php endif; ?>
                <p class="text-gray-700"><span class="font-medium">Создан:</span>
                  <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
                <p class="text-gray-700">
                  <span class="font-medium">Ожидаемая готовность:</span>
                  <?php if (!empty($ready_date)): ?>
                    <?php echo date('d.m.Y H:i', strtotime($ready_date)); ?>
                    <?php if (!empty($ready_last) && ($ready_last['change_type'] ?? '') === 'prolong'): ?>
                      <br><span class="ml-2 text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">
                        <?php
                        $reason = trim((string) ($ready_last['reason'] ?? ''));
                        echo 'Дата изменялась' . ($reason !== '' ? ': ' . htmlspecialchars($reason) : '');
                        ?>
                      </span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-gray-500">будет сообщена дополнительно</span>
                  <?php endif; ?>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Статус:</span>
                  <?php
                  $status_display = [
                    'pending' => 'В ожидании',
                    'processing' => 'В обработке',
                    'shipped' => 'Отправлен',
                    'delivered' => 'Доставлен',
                    'cancelled' => 'Отменен',
                    'completed' => 'Завершен'
                  ];
                  $status_cls = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'processing' => 'bg-blue-100 text-blue-800',
                    'shipped' => 'bg-purple-100 text-purple-800',
                    'delivered' => 'bg-indigo-100 text-indigo-800',
                    'cancelled' => 'bg-red-100 text-red-800',
                    'completed' => 'bg-green-100 text-green-800'
                  ];
                  ?>
                  <span
                    class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_cls[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                    <?php echo htmlspecialchars($status_display[$order['status']] ?? $order['status']); ?>
                  </span>
                </p>
                <p class="text-gray-700">
                  <span class="font-medium">Оплата:</span>
                  <?php
                  $pay_names = ['unpaid' => 'Не оплачен', 'partial' => 'Частично оплачен', 'paid' => 'Оплачен'];
                  $pay_cls = ['unpaid' => 'bg-red-100 text-red-800', 'partial' => 'bg-yellow-100 text-yellow-800', 'paid' => 'bg-green-100 text-green-800'];
                  ?>
                  <span
                    class="px-2 py-1 rounded-full text-xs font-medium <?php echo $pay_cls[$order['payment_status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                    <?php echo htmlspecialchars($pay_names[$order['payment_status']] ?? $order['payment_status']); ?>
                  </span>
                </p>
              </div>
            </div>

            <div>
              <h3 class="font-bold text-gray-800 mb-3">Контактная информация</h3>
              <div class="space-y-2">
                <p class="text-gray-700"><span class="font-medium">Имя:</span>
                  <?php echo htmlspecialchars($contact_info['name'] ?? 'Не указано'); ?></p>
                <p class="text-gray-700"><span class="font-medium">Email:</span>
                  <?php echo htmlspecialchars($contact_info['email'] ?? 'Не указано'); ?></p>
                <p class="text-gray-700"><span class="font-medium">Телефон:</span>
                  <?php echo htmlspecialchars($contact_info['phone'] ?? 'Не указано'); ?></p>
                <?php if (!empty($contact_info['comment'])): ?>
                  <p class="text-gray-700"><span class="font-medium">Комментарий:</span>
                    <?php echo nl2br(htmlspecialchars($contact_info['comment'])); ?></p>
                <?php endif; ?>
              </div>
            </div>

            <div class="md:col-span-2 bg-gray-50 rounded-2xl p-4">
              <h3 class="font-bold text-gray-800 mb-3">Финансовая информация</h3>
              <div class="space-y-2">
                <p class="text-gray-700"><span class="font-medium">Стоимость товаров:</span>
                  <?php echo number_format($original_total_price, 2, '.', ' '); ?> ₽</p>
                <?php if ($is_urgent_from_contact): ?>
                  <p class="text-gray-700"><span class="font-medium">Срочный заказ (+50%):</span> <span
                      class="text-[#17B890]">+<?php echo number_format($urgent_fee, 2, '.', ' '); ?> ₽</span></p>
                <?php endif; ?>
                <?php if ($order_promo): ?>
                  <p class="text-gray-700">
                    <span class="font-medium">Промокод
                      (<?php echo htmlspecialchars($order_promo['promo_code']); ?>):</span>
                    <span
                      class="text-red-500">-<?php echo number_format($order_promo['applied_discount'], 2, '.', ' '); ?>
                      ₽</span>
                    <span class="text-xs text-gray-500 ml-2">
                      <?php echo $order_promo['discount_type'] === 'percentage' ? ($order_promo['discount_value'] . '%') : (number_format($order_promo['discount_value'], 2, '.', ' ') . ' ₽'); ?>
                    </span>
                  </p>
                <?php endif; ?>
                <div class="border-t border-gray-200 pt-2 mt-2">
                  <div class="flex justify-between text-lg font-bold">
                    <span>Итого к оплате:</span>
                    <span class="text-[#118568]"><?php echo number_format($order['total_price'], 2, '.', ' '); ?>
                      ₽</span>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Состав -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Состав заказа</h2>
          <div class="space-y-4">
            <?php if (empty($order_items)): ?>
              <div class="p-6 bg-gray-50 rounded-2xl text-gray-600">Пока нет позиций</div>
            <?php else: ?>
              <?php foreach ($order_items as $item):
                $display_name = $item['is_custom'] ? ($item['item_name'] ?: 'Своя позиция') : ($item['product_name'] ?? 'Товар');
                $unit_label = $item['unit'] ?? 'шт.';
                $unit_price = $item['unit_price'] !== null ? (float) $item['unit_price'] : ($item['quantity'] > 0 ? ((float) $item['price'] / (int) $item['quantity']) : 0);
                ?>
                <div class="flex flex-col sm:flex-row gap-4 p-4 bg-gray-50 rounded-2xl hover:bg-gray-100 transition">
                  <div class="flex-grow">
                    <h3 class="font-bold text-gray-800 mb-1">
                      <?php echo htmlspecialchars($display_name); ?>
                      <?php if ($item['is_custom']): ?>
                        <span class="ml-2 text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Новая позиция</span>
                      <?php endif; ?>
                    </h3>

                    <?php if (!$item['is_custom'] && !empty($item['attributes'])): ?>
                      <div class="mb-2">
                        <div class="text-sm text-gray-600 mb-1">Характеристики:</div>
                        <div class="flex flex-wrap gap-2">
                          <?php foreach ($item['attributes'] as $attr): ?>
                            <span class="px-2 py-1 bg-[#DEE5E5] text-gray-700 text-xs rounded-full">
                              <?php echo htmlspecialchars(($attr['attribute_name'] ?? 'Характеристика') . ': ' . ($attr['value'] ?? '?')); ?>
                            </span>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>

                    <?php if ($item['is_custom'] && !empty($item['item_note'])): ?>
                      <div class="text-sm text-gray-600 mb-2">Примечание:
                        <?php echo nl2br(htmlspecialchars($item['item_note'])); ?></div>
                    <?php endif; ?>

                    <div class="flex flex-wrap items-center gap-4 text-sm">
                      <span class="font-medium">Количество: <?php echo (int) $item['quantity']; ?>
                        <?php echo htmlspecialchars($unit_label); ?></span>
                      <span class="font-medium">Цена за единицу: <?php echo number_format($unit_price, 2, '.', ' '); ?>
                        ₽</span>
                    </div>
                  </div>
                  <div class="flex items-center sm:items-end sm:flex-col sm:justify-end">
                    <div class="text-lg font-bold text-[#118568]"><?php echo number_format($item['price'], 2, '.', ' '); ?>
                      ₽</div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Чат -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-3xl shadow-2xl flex flex-col">
          <div class="p-6 border-b border-[#DEE5E5]">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Чат по заказу</h2>
            <?php if ($chat && !empty($chat['assigned_user_name'])): ?>
              <div class="text-sm text-gray-600">Ваш менеджер: <span
                  class="ml-1 font-medium text-[#118568]"><?php echo htmlspecialchars($chat['assigned_user_name']); ?></span>
              </div>
            <?php else: ?>
              <div class="text-sm text-gray-500">Ожидание назначения менеджера...</div>
            <?php endif; ?>
          </div>

          <div id="chat-messages" class="flex-grow p-4 overflow-y-auto max-h-96 bg-white">
            <?php if (empty($messages)): ?>
              <div class="text-center text-gray-500 py-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-2" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p>Сообщений пока нет</p>
                <p class="text-xs mt-1">Напишите первое сообщение</p>
              </div>
            <?php else: ?>
              <?php foreach ($messages as $message):
                $is_own = ($message['user_id'] == $user_id);
                $time_str = date('H:i', strtotime($message['created_at']));
                $sender_role = $message['sender_role'] ?? null;
                $sender_name = $message['sender_name'] ?? 'Пользователь';
                ?>
                <div class="mb-4 <?php echo $is_own ? 'text-right' : 'text-left'; ?>">
                  <?php if (!$is_own): ?>
                    <div class="text-xs text-gray-500 mb-1 flex items-center gap-2">
                      <span><?php echo htmlspecialchars($sender_name); ?></span>
                      <?php if ($sender_role === 'admin'): ?>
                        <span class="ml-1 px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded">(Админ)</span>
                      <?php elseif ($sender_role === 'manager'): ?>
                        <span class="ml-1 px-1 py-0.5 bg-purple-100 text-purple-800 text-xs rounded">(Менеджер)</span>
                      <?php else: ?>
                        <span class="ml-1 px-1 py-0.5 bg-blue-100 text-blue-800 text-xs rounded">(Клиент)</span>
                      <?php endif; ?>
                      <span class="ml-2"><?php echo $time_str; ?></span>
                    </div>
                  <?php endif; ?>

                  <div
                    class="inline-block max-w-xs md:max-w-md px-4 py-3 rounded-2xl <?php echo $is_own ? 'bg-[#118568] text-white rounded-tr-none' : 'bg-gray-200 text-gray-800 rounded-tl-none'; ?>">
                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                  </div>

                  <?php if ($is_own): ?>
                    <div class="text-xs text-gray-500 mt-1 text-right"><?php echo $time_str; ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="p-4 border-t border-[#DEE5E5]">
            <form id="chat-form" class="flex gap-2" autocomplete="off">
              <input type="hidden" name="csrf_token" id="csrf_token"
                value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="order_id" id="order_id" value="<?php echo (int) $order_id; ?>">
              <input type="text" id="message-text" name="message_text"
                class="flex-grow px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] text-sm"
                placeholder="Введите сообщение..." required maxlength="1000">
              <button type="submit" id="send-button"
                class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300">Отправить</button>
            </form>

            <div class="mt-2 text-center">
              <?php if (!$show_all && $messages_total > $messages_limit): ?>
                <a href="?id=<?php echo $order_id; ?>&show_all_messages=1"
                  class="text-sm text-[#118568] hover:underline">Показать все сообщения
                  (<?php echo $messages_total; ?>)</a>
              <?php elseif ($show_all): ?>
                <a href="?id=<?php echo $order_id; ?>" class="text-sm text-[#118568] hover:underline">Показать последние
                  <?php echo $messages_limit; ?></a>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chat-form');
    const input = document.getElementById('message-text');
    const sendButton = document.getElementById('send-button');
    const messagesContainer = document.getElementById('chat-messages');
    const orderId = document.getElementById('order_id').value;
    const csrfToken = document.getElementById('csrf_token').value;
    const currentUserId = <?php echo json_encode($user_id); ?>;

    function escapeHtml(str) { return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m])); }
    function nl2br(str) { return String(str).replace(/\n/g, '<br>'); }

    function renderMessages(messages) {
      if (!Array.isArray(messages) || messages.length === 0) {
        messagesContainer.innerHTML = '<div class="text-center text-gray-500 py-8"><p>Сообщений пока нет</p></div>';
        return;
      }
      let html = '';
      messages.forEach(msg => {
        const isOwn = msg.user_id == currentUserId;
        const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        if (!isOwn) {
          html += `<div class="text-xs text-gray-500 mb-1 flex items-center"><span>${escapeHtml(msg.sender_name || '')}</span>`;
          if (msg.sender_role === 'admin') html += `<span class="ml-1 px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded">(Админ)</span>`;
          else if (msg.sender_role === 'manager') html += `<span class="ml-1 px-1 py-0.5 bg-purple-100 text-purple-800 text-xs rounded">(Менеджер)</span>`;
          else html += `<span class="ml-1 px-1 py-0.5 bg-blue-100 text-blue-800 text-xs rounded">(Клиент)</span>`;
          html += `<span class="ml-1">${time}</span></div>`;
        }
        html += `<div class="mb-4 ${isOwn ? 'text-right' : 'text-left'}"><div class="inline-block max-w-xs md:max-w-md px-4 py-3 rounded-2xl ${isOwn ? 'bg-[#118568] text-white rounded-tr-none' : 'bg-gray-200 text-gray-800 rounded-tl-none'}">${nl2br(escapeHtml(msg.message || ''))}</div>`;
        if (isOwn) html += `<div class="text-xs text-gray-500 mt-1 text-right">${time}</div>`;
        html += `</div>`;
      });
      messagesContainer.innerHTML = html;
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        sendButton.disabled = true;
        const body = new URLSearchParams();
        body.append('order_id', orderId);
        body.append('message_text', text);
        body.append('csrf_token', csrfToken);

        fetch('/ajax/send_message.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        })
          .then(resp => resp.json())
          .then(data => {
            if (data.success) {
              renderMessages(data.messages || []);
              input.value = '';
            } else {
              alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
            }
          })
          .catch(() => alert('Произошла ошибка при отправке сообщения.'))
          .finally(() => { sendButton.disabled = false; });
      });

      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
      });
    }
  });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>