<?php
session_start();
$pageTitle = "Детали заказа | Админ-панель";
include_once('../../includes/header.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');


// Обработка фильтров
$where = [];
$params = [];

if (!empty($_GET['source'])) {
    $where[] = 'oa.source = ?';
    $params[] = $_GET['source'];
}
if (!empty($_GET['status'])) {
    $where[] = 'oa.status = ?';
    $params[] = $_GET['status'];
}
if (!empty($_GET['date_from'])) {
    $where[] = 'oa.created_at >= ?';
    $params[] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where[] = 'oa.created_at <= ?';
    $params[] = $_GET['date_to'];
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Получение заказов
$sql = "
    SELECT oa.*, o.total_price 
    FROM orders_accounting oa 
    LEFT JOIN orders o ON oa.order_id = o.id
    $where_sql
    ORDER BY oa.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Подсчёт по месяцам
$monthly = [];

foreach ($orders as $order) {
    $month = date('Y-m', strtotime($order['created_at']));
    if (!isset($monthly[$month])) {
        $monthly[$month] = ['income' => 0, 'expense' => 0];
    }

    $monthly[$month]['income'] += $order['income'];

    // Получаем расходы
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM order_expenses WHERE order_accounting_id = ?");
    $stmt->execute([$order['id']]);
    $expense = $stmt->fetchColumn();
    $monthly[$month]['expense'] += $expense ?? 0;
}
?>
<?php if (isset($_GET['synced'])): ?>
    <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
        Синхронизировано заказов: <?= htmlspecialchars($_GET['synced']) ?>
    </div>
<?php endif; ?>


<!-- Модальное окно -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex justify-center items-center z-50">
  <form action="/admin/buhgalt/addexternalorder" method="POST" class="bg-white p-6 rounded w-full max-w-md shadow-lg space-y-4">
    <h2 class="text-xl font-bold">Добавить внешний заказ</h2>
    <input name="client_name" class="w-full p-2 border rounded" placeholder="Имя клиента" required>
    <textarea name="description" class="w-full p-2 border rounded" placeholder="Описание"></textarea>
    <input type="number" step="0.01" name="income" class="w-full p-2 border rounded" placeholder="Доход" required>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Сохранить</button>
    <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-500">Отмена</button>
  </form>
</div>
<div class="bg-white shadow rounded p-4 mb-6">
    <h2 class="text-xl font-bold mb-2">Аналитика по месяцам</h2>
    <canvas id="financeChart" height="100"></canvas>
</div>
<!-- ТAILWIND: Таблица заказов -->
<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Бухгалтерия заказов</h1>
    <a href="/admin/buhgalt/syncorders" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded mb-4 inline-block">Добавить заказы с сайта в бухгалтерию</a>
    <!-- Кнопка -->
<button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-green-600 text-white px-4 py-2 rounded mb-4">
    + Добавить внешний заказ
</button>
    <!-- Фильтры -->
    <form class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4" method="get">
        <select name="source" class="p-2 border rounded">
            <option value="">Источник</option>
            <option value="site">Сайт</option>
            <option value="external">Внешний</option>
        </select>
        <select name="status" class="p-2 border rounded">
            <option value="">Статус</option>
            <option value="paid">Оплачен</option>
            <option value="partial">Частично</option>
            <option value="unpaid">Не оплачен</option>
        </select>
        <input type="date" name="date_from" class="p-2 border rounded" placeholder="С">
        <input type="date" name="date_to" class="p-2 border rounded" placeholder="По">
        <button type="submit" class="col-span-1 md:col-span-4 bg-black text-white p-2 rounded">Применить</button>
    </form>

    <!-- Таблица -->
    <div class="overflow-auto">
        <table class="min-w-full text-sm text-left border border-gray-200 rounded">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 border">ID</th>
                    <th class="p-2 border">Источник</th>
                    <th class="p-2 border">Клиент</th>
                    <th class="p-2 border">Доход</th>
                    <th class="p-2 border">Расход</th>
                    <th class="p-2 border">Прибыль</th>
                    <th class="p-2 border">Статус</th>
                    <th class="p-2 border">Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-2 border"><?= htmlspecialchars($order['id']) ?></td>
                    <td class="p-2 border"><?= $order['source'] ?></td>
                    <td class="p-2 border"><?= htmlspecialchars($order['client_name']) ?></td>
                    <td class="p-2 border"><?= number_format($order['income'], 2) ?></td>
                    <td class="p-2 border"><?= number_format($order['total_expense'], 2) ?></td>
                    <td class="p-2 border font-semibold"><?= number_format($order['income'] - $order['total_expense'], 2) ?></td>
                    <td class="p-2 border"><?= $order['status'] ?></td>
                    <td class="p-2 border"><?= $order['created_at'] ?></td>
                    <td class="p-2 border">
                        <a href="/admin/buhgalt/orderdetail?id=<?= $order['id'] ?>" class="text-blue-600 underline">Детали</a>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<script>
const ctx = document.getElementById('financeChart').getContext('2d');

const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($monthly)) ?>,
        datasets: [
            {
                label: 'Доход',
                backgroundColor: 'rgba(34, 197, 94, 0.6)',
                data: <?= json_encode(array_column($monthly, 'income')) ?>
            },
            {
                label: 'Расход',
                backgroundColor: 'rgba(239, 68, 68, 0.6)',
                data: <?= json_encode(array_column($monthly, 'expense')) ?>
            },
            {
                label: 'Прибыль',
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                data: <?= json_encode(array_map(fn($m) => $m['income'] - $m['expense'], $monthly)) ?>
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => value + ' ₽'
                }
            }
        }
    }
});
</script>

<?php include_once('../../includes/footer.php'); ?>