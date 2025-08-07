<?php
// admin/buhgalt/add_expense.php
session_start();
$pageTitle = "Добавить расход";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
    header("Location: /login");
    exit();
}

include_once('../../includes/header.php');
include_once('../../includes/db.php');

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

$order_accounting_id = $_GET['order_accounting_id'] ?? null;

if (!$order_accounting_id) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Не указан заказ для добавления расхода.'];
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

// Получаем категории расходов
$stmt = $pdo->query("SELECT * FROM expenses_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    // Можно добавить дату расхода, если нужно отличная от текущей
    // $expense_date = $_POST['expense_date'] ?? date('Y-m-d H:i:s');

    if ($amount > 0) {
        try {
            $pdo->beginTransaction();
            
            // Добавляем расход
            $stmt = $pdo->prepare("
                INSERT INTO order_expenses (order_accounting_id, category_id, amount, description) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$order_accounting_id, $category_id, $amount, $description]);
            
            // Обновляем общий расход в orders_accounting
            $stmt = $pdo->prepare("
                UPDATE orders_accounting 
                SET total_expense = (SELECT SUM(amount) FROM order_expenses WHERE order_accounting_id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$order_accounting_id, $order_accounting_id]);
            
            $pdo->commit();
            
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Расход успешно добавлен.'];
            header("Location: index.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Ошибка при добавлении расхода: " . $e->getMessage());
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при добавлении расхода.'];
            // Перенаправляем обратно, чтобы показать ошибку
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Сумма расхода должна быть больше 0.'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}
?>

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
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Добавить расход</h1>
      <p class="text-xl text-gray-700">по заказу #<?php echo htmlspecialchars($order['id']); ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <div class="bg-white rounded-3xl shadow-2xl p-6">
    <div class="mb-6 p-4 bg-gray-50 rounded-xl">
        <h3 class="font-bold text-gray-800 mb-2">Информация о заказе</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            <div><span class="font-medium">Источник:</span> <?php echo $order['source'] === 'site' ? 'Сайт' : 'Внешний'; ?></div>
            <div><span class="font-medium">Клиент:</span> <?php echo htmlspecialchars($order['client_name'] ?? 'Не указан'); ?></div>
            <div><span class="font-medium">Описание:</span> <?php echo htmlspecialchars($order['description'] ?? 'Нет описания'); ?></div>
            <div><span class="font-medium">Доход:</span> <?php echo number_format($order['income'], 2, '.', ' '); ?> ₽</div>
            <!-- Добавляем estimated_expense -->
            <div><span class="font-medium">Предполагаемый расход:</span> <?php echo number_format($order['estimated_expense'], 2, '.', ' '); ?> ₽</div>
            <div><span class="font-medium">Фактический расход:</span> <?php echo number_format($order['total_expense'], 2, '.', ' '); ?> ₽</div>
            <?php 
            $difference = $order['estimated_expense'] - $order['total_expense'];
            if (abs($difference) > 0.01): ?>
            <div><span class="font-medium">Расхождение:</span> 
                <span class="<?php echo $difference > 0 ? 'text-red-500' : 'text-[#17B890]'; ?>">
                    <?php echo ($difference > 0 ? '+' : '') . number_format($difference, 2, '.', ' '); ?> ₽
                </span>
                <?php if ($difference > 0): ?>
                    <span class="text-xs text-gray-500">(перерасход)</span>
                <?php else: ?>
                    <span class="text-xs text-gray-500">(экономия)</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

      <form action="" method="POST" class="space-y-6">
        <div>
          <label for="category_id" class="block text-gray-700 font-medium mb-2">Категория расхода</label>
          <select id="category_id" name="category_id" 
                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
            <option value="">Без категории</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div>
          <label for="amount" class="block text-gray-700 font-medium mb-2">Сумма расхода (руб.) *</label>
          <input type="number" step="0.01" id="amount" name="amount" 
                 class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                 placeholder="0.00" min="0" required>
        </div>
        
        <div>
          <label for="description" class="block text-gray-700 font-medium mb-2">Описание расхода</label>
          <textarea id="description" name="description" rows="2" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    placeholder="Что было потрачено"></textarea>
        </div>
        
        <!--
        <div>
          <label for="expense_date" class="block text-gray-700 font-medium mb-2">Дата расхода</label>
          <input type="date" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>"
                 class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
        </div>
        -->
        
        <div class="flex flex-col sm:flex-row gap-4 pt-4">
          <button type="submit" 
                  class="flex-1 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Добавить расход
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