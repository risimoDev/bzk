<?php
// admin/materials/movement_add.php
session_start();
$pageTitle = "Движение материала";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login"); exit();
}

include_once('../../includes/db.php');

$material_id = $_GET['material_id'] ?? null;
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id=?");
$stmt->execute([$material_id]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) { echo "Материал не найден"; exit(); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $type = $_POST['type'];
    $qty = floatval($_POST['quantity']);
    $comment = trim($_POST['comment']);

    if ($qty>0) {
        $stmt = $pdo->prepare("INSERT INTO materials_movements (material_id,type,quantity,reference_type,comment) VALUES (?,?,?,?,?)");
        $stmt->execute([$material_id,$type,$qty,'manual',$comment]);

        // Обновляем остаток
        $stmtStock = $pdo->prepare("INSERT INTO materials_stock (material_id,quantity) VALUES (?,?)
            ON DUPLICATE KEY UPDATE quantity = quantity " . ($type==='in'?'+':'-') . " VALUES(quantity)");
        $stmtStock->execute([$material_id,$qty]);

        $_SESSION['notifications'][]=['type'=>'success','message'=>'Движение записано'];
        header("Location: index.php"); exit();
    }
}
?>

<?php include_once('../../includes/header.php');?>

<main class="min-h-screen bg-gray-100 py-8">
  <div class="container mx-auto max-w-3xl bg-white p-6 rounded-2xl shadow-lg">
    <h1 class="text-3xl font-bold mb-6">Движение: <?= htmlspecialchars($material['name']); ?></h1>
    <form method="post" class="space-y-4">
      <div>
        <label class="block mb-1">Тип движения</label>
        <select name="type" class="w-full border rounded px-3 py-2">
          <option value="in">Приход</option>
          <option value="out">Расход</option>
        </select>
      </div>
      <div>
        <label class="block mb-1">Количество</label>
        <input type="number" step="0.01" name="quantity" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block mb-1">Комментарий</label>
        <textarea name="comment" class="w-full border rounded px-3 py-2"></textarea>
      </div>
      <button class="bg-[#118568] text-white px-4 py-2 rounded">Сохранить</button>
    </form>
  </div>
</main>
