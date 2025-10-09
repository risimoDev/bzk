<?php
// admin/product/manage_expenses.php
session_start();
$pageTitle = "Управление расходниками";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

include_once('../../includes/db.php');

// Уведомления
$notifications = $_SESSION['notifications'] ?? [];
unset($_SESSION['notifications']);

$product_id = $_GET['product_id'] ?? null;
if (!$product_id) {
  $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Не указан товар.'];
  header("Location: /admin/products");
  exit;
}

// Инфо о товаре
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
  $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Товар не найден.'];
  header("Location: /admin/products");
  exit;
}

// Добавление / удаление материалов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = $_POST['action'] ?? null;

  if ($action === 'add_material') {
    $material_id = intval($_POST['material_id']);
    $quantity_per_unit = floatval($_POST['quantity_per_unit']);

    if ($material_id > 0 && $quantity_per_unit > 0) {
      $stmt = $pdo->prepare("INSERT INTO product_materials (product_id, material_id, quantity_per_unit) VALUES (?, ?, ?)");
      $stmt->execute([$product_id, $material_id, $quantity_per_unit]);
      $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Материал добавлен к товару.'];
    } else {
      $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Выберите материал и укажите количество.'];
    }

  } elseif ($action === 'delete_material') {
    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("DELETE FROM product_materials WHERE id = ? AND product_id = ?");
    $stmt->execute([$id, $product_id]);
    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Материал удалён.'];
  }

  header("Location: " . $_SERVER['REQUEST_URI']);
  exit;
}

// Все материалы (для выбора)
$stmt = $pdo->query("SELECT id, name, unit, cost_per_unit FROM materials ORDER BY name");
$all_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Материалы товара
$stmt = $pdo->prepare("
    SELECT pm.id, m.name, m.unit, m.cost_per_unit, pm.quantity_per_unit
    FROM product_materials pm
    JOIN materials m ON pm.material_id = m.id
    WHERE pm.product_id = ?
");
$stmt->execute([$product_id]);
$product_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">

    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold">Расходники для товара: <?= htmlspecialchars($product['name']); ?></h1>
      <a href="/admin/products" class="px-4 py-2 bg-gray-500 text-white rounded-lg">Назад</a>
    </div>

    <?php foreach ($notifications as $n): ?>
      <div class="p-4 mb-4 rounded <?= $n['type'] === 'success' ? 'bg-green-200' : 'bg-red-200'; ?>">
        <?= htmlspecialchars($n['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Добавление материала -->
    <div class="bg-white p-6 rounded-xl shadow mb-8">
      <h2 class="text-xl font-bold mb-4">Добавить материал</h2>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_material">
        <div class="flex flex-wrap gap-4 items-end">
          <div>
            <label class="block mb-1">Материал</label>
            <select name="material_id" class="border rounded p-2">
              <option value="">-- выберите --</option>
              <?php foreach ($all_materials as $m): ?>
                <option value="<?= $m['id']; ?>"><?= htmlspecialchars($m['name']); ?> (<?= $m['unit']; ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block mb-1">Количество на ед.</label>
            <input type="number" step="0.0001" min="0" name="quantity_per_unit" class="border rounded p-2">
          </div>
          <button type="submit" class="px-4 py-2 bg-[#118568] text-white rounded">Добавить</button>
        </div>
      </form>
    </div>

    <!-- Список материалов -->
    <div class="bg-white p-6 rounded-xl shadow">
      <h2 class="text-xl font-bold mb-4">Список материалов</h2>
      <?php if (empty($product_materials)): ?>
        <p class="text-gray-500">Материалы не добавлены.</p>
      <?php else: ?>
        <table class="w-full border">
          <thead>
            <tr class="bg-gray-100">
              <th class="p-2 text-left">Название</th>
              <th class="p-2 text-left">Кол-во на ед.</th>
              <th class="p-2 text-left">Ед.</th>
              <th class="p-2 text-left">Себестоимость</th>
              <th class="p-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($product_materials as $pm): ?>
              <tr>
                <td class="p-2"><?= htmlspecialchars($pm['name']); ?></td>
                <td class="p-2"><?= number_format($pm['quantity_per_unit'], 4, '.', ' '); ?></td>
                <td class="p-2"><?= htmlspecialchars($pm['unit']); ?></td>
                <td class="p-2"><?= $pm['cost_per_unit'] ? number_format($pm['cost_per_unit'], 2, '.', ' ') . ' ₽' : '-'; ?>
                </td>
                <td class="p-2">
                  <form method="POST" onsubmit="return confirm('Удалить?')">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_material">
                    <input type="hidden" name="id" value="<?= $pm['id']; ?>">
                    <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded">Удалить</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php include_once('../../includes/footer.php'); ?>