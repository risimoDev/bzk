<?php
// admin/materials/movements.php
session_start();
$pageTitle = "История движения";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login"); exit();
}

include_once('../../includes/header.php');
include_once('../../includes/db.php');

$material_id = $_GET['material_id'] ?? null;
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id=?");
$stmt->execute([$material_id]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) { echo "Материал не найден"; exit(); }

$stmt = $pdo->prepare("SELECT * FROM materials_movements WHERE material_id=? ORDER BY created_at DESC");
$stmt->execute([$material_id]);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<main class="min-h-screen bg-gray-100 py-8">
  <div class="container mx-auto max-w-5xl bg-white p-6 rounded-2xl shadow-lg">
    <h1 class="text-3xl font-bold mb-6">История движения: <?= htmlspecialchars($material['name']); ?></h1>
    <table class="w-full">
      <thead class="bg-gray-200">
        <tr>
          <th class="px-3 py-2">Дата</th>
          <th class="px-3 py-2">Тип</th>
          <th class="px-3 py-2">Кол-во</th>
          <th class="px-3 py-2">Комментарий</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($movements as $mv): ?>
          <tr class="border-b">
            <td class="px-3 py-2"><?= date('d.m.Y H:i',strtotime($mv['created_at'])); ?></td>
            <td class="px-3 py-2"><?= $mv['type']==='in'?'Приход':'Расход'; ?></td>
            <td class="px-3 py-2"><?= $mv['quantity']; ?> <?= htmlspecialchars($material['unit']); ?></td>
            <td class="px-3 py-2"><?= htmlspecialchars($mv['comment']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
