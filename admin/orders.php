<?php
session_start();
$pageTitle = "Управление заказами";


if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

include_once('../includes/db.php');
include_once('../includes/security.php');
include_once('../includes/common.php');
include_once(__DIR__ . '/buhgalt/functions.php');

// ---------- CSRF ----------
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ---------- Статусы ----------
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

// ---------- Обработка изменения статуса ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
  verify_csrf();

  $order_id = (int) ($_POST['order_id'] ?? 0);
  $status = $_POST['update_status'] ?? null;

  if ($order_id > 0 && isset($statuses[$status])) {
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);

    add_notification('success', 'Статус заказа успешно изменен.');
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
  } else {
    add_notification('error', 'Некорректные данные для изменения статуса.');
  }
}

// ---------- Пагинация ----------
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_orders_stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $total_orders_stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// ---------- Получение заказов ----------
$stmt = $pdo->prepare("
    SELECT o.id, o.user_id, o.total_price, o.status, o.created_at, 
           u.name AS user_name, u.email,
           oa.id AS accounting_id, oa.income, oa.total_expense
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN orders_accounting oa ON oa.order_id = o.id
    ORDER BY o.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Статистика заказов ----------
$status_stats = array_fill_keys(array_keys($statuses), 0);
$stmt_stats = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
foreach ($stmt_stats->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $status_stats[$row['status']] = (int) $row['cnt'];
}
?>

<?php include_once('../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">

    <!-- breadcrumbs + назад -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div><?php echo generateBreadcrumbs($pageTitle ?? ''); ?></div>
      <div><?php echo backButton(); ?></div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление заказами</h1>
      <p class="text-xl text-gray-700">Всего заказов: <?php echo $total_orders; ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- уведомления -->
    <?php echo display_notifications(); ?>

    <!-- статистика -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
      <?php foreach ($statuses as $k => $name): ?>
        <div class="bg-white rounded-2xl shadow-lg p-4 text-center hover:shadow-xl transition">
          <div
            class="text-2xl font-bold <?php echo $k === 'cancelled' ? 'text-red-500' : ($k === 'completed' ? 'text-green-500' : 'text-[#118568]'); ?>">
            <?php echo $status_stats[$k]; ?>
          </div>
          <div class="text-sm text-gray-600"><?php echo $name; ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- список заказов -->
    <?php if (empty($orders)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <p class="text-gray-600">Пока нет заказов</p>
      </div>
    <?php else: ?>
      <?php
      // Подготовка данных для responsive_table
      $columns = [
        'order' => ['title' => 'Заказ', 'allow_html' => true],
        'client' => ['title' => 'Клиент', 'allow_html' => true],
        'date' => ['title' => 'Дата', 'allow_html' => true],
        'amount' => ['title' => 'Сумма', 'allow_html' => true],
        'profit' => ['title' => 'Прибыль', 'allow_html' => true],
        'status' => ['title' => 'Статус', 'allow_html' => true],
        'actions' => ['title' => 'Действия', 'allow_html' => true]
      ];

      $table_data = [];
      foreach ($orders as $order) {
        $profit = $order['accounting_id'] ? get_order_profit($pdo, $order['accounting_id']) : null;

        $table_data[] = [
          'order' => '<div class="font-bold text-lg">#' . e($order['id']) . '</div><div class="text-sm text-gray-500 mt-1">' . date('d.m.Y H:i', strtotime($order['created_at'])) . '</div>',
          'client' => '<div class="font-medium text-gray-800">' . e($order['user_name'] ?? 'Гость') . '</div><div class="text-sm text-gray-500 mt-1">' . e($order['email'] ?? 'Не указан') . '</div>',
          'date' => '<div class="text-gray-600">' . date('d.m.Y', strtotime($order['created_at'])) . '</div>',
          'amount' => '<span class="font-bold text-2xl text-[#118568]">' . number_format($order['total_price'], 0, '', ' ') . ' ₽</span>',
          'profit' => '<span class="font-bold text-xl ' . ($profit < 0 ? 'text-red-600' : 'text-green-600') . '">' . ($profit !== null ? number_format($profit, 0, '', ' ') . ' ₽' : '—') . '</span>',
          'status' => '<span class="px-3 py-1 rounded-full text-xs font-medium ' . ($status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800') . '">' . e($statuses[$order['status']] ?? 'Неизвестно') . '</span>',
          'actions' => '
                  <div class="flex flex-col sm:flex-row gap-2">
                      <form action="" method="POST" class="inline">
                          ' . csrf_field() . '
                          <input type="hidden" name="order_id" value="' . $order['id'] . '">
                          <select name="update_status" onchange="this.form.submit()" class="text-xs rounded border-gray-300 focus:border-[#118568] focus:ring-[#17B890] w-full sm:w-auto">
                              ' . implode('', array_map(function ($k, $name) use ($order) {
            return '<option value="' . e($k) . '"' . ($order['status'] === $k ? ' selected' : '') . '>' . e($name) . '</option>';
          }, array_keys($statuses), $statuses)) . '
                          </select>
                      </form>
                      <a href="/admin/order/details?id=' . $order['id'] . '" class="px-3 py-2 bg-gradient-to-r from-[#17B890] to-[#118568] text-white text-xs rounded hover:from-[#14a380] hover:to-[#0f755a] transition-all duration-300 flex items-center justify-center whitespace-nowrap mt-2 sm:mt-0">
                          Детали
                      </a>
                  </div>'
        ];
      }

      echo responsive_table($columns, $table_data, [
        'default_view' => 'cards', // Показываем карточки на мобильных по умолчанию
        'table_classes' => 'w-full bg-white rounded-2xl shadow-lg overflow-hidden',
        'card_classes' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6'
      ]);
      ?>
    <?php endif; ?>


    <!-- пагинация -->
    <?php if ($total_pages > 1): ?>
      <div class="flex justify-center">
        <div class="bg-white rounded-2xl shadow-lg px-6 py-4 flex items-center space-x-2">
          <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>"
              class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5]">
              ←
            </a>
          <?php endif; ?>
          <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>"
              class="w-10 h-10 flex items-center justify-center rounded-lg <?php echo $i == $page ? 'bg-[#118568] text-white' : 'hover:bg-[#DEE5E5]'; ?>">
              <?php echo $i; ?>
            </a>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>"
              class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5]">
              →
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
  (function () {
    const badgeSelector = '#orders-badge';
    const linkSelector = '#admin-orders-link';
    const fetchUrl = '/ajax/new_orders_count.php';
    const markSeenUrl = '/ajax/mark_orders_seen.php';

    async function fetchNewCount() {
      try {
        const res = await fetch(fetchUrl, { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        updateBadge(data.count || 0);
      } catch (e) {
        console.error('fetchNewCount error', e);
      }
    }

    function updateBadge(count) {
      const link = document.querySelector(linkSelector);
      if (!link) return;
      let badge = document.querySelector(badgeSelector);
      if (count > 0) {
        if (!badge) {
          badge = document.createElement('span');
          badge.id = 'orders-badge';
          badge.className = 'absolute top-3 right-3 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none rounded-full bg-red-500 text-white';
          link.appendChild(badge);
        }
        badge.textContent = count;
        badge.style.display = '';
      } else {
        if (badge) badge.style.display = 'none';
      }
    }

    // Периодический опрос (каждые 15 сек)
    let pollInterval = 15000;
    setInterval(() => {
      if (!document.hidden) fetchNewCount();
    }, pollInterval);

    // Обновить при загрузке
    document.addEventListener('DOMContentLoaded', fetchNewCount);

    // При клике на ссылку пометим заказы как прочитанные.
    document.addEventListener('click', function (e) {
      const target = e.target.closest(linkSelector);
      if (!target) return;

      // Используем sendBeacon, чтобы запрос успел уйти даже при навигации
      try {
        const params = new URLSearchParams();
        params.append('all', '1');
        if (navigator.sendBeacon) {
          navigator.sendBeacon(markSeenUrl, params);
        } else {
          // fallback: fire-and-forget fetch but non-blocking
          fetch(markSeenUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: params
          }).catch(() => { });
        }
      } catch (err) {
        // ignore
      }
      // навигация дальше по ссылке произойдёт как обычно
    });

  })();
</script>

<?php include_once('../includes/footer.php'); ?>