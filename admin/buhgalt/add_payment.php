<?php
// admin/buhgalt/add_payment.php
session_start();
$pageTitle = "Добавить платеж";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
  header("Location: /login");
  exit();
}

include_once('../../includes/db.php');

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
  $notifications = $_SESSION['notifications'];
  unset($_SESSION['notifications']);
}

$order_accounting_id = $_GET['order_accounting_id'] ?? null;

if (!$order_accounting_id) {
  $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Не указан заказ для добавления платежа.'];
  header("Location: index.php");
  exit;
}

// Получаем информацию о заказе
$stmt = $pdo->prepare("SELECT * FROM orders_accounting WHERE id = ?");
$stmt->execute([$order_accounting_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заказ не найден.'];
  header("Location: index.php");
  exit;
}

// Рассчитываем уже оплаченную сумму
$stmt = $pdo->prepare("SELECT SUM(amount) as total_paid FROM order_payments WHERE order_accounting_id = ?");
$stmt->execute([$order_accounting_id]);
$total_paid_result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_paid = $total_paid_result['total_paid'] ?? 0;

$balance_due = $order['income'] - $total_paid; // Остаток к оплате

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $amount = floatval($_POST['amount'] ?? 0);
  $note = trim($_POST['note'] ?? '');
  // Можно добавить дату платежа, если нужно отличная от текущей
  // $payment_date = $_POST['payment_date'] ?? date('Y-m-d H:i:s');

  if ($amount > 0 && $amount <= $balance_due) {
    try {
      $pdo->beginTransaction();

      // Добавляем платеж
      $stmt = $pdo->prepare("
                INSERT INTO order_payments (order_accounting_id, amount, note) 
                VALUES (?, ?, ?)
            ");
      $stmt->execute([$order_accounting_id, $amount, $note]);

      // Пересчитываем общую оплаченную сумму
      $stmt = $pdo->prepare("SELECT SUM(amount) as total_paid FROM order_payments WHERE order_accounting_id = ?");
      $stmt->execute([$order_accounting_id]);
      $new_total_paid_result = $stmt->fetch(PDO::FETCH_ASSOC);
      $new_total_paid = $new_total_paid_result['total_paid'] ?? 0;

      // Определяем новый статус
      $new_status = 'unpaid';
      if ($new_total_paid >= $order['income']) {
        $new_status = 'paid';
      } elseif ($new_total_paid > 0) {
        $new_status = 'partial';
      }

      // Обновляем статус в orders_accounting
      $stmt = $pdo->prepare("UPDATE orders_accounting SET status = ? WHERE id = ?");
      $stmt->execute([$new_status, $order_accounting_id]);

      $pdo->commit();

      $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Платеж успешно добавлен.'];
      header("Location: index.php");
      exit;

    } catch (Exception $e) {
      $pdo->rollBack();
      error_log("Ошибка при добавлении платежа: " . $e->getMessage());
      $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при добавлении платежа.'];
      // Перенаправляем обратно, чтобы показать ошибку
      header("Location: " . $_SERVER['REQUEST_URI']);
      exit;
    }
  } else {
    if ($amount <= 0) {
      $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Сумма платежа должна быть больше 0.'];
    } else {
      $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Сумма платежа не может превышать остаток к оплате (' . number_format($balance_due, 2, '.', ' ') . ' ₽).'];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }
}
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-4xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <?php echo backButton(); ?>
      </div>
    </div>

    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Добавить платеж</h1>
      <p class="text-xl text-gray-700">по заказу #<?php echo htmlspecialchars($order['id']); ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div
        class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <div class="bg-white rounded-3xl shadow-2xl p-6">
      <div class="mb-6 p-4 bg-gray-50 rounded-xl">
        <h3 class="font-bold text-gray-800 mb-2">Информация о заказе</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
          <div><span class="font-medium">Источник:</span>
            <?php echo $order['source'] === 'site' ? 'Сайт' : 'Внешний'; ?></div>
          <div><span class="font-medium">Клиент:</span>
            <?php echo htmlspecialchars($order['client_name'] ?? 'Не указан'); ?></div>
          <div><span class="font-medium">Описание:</span>
            <?php echo htmlspecialchars($order['description'] ?? 'Нет описания'); ?></div>
          <div><span class="font-medium">Доход:</span> <?php echo number_format($order['income'], 2, '.', ' '); ?> ₽
          </div>
          <div><span class="font-medium">Оплачено:</span> <?php echo number_format($total_paid, 2, '.', ' '); ?> ₽</div>
          <div><span class="font-medium">К оплате:</span> <span
              class="font-bold"><?php echo number_format($balance_due, 2, '.', ' '); ?> ₽</span></div>
          <div><span class="font-medium">Статус:</span>
            <span class="px-2 py-1 text-xs rounded-full 
                        <?php
                        switch ($order['status']) {
                          case 'paid':
                            echo 'bg-green-100 text-green-800';
                            break;
                          case 'partial':
                            echo 'bg-yellow-100 text-yellow-800';
                            break;
                          case 'unpaid':
                            echo 'bg-red-100 text-red-800';
                            break;
                          default:
                            echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
              <?php
              $status_names = [
                'unpaid' => 'Не оплачен',
                'partial' => 'Частично',
                'paid' => 'Оплачен'
              ];
              echo $status_names[$order['status']] ?? $order['status'];
              ?>
            </span>
          </div>
        </div>
      </div>

      <form action="" method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        <div>
          <label for="amount" class="block text-gray-700 font-medium mb-2">Сумма платежа (руб.) *</label>
          <input type="number" step="0.01" id="amount" name="amount"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
            placeholder="0.00" min="0" max="<?php echo $balance_due; ?>" required>
          <p class="mt-1 text-sm text-gray-500">Максимум: <?php echo number_format($balance_due, 2, '.', ' '); ?> ₽</p>
        </div>

        <div>
          <label for="note" class="block text-gray-700 font-medium mb-2">Примечание</label>
          <textarea id="note" name="note" rows="2"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
            placeholder="Комментарий к платежу"></textarea>
        </div>

        <!--
        <div>
          <label for="payment_date" class="block text-gray-700 font-medium mb-2">Дата платежа</label>
          <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>"
                 class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
        </div>
        -->

        <div class="flex flex-col sm:flex-row gap-4 pt-4">
          <button type="submit"
            class="flex-1 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Добавить платеж
          </button>

          <a href="index.php"
            class="flex-1 px-4 py-4 bg-gray-200 text-gray-700 text-center rounded-xl hover:bg-gray-300 transition-colors duration-300 font-bold text-lg">
            Отмена
          </a>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include_once('../../includes/footer.php'); ?>