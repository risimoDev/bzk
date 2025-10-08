<?php
session_start();
$pageTitle = "Добавить общий расход";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

include_once('../../includes/db.php');
require_once '../../includes/security.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $category_id = intval($_POST['category_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d H:i:s');

    if ($amount > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO general_expenses (category_id, amount, description, expense_date)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$category_id, $amount, $description, $expense_date]);

        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Расход добавлен.'];
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Введите корректную сумму.'];
    }
}

// категории
$categories = $pdo->query("SELECT * FROM expenses_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include_once('../../includes/header.php');?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-3xl">
    <div class="bg-white rounded-3xl shadow-2xl p-8">
      <h1 class="text-3xl font-bold text-gray-800 mb-6">Добавить общий расход</h1>

      <form method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        <div>
          <label class="block text-gray-700 mb-2">Категория</label>
          <select name="category_id" class="w-full border rounded-lg px-3 py-2">
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-gray-700 mb-2">Сумма (₽)</label>
          <input type="number" step="0.01" name="amount" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-gray-700 mb-2">Дата расхода</label>
          <input type="datetime-local" name="expense_date" class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-gray-700 mb-2">Описание</label>
          <textarea name="description" class="w-full border rounded-lg px-3 py-2"></textarea>
        </div>
        <button type="submit" class="w-full bg-[#118568] text-white py-3 rounded-xl hover:bg-[#0f755a]">
          Добавить
        </button>
      </form>
    </div>
  </div>
</main>
<?php include_once('../../includes/footer.php'); ?>
