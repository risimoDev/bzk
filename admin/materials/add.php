<?php
// admin/materials/add.php
session_start();
$pageTitle = "Добавить материал";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

include_once('../../includes/db.php');
require_once '../../includes/security.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $name = trim($_POST['name']);
  $unit = trim($_POST['unit']);
  $desc = trim($_POST['description']);

  if ($name && $unit) {
    $stmt = $pdo->prepare("INSERT INTO materials (name,unit,description) VALUES (?,?,?)");
    $stmt->execute([$name, $unit, $desc]);
    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Материал добавлен'];
    header("Location: index.php");
    exit();
  } else {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заполните обязательные поля'];
  }
}
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gray-100 py-8">
  <div class="container mx-auto max-w-3xl bg-white p-6 rounded-2xl shadow-lg">
    <h1 class="text-3xl font-bold mb-6">Добавить материал</h1>
    <form method="post" class="space-y-4">
      <?php echo csrf_field(); ?>
      <div>
        <label class="block mb-1">Название *</label>
        <input type="text" name="name" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block mb-1">Единица измерения *</label>
        <input type="text" name="unit" class="w-full border rounded px-3 py-2" placeholder="шт, м, кг" required>
      </div>
      <div>
        <label class="block mb-1">Описание</label>
        <textarea name="description" class="w-full border rounded px-3 py-2"></textarea>
      </div>
      <button class="bg-[#118568] text-white px-4 py-2 rounded hover:bg-[#0f755a]">Добавить</button>
    </form>
  </div>
</main>