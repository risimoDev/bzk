<?php
// admin/materials/index.php
session_start();
$pageTitle = "Материалы (Склад)";

// Только админ и менеджер
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

include_once('../../includes/db.php');

// Уведомления
$notifications = $_SESSION['notifications'] ?? [];
unset($_SESSION['notifications']);

// Добавление материала
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_material') {
        $name = trim($_POST['name']);
        $unit = trim($_POST['unit']);
        $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);

        if ($name && $unit) {
            $stmt = $pdo->prepare("INSERT INTO materials (name, unit, cost_per_unit) VALUES (?, ?, ?)");
            $stmt->execute([$name, $unit, $cost_per_unit]);

            // Создать запись в stock
            $material_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO materials_stock (material_id, quantity) VALUES (?, 0)")
                ->execute([$material_id]);

            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Материал добавлен.'];
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Введите название и единицу.'];
        }

    } elseif ($action === 'update_material') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $unit = trim($_POST['unit']);
        $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);

        $stmt = $pdo->prepare("UPDATE materials SET name=?, unit=?, cost_per_unit=? WHERE id=?");
        $stmt->execute([$name, $unit, $cost_per_unit, $id]);

        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Материал обновлён.'];

    } elseif ($action === 'delete_material') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM materials WHERE id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM materials_stock WHERE material_id=?")->execute([$id]);
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Материал удалён.'];
    }

    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
}

// Получаем все материалы с остатками
$stmt = $pdo->query("
    SELECT m.id, m.name, m.unit, m.cost_per_unit, 
           COALESCE(ms.quantity, 0) as stock
    FROM materials m
    LEFT JOIN materials_stock ms ON m.id = ms.material_id
    ORDER BY m.name
");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include_once('../../includes/header.php');?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">

    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold">Материалы (Склад)</h1>
      <a href="/admin" class="px-4 py-2 bg-gray-500 text-white rounded-lg">Назад</a>
    </div>

    <?php foreach ($notifications as $n): ?>
      <div class="p-4 mb-4 rounded <?= $n['type'] === 'success' ? 'bg-green-200' : 'bg-red-200'; ?>">
        <?= htmlspecialchars($n['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Добавление материала -->
    <div class="bg-white p-6 rounded-xl shadow mb-8">
      <h2 class="text-xl font-bold mb-4">Добавить материал</h2>
      <form method="POST" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="action" value="add_material">
        <div>
          <label class="block mb-1">Название</label>
          <input type="text" name="name" class="border rounded p-2" required>
        </div>
        <div>
          <label class="block mb-1">Ед. измерения</label>
          <input type="text" name="unit" class="border rounded p-2" required>
        </div>
        <div>
          <label class="block mb-1">Себестоимость ед. (₽)</label>
          <input type="number" step="0.01" name="cost_per_unit" class="border rounded p-2" value="0">
        </div>
        <button type="submit" class="px-4 py-2 bg-[#118568] text-white rounded">Добавить</button>
      </form>
    </div>

    <!-- Список материалов -->
    <div class="bg-white p-6 rounded-xl shadow">
      <h2 class="text-xl font-bold mb-4">Список материалов</h2>
      <?php if (empty($materials)): ?>
        <p class="text-gray-500">Материалы не найдены.</p>
      <?php else: ?>
        <table class="w-full border">
          <thead>
            <tr class="bg-gray-100">
              <th class="p-2 text-left">Название</th>
              <th class="p-2 text-left">Ед.</th>
              <th class="p-2 text-left">Себестоимость</th>
              <th class="p-2 text-left">Остаток</th>
              <th class="p-2"></th>
              <th class="p-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($materials as $m): ?>
              <tr>
                <form method="POST">
                  <input type="hidden" name="action" value="update_material">
                  <input type="hidden" name="id" value="<?= $m['id']; ?>">
                  <td class="p-2">
                    <input type="text" name="name" value="<?= htmlspecialchars($m['name']); ?>" class="border rounded p-1 w-full">
                  </td>
                  <td class="p-2">
                    <input type="text" name="unit" value="<?= htmlspecialchars($m['unit']); ?>" class="border rounded p-1 w-20">
                  </td>
                  <td class="p-2">
                    <input type="number" step="0.01" name="cost_per_unit" value="<?= $m['cost_per_unit']; ?>" class="border rounded p-1 w-24">
                  </td>
                  <td class="p-2"><?= number_format($m['stock'], 2, '.', ' '); ?></td>
                  <td class="p-2 flex gap-2">
                    <button type="submit" class="px-3 py-1 bg-blue-500 text-white rounded">💾</button>
                </form>
                <form method="POST" onsubmit="return confirm('Удалить материал?')">
                  <input type="hidden" name="action" value="delete_material">
                  <input type="hidden" name="id" value="<?= $m['id']; ?>">
                  <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded">🗑</button>
                </form>
                  </td>
                <td class="py-3 px-4">
                  <div class="flex flex-wrap gap-2">
                    <a href="edit?id=<?= $m['id']; ?>" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">Редактировать</a>
                    <a href="movementadd?material_id=<?= $m['id']; ?>" class="px-3 py-1 bg-[#118568] text-white rounded text-sm hover:bg-[#0f755a]">Движение</a>
                    <a href="movements?material_id=<?= $m['id']; ?>" class="px-3 py-1 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">История</a>
                  </div>
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
