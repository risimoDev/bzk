<?php
session_start();
$pageTitle = "Управление заказами";
include_once('../includes/header.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

include_once('../includes/db.php');
include_once(__DIR__ . '/buhgalt/functions.php');

// ---------- CSRF ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ---------- Статусы ----------
$statuses = [
    'pending'    => 'В ожидании',
    'processing' => 'В обработке',
    'shipped'    => 'Отправлен',
    'delivered'  => 'Доставлен',
    'cancelled'  => 'Отменен',
    'completed'  => 'Полностью готов'
];

$status_colors = [
    'pending'    => 'bg-yellow-100 text-yellow-800',
    'processing' => 'bg-blue-100 text-blue-800',
    'shipped'    => 'bg-purple-100 text-purple-800',
    'delivered'  => 'bg-indigo-100 text-indigo-800',
    'cancelled'  => 'bg-red-100 text-red-800',
    'completed'  => 'bg-green-100 text-green-800'
];

// ---------- Обработка изменения статуса ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка безопасности (CSRF).'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $order_id = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['update_status'] ?? null;

    if ($order_id > 0 && isset($statuses[$status])) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);

        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Статус заказа успешно изменен.'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Некорректные данные для изменения статуса.'];
    }
}

// ---------- Пагинация ----------
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
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
    $status_stats[$row['status']] = (int)$row['cnt'];
}
?>

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
    <?php if (!empty($_SESSION['notifications'])): ?>
      <?php foreach ($_SESSION['notifications'] as $n): ?>
        <div class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
          <?php echo htmlspecialchars($n['message']); ?>
        </div>
      <?php endforeach; unset($_SESSION['notifications']); ?>
    <?php endif; ?>

    <!-- статистика -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
      <?php foreach ($statuses as $k => $name): ?>
        <div class="bg-white rounded-2xl shadow-lg p-4 text-center hover:shadow-xl transition">
          <div class="text-2xl font-bold <?php echo $k === 'cancelled' ? 'text-red-500' : ($k === 'completed' ? 'text-green-500' : 'text-[#118568]'); ?>">
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
      <div class="bg-white rounded-3xl shadow-2xl overflow-hidden mb-8">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-[#118568] text-white">
              <tr>
                <th class="py-4 px-6 text-left">Заказ</th>
                <th class="py-4 px-6 text-left">Клиент</th>
                <th class="py-4 px-6 text-left">Дата</th>
                <th class="py-4 px-6 text-left">Сумма</th>
                <th class="py-4 px-6 text-left">Прибыль</th>
                <th class="py-4 px-6 text-left">Статус</th>
                <th class="py-4 px-6 text-left">Действия</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#DEE5E5]">
              <?php foreach ($orders as $order): ?>
                <?php $profit = $order['accounting_id'] ? get_order_profit($pdo, $order['accounting_id']) : null; ?>
                <tr class="hover:bg-[#f8fafa] transition">
                  <td class="py-4 px-6">
                    <div class="font-bold">#<?php echo htmlspecialchars($order['id']); ?></div>
                    <div class="text-sm text-gray-500"><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-medium"><?php echo htmlspecialchars($order['user_name'] ?? 'Гость'); ?></div>
                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email'] ?? 'Не указан'); ?></div>
                  </td>
                  <td class="py-4 px-6 text-gray-600"><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></td>
                  <td class="py-4 px-6 font-bold text-[#118568]"><?php echo number_format($order['total_price'], 0, '', ' '); ?> ₽</td>
                  <td class="py-4 px-6 font-bold <?php echo ($profit < 0 ? 'text-red-600' : 'text-green-600'); ?>">
                    <?php echo $profit !== null ? number_format($profit, 0, '', ' ') . ' ₽' : '—'; ?>
                  </td>
                  <td class="py-4 px-6">
                    <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                      <?php echo htmlspecialchars($statuses[$order['status']] ?? 'Неизвестно'); ?>
                    </span>
                  </td>
                  <td class="py-4 px-6">
                    <div class="flex flex-col sm:flex-row gap-2">
                      <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <select name="update_status" onchange="this.form.submit()" class="text-xs rounded border-gray-300 focus:border-[#118568] focus:ring-[#17B890]">
                          <?php foreach ($statuses as $k => $name): ?>
                            <option value="<?php echo $k; ?>" <?php echo $order['status'] === $k ? 'selected' : ''; ?>>
                              <?php echo $name; ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </form>

                      <a href="/admin/order/details?id=<?php echo $order['id']; ?>" class="px-3 py-1 bg-[#17B890] text-white text-xs rounded hover:bg-[#14a380] transition flex items-center">
                        Детали
                      </a>
                    </div>
                  </td>
                </tr>

                <!-- быстрый просмотр состава заказа -->
                <tr class="bg-gray-50">
                  <td colspan="7" class="px-6 py-2 text-sm text-gray-600">
                    <?php
                    $stmt_items = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                    $stmt_items->execute([$order['id']]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    if ($items):
                        foreach ($items as $it):
                            echo "- " . htmlspecialchars($it['name']) . " × " . (int)$it['quantity'] . "<br>";
                        endforeach;
                    else:
                        echo "Нет товаров";
                    endif;
                    ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- пагинация -->
      <?php if ($total_pages > 1): ?>
        <div class="flex justify-center">
          <div class="bg-white rounded-2xl shadow-lg px-6 py-4 flex items-center space-x-2">
            <?php if ($page > 1): ?>
              <a href="?page=<?php echo $page - 1; ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5]">
                ←
              </a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
              <a href="?page=<?php echo $i; ?>" class="w-10 h-10 flex items-center justify-center rounded-lg <?php echo $i == $page ? 'bg-[#118568] text-white' : 'hover:bg-[#DEE5E5]'; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
              <a href="?page=<?php echo $page + 1; ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5]">
                →
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>