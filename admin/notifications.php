<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Добавление нового уведомления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notification'])) {
    $stmt = $pdo->prepare("INSERT INTO notifications (title, message, type, active) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['title'],
        $_POST['message'],
        $_POST['type'],
        isset($_POST['active']) ? 1 : 0
    ]);
    header("Location: notifications.php?success=1");
    exit;
}

// Включение/выключение
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->query("UPDATE notifications SET active = 1 - active WHERE id = $id");
    header("Location: notifications.php");
    exit;
}

// Удаление
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->query("DELETE FROM notifications WHERE id = $id");
    header("Location: notifications.php");
    exit;
}

$notifications = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="p-6">
  <h1 class="text-2xl font-bold mb-4">Уведомления</h1>

  <!-- Форма -->
  <form method="POST" class="mb-6 space-y-4 p-4 bg-white rounded-xl shadow">
    <input type="text" name="title" placeholder="Заголовок" required class="w-full border p-2 rounded">
    <textarea name="message" placeholder="Сообщение" required class="w-full border p-2 rounded"></textarea>
    <select name="type" class="border p-2 rounded">
      <option value="info">Инфо</option>
      <option value="success">Успех</option>
      <option value="warning">Предупреждение</option>
      <option value="error">Ошибка</option>
    </select>
    <label class="inline-flex items-center">
      <input type="checkbox" name="active" checked class="mr-2"> Активно
    </label>
    <button type="submit" name="add_notification" class="bg-green-600 text-white px-4 py-2 rounded">Добавить</button>
  </form>

  <!-- Список -->
  <div class="space-y-3">
    <?php foreach ($notifications as $n): ?>
      <div class="p-4 bg-gray-50 rounded-lg flex justify-between items-center">
        <div>
          <h3 class="font-bold"><?= htmlspecialchars($n['title']) ?></h3>
          <p class="text-sm text-gray-600"><?= htmlspecialchars($n['message']) ?></p>
          <span class="text-xs px-2 py-1 rounded 
            <?= $n['type']=='success'?'bg-green-100 text-green-700':
               ($n['type']=='warning'?'bg-yellow-100 text-yellow-700':
               ($n['type']=='error'?'bg-red-100 text-red-700':'bg-blue-100 text-blue-700')) ?>">
            <?= $n['type'] ?>
          </span>
          <span class="ml-2 text-xs <?= $n['active']?'text-green-600':'text-gray-400' ?>">
            <?= $n['active']?'Активно':'Выключено' ?>
          </span>
        </div>
        <div class="flex gap-2">
          <a href="?toggle=<?= $n['id'] ?>" class="text-blue-600">Переключить</a>
          <a href="?delete=<?= $n['id'] ?>" class="text-red-600" onclick="return confirm('Удалить уведомление?')">Удалить</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
