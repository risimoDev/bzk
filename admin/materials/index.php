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
require_once '../../includes/security.php';
// Уведомления
$notifications = $_SESSION['notifications'] ?? [];
unset($_SESSION['notifications']);

// Добавление материала
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
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

  header("Location: " . $_SERVER['REQUEST_URI']);
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

<?php include_once('../../includes/header.php'); ?>

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
        <?php echo csrf_field(); ?>
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
        <div id="materials-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($materials as $m): ?>
            <div
              class="material-card rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 hover:shadow-md transition duration-300">
              <div class="p-5 border-b border-gray-100">
                <div class="flex items-start justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-11 h-11 bg-[#118568] rounded-xl flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                      </svg>
                    </div>
                    <div>
                      <h3 class="font-bold text-gray-900 text-lg leading-tight"><?= htmlspecialchars($m['name']); ?></h3>
                      <p class="text-xs text-gray-500">Ед. изм.: <span
                          class="font-medium text-gray-700"><?= htmlspecialchars($m['unit']); ?></span></p>
                    </div>
                  </div>
                  <span
                    class="px-3 py-1 rounded-full text-xs font-semibold <?php echo ($m['stock'] > 0) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?= $m['stock'] > 0 ? 'В наличии' : 'Нет в наличии'; ?>
                  </span>
                </div>
              </div>

              <form method="POST" class="p-5 space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_material">
                <input type="hidden" name="id" value="<?= $m['id']; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Название</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($m['name']); ?>"
                      class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Единица</label>
                    <input type="text" name="unit" value="<?= htmlspecialchars($m['unit']); ?>"
                      class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Себестоимость</label>
                    <input type="number" step="0.01" name="cost_per_unit" value="<?= $m['cost_per_unit']; ?>"
                      class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Остаток</label>
                    <div class="px-3 py-2 bg-gray-100 rounded-lg text-sm font-medium text-gray-700">
                      <?= number_format($m['stock'], 2, '.', ' '); ?>
                    </div>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                  <button type="submit"
                    class="flex-1 min-w-0 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">
                    Сохранить
                  </button>
                  <a href="movement_add?material_id=<?= $m['id']; ?>"
                    class="flex-1 min-w-0 text-center px-3 py-2 bg-[#118568] text-white rounded-lg text-sm hover:bg-[#0f755a] transition">
                    Движение
                  </a>
                  <a href="movements?material_id=<?= $m['id']; ?>"
                    class="flex-1 min-w-0 text-center px-3 py-2 bg-gray-500 text-white rounded-lg text-sm hover:bg-gray-600 transition">
                    История
                  </a>
                </div>
              </form>

              <form method="POST" onsubmit="return confirm('Удалить материал?')" class="p-5 pt-0">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_material">
                <input type="hidden" name="id" value="<?= $m['id']; ?>">
                <button type="submit"
                  class="w-full px-3 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
                  Удалить
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="materials-pagination" class="mt-6 flex items-center justify-center gap-2">
          <button type="button" id="materials-prev"
            class="px-3 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">←</button>
          <span id="materials-page-info" class="text-sm text-gray-700">Страница 1</span>
          <button type="button" id="materials-next"
            class="px-3 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">→</button>
        </div>
      <?php endif; ?>
    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('materials-grid');
    const prev = document.getElementById('materials-prev');
    const next = document.getElementById('materials-next');
    const info = document.getElementById('materials-page-info');
    const pageSize = 9;
    let currentPage = 1;

    function renderPage() {
      if (!grid) return;
      const cards = grid.querySelectorAll('.material-card');
      const total = cards.length;
      const pages = Math.max(1, Math.ceil(total / pageSize));
      if (currentPage > pages) currentPage = pages;
      const start = (currentPage - 1) * pageSize;
      const end = start + pageSize;
      cards.forEach((card, idx) => {
        card.style.display = (idx >= start && idx < end) ? '' : 'none';
      });
      if (info) info.textContent = `Страница ${currentPage} / ${pages}`;
      if (prev) prev.disabled = currentPage <= 1;
      if (next) next.disabled = currentPage >= pages;
    }

    if (prev) prev.addEventListener('click', () => { if (currentPage > 1) { currentPage--; renderPage(); } });
    if (next) next.addEventListener('click', () => { currentPage++; renderPage(); });

    renderPage();
  });
</script>