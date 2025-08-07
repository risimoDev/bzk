<?php
session_start();
$pageTitle = "Админ-панель";
// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');


// Получаем список товаров
$products = $pdo->query("SELECT id, name, base_price FROM products")->fetchAll(PDO::FETCH_ASSOC);

// Режим: добавление или редактирование
$isEdit = isset($_GET['id']);
$order = null;
$orderItems = [];
$payments = [];

if ($isEdit) {
    $orderId = (int)$_GET['id'];
    // Получение заказа
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    // Получение товаров заказа
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();

    // Получение оплат
    $stmt = $pdo->prepare("SELECT * FROM order_payments WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $payments = $stmt->fetchAll();
}
?>
  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

    <script>
    function addProductRow(productId = '', quantity = '') {
        const container = document.getElementById('products-container');
        const row = document.createElement('div');
        row.className = 'flex space-x-2 mb-2';

        const select = document.createElement('select');
        select.name = 'product_ids[]';
        select.required = true;
        select.className = 'border px-2 py-1';
        select.innerHTML = `<option value="">Выберите товар</option>
            <?php foreach ($products as $product): ?>
                <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?> (<?= $product['price'] ?>₽)</option>
            <?php endforeach; ?>`;
        if (productId) select.value = productId;

        const input = document.createElement('input');
        input.type = 'number';
        input.name = 'quantities[]';
        input.min = 1;
        input.value = quantity || 1;
        input.required = true;
        input.className = 'border px-2 py-1 w-20';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '✕';
        removeBtn.className = 'text-red-500';
        removeBtn.onclick = () => container.removeChild(row);

        row.appendChild(select);
        row.appendChild(input);
        row.appendChild(removeBtn);
        container.appendChild(row);
    }

    function addPaymentRow(amount = '', date = '', comment = '') {
        const container = document.getElementById('payments-container');
        const row = document.createElement('div');
        row.className = 'flex space-x-2 mb-2';

        const amountInput = document.createElement('input');
        amountInput.type = 'number';
        amountInput.name = 'payment_amounts[]';
        amountInput.step = '0.01';
        amountInput.value = amount || '';
        amountInput.placeholder = 'Сумма';
        amountInput.className = 'border px-2 py-1';

        const dateInput = document.createElement('input');
        dateInput.type = 'date';
        dateInput.name = 'payment_dates[]';
        dateInput.value = date || '';
        dateInput.className = 'border px-2 py-1';

        const commentInput = document.createElement('input');
        commentInput.type = 'text';
        commentInput.name = 'payment_comments[]';
        commentInput.value = comment || '';
        commentInput.placeholder = 'Комментарий';
        commentInput.className = 'border px-2 py-1 flex-1';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '✕';
        removeBtn.className = 'text-red-500';
        removeBtn.onclick = () => container.removeChild(row);

        row.appendChild(amountInput);
        row.appendChild(dateInput);
        row.appendChild(commentInput);
        row.appendChild(removeBtn);
        container.appendChild(row);
    }

    window.onload = function () {
        <?php foreach ($orderItems as $item): ?>
            addProductRow("<?= $item['product_id'] ?>", "<?= $item['quantity'] ?>");
        <?php endforeach; ?>

        <?php if (empty($orderItems)): ?>
            addProductRow();
        <?php endif; ?>

        <?php foreach ($payments as $pay): ?>
            addPaymentRow("<?= $pay['amount'] ?>", "<?= $pay['payment_date'] ?>", "<?= htmlspecialchars($pay['comment']) ?>");
        <?php endforeach; ?>
    }
    </script>
<body class="p-6 font-sans">
    <h1 class="text-xl font-bold mb-4"><?= $isEdit ? "Редактирование заказа #{$order['id']}" : "Создание нового заказа" ?></h1>

    <form action="save_order.php" method="post" class="space-y-4">
        <?php if ($isEdit): ?>
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
        <?php endif; ?>

        <div>
            <label>Клиент:</label><br>
            <input type="text" name="client_name" required class="border px-2 py-1 w-full"
                   value="<?= $order['client_name'] ?? '' ?>">
        </div>

        <div>
            <label>Комментарий:</label><br>
            <textarea name="comment" class="border px-2 py-1 w-full"><?= $order['comment'] ?? '' ?></textarea>
        </div>

        <div>
            <label>Статус заказа:</label><br>
            <select name="status" class="border px-2 py-1">
                <?php
                $statuses = [
  'pending' => 'В ожидании',
  'processing' => 'В обработке',
  'shipped' => 'Отправлен',
  'delivered' => 'Доставлен',
  'cancelled' => 'Отменен',
  'completed' => 'Полностью готов'
];
                foreach ($statuses as $status) {
                    $selected = ($order['status'] ?? 'новый') === $status ? 'selected' : '';
                    echo "<option value=\"$status\" $selected>$status</option>";
                }
                ?>
            </select>
        </div>

        <div>
            <label class="block font-semibold">Товары:</label>
            <div id="products-container"></div>
            <button type="button" onclick="addProductRow()" class="text-blue-500 mt-2">+ Добавить товар</button>
        </div>

        <div>
            <label class="block font-semibold">Оплаты:</label>
            <div id="payments-container"></div>
            <button type="button" onclick="addPaymentRow()" class="text-blue-500 mt-2">+ Добавить оплату</button>
        </div>

        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Сохранить заказ</button>
    </form>

<?php include_once('../includes/footer.php'); ?>
