<?php
session_start();
$pageTitle = "Внешние заказы";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';
require_once '../buhgalt/functions.php';

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

// Параметры фильтрации и пагинации
$page = intval($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Построение запроса с фильтрами
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "eo.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(eo.client_name LIKE ? OR eo.email LIKE ? OR eo.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Получение общего количества записей для пагинации
$count_sql = "SELECT COUNT(*) FROM external_orders eo $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Основной запрос с join к бухгалтерии
$sql = "
    SELECT 
        eo.*,
        MAX(oa.income) as accounting_income,
        MAX(oa.total_expense) as total_expense,
        MAX(oa.estimated_expense) as estimated_expense,
        MAX(oa.tax_amount) as tax_amount,
        (MAX(oa.income) - (MAX(oa.total_expense) + MAX(oa.tax_amount))) as profit,
        COUNT(eoi.id) as items_count,
        SUM(CASE WHEN eoi.is_custom = 1 THEN 1 ELSE 0 END) as custom_items_count
    FROM external_orders eo
    LEFT JOIN orders_accounting oa ON oa.external_order_id = eo.id AND oa.source = 'external'
    LEFT JOIN external_order_items eoi ON eoi.external_order_id = eo.id
    $where_clause
    GROUP BY eo.id
    ORDER BY eo.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика
$stats_sql = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(eo.total_price) as total_revenue,
        SUM(CASE WHEN eo.status = 'paid' THEN eo.total_price ELSE 0 END) as paid_revenue,
        SUM(CASE WHEN eo.status = 'unpaid' THEN eo.total_price ELSE 0 END) as unpaid_revenue
    FROM external_orders eo
";
$stats_stmt = $pdo->query($stats_sql);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
    <div class="container mx-auto px-6 max-w-7xl">

        <!-- Заголовок -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
            <div>
                <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
            </div>
            <div class="flex gap-3">
                <?php echo backButton(); ?>
                <a href="/admin/order/add_external.php"
                    class="px-5 py-2.5 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition text-sm font-medium">
                    + Новый заказ
                </a>
                <a href="/admin/buhgalt/"
                    class="px-5 py-2.5 bg-[#17B890] text-white rounded-xl hover:bg-[#15a081] transition text-sm font-medium">
                    Бухгалтерия
                </a>
            </div>
        </div>

        <!-- Заголовок страницы -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Внешние заказы</h1>
            <p class="text-lg text-gray-700">Управление заказами, созданными вручную</p>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <!-- Уведомления -->
        <?php foreach ($notifications as $n): ?>
            <div
                class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <?php echo htmlspecialchars($n['message']); ?>
            </div>
        <?php endforeach; ?>

        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Всего заказов</h3>
                <p class="text-3xl font-bold text-[#118568] mt-2"><?php echo number_format($stats['total_orders']); ?>
                </p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Общая выручка</h3>
                <p class="text-3xl font-bold text-[#118568] mt-2">
                    <?php echo number_format($stats['total_revenue'], 0, '', ' '); ?> ₽</p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Оплачено</h3>
                <p class="text-3xl font-bold text-green-600 mt-2">
                    <?php echo number_format($stats['paid_revenue'], 0, '', ' '); ?> ₽</p>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <h3 class="text-sm font-medium text-gray-600">Не оплачено</h3>
                <p class="text-3xl font-bold text-red-600 mt-2">
                    <?php echo number_format($stats['unpaid_revenue'], 0, '', ' '); ?> ₽</p>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Поиск</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Имя, email, телефон..."
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Статус оплаты</label>
                    <select name="status"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                        <option value="">Все статусы</option>
                        <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Не оплачен
                        </option>
                        <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>Частично
                        </option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Оплачен</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit"
                        class="w-full py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition font-medium">
                        Найти
                    </button>
                </div>
                <div class="flex items-end">
                    <a href="?"
                        class="w-full py-3 bg-gray-200 text-gray-700 text-center rounded-lg hover:bg-gray-300 transition font-medium">
                        Сбросить
                    </a>
                </div>
            </form>
        </div>

        <!-- Заказы -->
        <?php if (empty($orders)): ?>
            <div class="text-center py-16">
                <div class="bg-white rounded-3xl shadow-lg p-12">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Заказы не найдены</h3>
                    <p class="text-gray-600 mb-8">Создайте первый внешний заказ</p>
                    <a href="/admin/order/add_external.php"
                        class="inline-block px-8 py-4 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition font-medium">
                        + Создать заказ
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition p-6">
                        <!-- Заголовок карточки -->
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">
                                    <?php echo htmlspecialchars($order['client_name']); ?></h3>
                                <p class="text-sm text-gray-600">#<?php echo $order['id']; ?> •
                                    <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
                            </div>
                            <span class="px-3 py-1 text-xs font-medium rounded-full
                <?php
                switch ($order['status']) {
                    case 'paid':
                        echo 'bg-green-100 text-green-800';
                        break;
                    case 'partial':
                        echo 'bg-yellow-100 text-yellow-800';
                        break;
                    default:
                        echo 'bg-red-100 text-red-800';
                        break;
                }
                ?>">
                                <?php
                                switch ($order['status']) {
                                    case 'paid':
                                        echo 'Оплачен';
                                        break;
                                    case 'partial':
                                        echo 'Частично';
                                        break;
                                    default:
                                        echo 'Не оплачен';
                                        break;
                                }
                                ?>
                            </span>
                        </div>

                        <!-- Контактная информация -->
                        <?php if ($order['email'] || $order['phone']): ?>
                            <div class="mb-4 space-y-1">
                                <?php if ($order['email']): ?>
                                    <p class="text-sm text-gray-600">
                                        <span class="inline-block w-4">📧</span> <?php echo htmlspecialchars($order['email']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($order['phone']): ?>
                                    <p class="text-sm text-gray-600">
                                        <span class="inline-block w-4">📞</span> <?php echo htmlspecialchars($order['phone']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Товары -->
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">
                                Позиций: <span class="font-medium"><?php echo $order['items_count']; ?></span>
                                <?php if ($order['custom_items_count'] > 0): ?>
                                    <span class="text-[#118568]">(<?php echo $order['custom_items_count']; ?> польз.)</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- Финансы -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-gray-600">Доход:</span>
                                    <span
                                        class="font-bold text-[#118568]"><?php echo number_format($order['total_price'], 0, '', ' '); ?>
                                        ₽</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Расход:</span>
                                    <span
                                        class="font-bold text-red-600"><?php echo number_format($order['total_expense'] ?: 0, 0, '', ' '); ?>
                                        ₽</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Налог:</span>
                                    <span
                                        class="font-medium"><?php echo number_format($order['tax_amount'] ?: 0, 0, '', ' '); ?>
                                        ₽</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Прибыль:</span>
                                    <span
                                        class="font-bold <?php echo ($order['profit'] ?: 0) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo number_format($order['profit'] ?: 0, 0, '', ' '); ?> ₽
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Действия -->
                        <div class="flex gap-2">
                            <a href="/admin/order/external_details.php?id=<?php echo $order['id']; ?>"
                                class="flex-1 py-2 bg-[#118568] text-white text-center rounded-lg hover:bg-[#0f755a] transition text-sm font-medium">
                                Подробнее
                            </a>
                            <a href="/admin/buhgalt/order_accounting.php?external_id=<?php echo $order['id']; ?>"
                                class="flex-1 py-2 bg-gray-200 text-gray-700 text-center rounded-lg hover:bg-gray-300 transition text-sm font-medium">
                                Бухгалтерия
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Пагинация -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center">
                    <nav class="bg-white rounded-2xl shadow-lg p-4">
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                    Назад
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>"
                                    class="px-4 py-2 <?php echo $i === $page ? 'bg-[#118568] text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-lg transition">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['status' => $status_filter, 'search' => $search])); ?>"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                    Далее
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>