<?php
// admin/tax_settings.php
session_start();
require_once '../includes/security.php';
$pageTitle = "Настройки налога";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

include_once('../includes/db.php');
require_once __DIR__ . '/buhgalt/functions.php';

// Обработка уведомлений
$notifications = $_SESSION['notifications'] ?? [];
unset($_SESSION['notifications']);

// Сохранение налога
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tax_rate'])) {
    // Verify CSRF token
    verify_csrf();
    $tax_rate = floatval($_POST['tax_rate']);
    if ($tax_rate >= 0 && $tax_rate <= 100) {
        if (set_setting($pdo, 'tax_rate', $tax_rate)) {
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Ставка налога обновлена.'];
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при обновлении налога.'];
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Введите корректное значение (0–100).'];
    }
    header("Location: /admin/tax_settings.php");
    exit();
}

// Текущая ставка
$current_tax = get_setting($pdo, 'tax_rate', 20.0);
?>
<?php include_once('../includes/header.php'); ?>
<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-4xl">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] text-sm">В админку</a>
      </div>
    </div>

    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Настройки налога</h1>
      <p class="text-lg text-gray-700">Здесь вы можете указать текущую ставку налога в %</p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl 
        <?php echo $notification['type'] === 'success' 
          ? 'bg-green-100 border border-green-400 text-green-700' 
          : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <div class="bg-white rounded-3xl shadow-2xl p-8">
      <form method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        <div>
          <label for="tax_rate" class="block text-gray-700 font-medium mb-2">Ставка налога (%)</label>
          <input type="number" step="0.01" id="tax_rate" name="tax_rate" 
                 value="<?php echo htmlspecialchars($current_tax); ?>" 
                 min="0" max="100"
                 class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition">
        </div>

        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Сохранить
        </button>
      </form>
    </div>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>
