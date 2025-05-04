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

$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    die("ID заказа не указан.");
}

// Получаем заказ
$stmt = $pdo->prepare("SELECT * FROM orders_accounting WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Заказ не найден.");
}

// Получаем оплаты
$stmt = $pdo->prepare("SELECT * FROM order_payments WHERE order_accounting_id = ? ORDER BY payment_date DESC");
$stmt->execute([$order_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем расходы
$stmt = $pdo->prepare("SELECT * FROM order_expenses WHERE order_accounting_id = ? ORDER BY expense_date DESC");
$stmt->execute([$order_id]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Суммы
$total_paid = array_sum(array_column($payments, 'amount'));
$total_expense = array_sum(array_column($expenses, 'amount'));
$profit = $order['income'] - $total_expense;
?>

<div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold mb-4">Детали заказа #<?= $order_id ?></h1>

    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white shadow p-4 rounded border">
            <p><strong>Клиент:</strong> <?= htmlspecialchars($order['client_name']) ?></p>
            <p><strong>Источник:</strong> <?= $order['source'] ?></p>
            <p><strong>Доход:</strong> <?= number_format($order['income'], 2) ?> ₽</p>
            <p><strong>Оплачено:</strong> <?= number_format($total_paid, 2) ?> ₽</p>
            <p><strong>Расходы:</strong> <?= number_format($total_expense, 2) ?> ₽</p>
            <p><strong>Прибыль:</strong> <?= number_format($profit, 2) ?> ₽</p>
        </div>

        <!-- Форма оплаты -->
        <form action="/admin/buhgalt/addpayment" method="POST" class="bg-white shadow p-4 rounded border space-y-2">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <h2 class="text-lg font-semibold">Добавить оплату</h2>
            <input type="number" step="0.01" name="amount" class="w-full p-2 border rounded" placeholder="Сумма" required>
            <input type="date" name="payment_date" class="w-full p-2 border rounded" required>
            <button type="submit" class="bg-green-600 text-white px-4 py-1 rounded">Добавить</button>
        </form>

        <!-- Форма расхода -->
        <form action="/admin/buhgalt/addexpense" method="POST" class="bg-white shadow p-4 rounded border space-y-2">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <h2 class="text-lg font-semibold">Добавить расход</h2>
            <input type="text" name="description" class="w-full p-2 border rounded" placeholder="Описание">
            <input type="number" step="0.01" name="amount" class="w-full p-2 border rounded" placeholder="Сумма" required>
            <input type="date" name="expense_date" class="w-full p-2 border rounded" required>
            <button type="submit" class="bg-red-600 text-white px-4 py-1 rounded">Добавить</button>
        </form>
    </div>

    <!-- Таблица оплат -->
    <div>
        <h2 class="text-xl font-semibold mb-2">Оплаты</h2>
        <table class="min-w-full text-sm border">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 border">Дата</th>
                    <th class="p-2 border">Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td class="p-2 border"><?= $p['payment_date'] ?></td>
                    <td class="p-2 border"><?= number_format($p['amount'], 2) ?> ₽</td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- Таблица расходов -->
    <div>
        <h2 class="text-xl font-semibold mb-2">Расходы</h2>
        <table class="min-w-full text-sm border">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 border">Дата</th>
                    <th class="p-2 border">Сумма</th>
                    <th class="p-2 border">Описание</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $e): ?>
                <tr>
                    <td class="p-2 border"><?= $e['expense_date'] ?></td>
                    <td class="p-2 border"><?= number_format($e['amount'], 2) ?> ₽</td>
                    <td class="p-2 border"><?= htmlspecialchars($e['description']) ?></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once('../../includes/footer.php'); ?>