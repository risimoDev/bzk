<?php
// admin/buhgalt/add_external_order.php
session_start();
$pageTitle = "Добавить внешний заказ";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $client_name = trim($_POST['client_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $income = floatval($_POST['income'] ?? 0);

    if (!empty($client_name) && $income > 0) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO orders_accounting (source, client_name, description, income, total_expense, status) 
                VALUES ('external', ?, ?, ?, 0, 'unpaid')
            ");
            $stmt->execute([$client_name, $description, $income]);
            
            $pdo->commit();
            
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Внешний заказ успешно добавлен.'];
            header("Location: index.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Ошибка при добавлении внешнего заказа: " . $e->getMessage());
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при добавлении внешнего заказа.'];
            // Перенаправляем обратно, чтобы показать ошибку
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пожалуйста, заполните все обязательные поля корректно.'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}
?>

<?php include_once('../../includes/header.php');?>

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
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Добавить внешний заказ</h1>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <div class="bg-white rounded-3xl shadow-2xl p-6">
      <form action="" method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        <div>
          <label for="client_name" class="block text-gray-700 font-medium mb-2">Имя клиента *</label>
          <input type="text" id="client_name" name="client_name" 
                 class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                 placeholder="Введите имя клиента" required>
        </div>
        
        <div>
          <label for="description" class="block text-gray-700 font-medium mb-2">Описание заказа</label>
          <textarea id="description" name="description" rows="3" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    placeholder="Краткое описание заказа"></textarea>
        </div>
        
        <div>
          <label for="income" class="block text-gray-700 font-medium mb-2">Доход (руб.) *</label>
          <input type="number" step="0.01" id="income" name="income" 
                 class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                 placeholder="0.00" min="0" required>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-4 pt-4">
          <button type="submit" 
                  class="flex-1 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Добавить заказ
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