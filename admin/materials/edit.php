<?php
// admin/materials/edit.php
session_start();
$pageTitle = "Редактировать материал";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login"); exit();
}

include_once('../../includes/db.php');
require_once '../../includes/security.php';
$id = $_GET['id'] ?? null;
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id=?");
$stmt->execute([$id]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) { echo "Материал не найден"; exit(); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $name = trim($_POST['name']);
    $unit = trim($_POST['unit']);
    $desc = trim($_POST['description']);
    $stmt = $pdo->prepare("UPDATE materials SET name=?, unit=?, description=? WHERE id=?");
    $stmt->execute([$name,$unit,$desc,$id]);
    $_SESSION['notifications'][]=['type'=>'success','message'=>'Материал обновлён'];
    header("Location: index.php"); exit();
}
?>

<?php include_once('../../includes/header.php');?>

<main class="min-h-screen bg-gray-100 py-8">
  <div class="container mx-auto max-w-3xl bg-white p-6 rounded-2xl shadow-lg">
    <h1 class="text-3xl font-bold mb-6">Редактировать материал</h1>
    <form method="post" class="space-y-4">
      <?php echo csrf_field(); ?>
      <div>
        <label class="block mb-1">Название *</label>
        <input type="text" name="name" value="<?= htmlspecialchars($material['name']); ?>" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block mb-1">Единица *</label>
        <input type="text" name="unit" value="<?= htmlspecialchars($material['unit']); ?>" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block mb-1">Описание</label>
        <textarea name="description" class="w-full border rounded px-3 py-2"><?= htmlspecialchars($material['description']); ?></textarea>
      </div>
      <button class="bg-[#118568] text-white px-4 py-2 rounded">Сохранить</button>
    </form>
  </div>
</main>
