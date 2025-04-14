<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');
// Обработчик изменения роли пользователя
if (isset($_GET['change_role'])) {
  $id = intval($_GET['user_id']);
  $role = htmlspecialchars($_GET['change_role']);

  // Проверяем, что ID пользователя существует
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user) {
      // Обновляем роль пользователя
      $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
      $success = $stmt->execute([$role, $id]);

      if ($success) {
          $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Роль пользователя успешно обновлена!'];
      } else {
          $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при обновлении роли пользователя.'];
      }
  } else {
      $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пользователь не найден.'];
  }

  header("Location: /admin/users");
  exit();
}

// Получение списка пользователей
$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Управление пользователями -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление пользователями</h1>

    <!-- Таблица пользователей -->
    <section class="overflow-x-auto">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Текущие пользователи</h2>
      <table class="min-w-full bg-white border border-gray-200">
  <thead class="bg-gray-100">
    <tr>
      <th class="py-2 px-4 text-left">ID</th>
      <th class="py-2 px-4 text-left">Имя</th>
      <th class="py-2 px-4 text-left">Email</th>
      <th class="py-2 px-4 text-left">Роль</th>
      <th class="py-2 px-4 text-left">Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $user): ?>
    <tr class="border-t border-gray-200">
      <td class="py-2 px-4"><?php echo htmlspecialchars($user['id']); ?></td>
      <td class="py-2 px-4"><?php echo htmlspecialchars($user['name']); ?></td>
      <td class="py-2 px-4"><?php echo htmlspecialchars($user['email']); ?></td>
      <td class="py-2 px-4"><?php echo htmlspecialchars($user['role']); ?></td>
      <td class="py-2 px-4 space-x-2">
        <a href="?change_role=user&user_id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-800">Клиент</a>
        <a href="?change_role=manager&user_id=<?php echo $user['id']; ?>" class="text-purple-600 hover:text-purple-800">Менеджер</a>
        <a href="?change_role=admin&user_id=<?php echo $user['id']; ?>" class="text-red-600 hover:text-red-800">Администратор</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
    </section>
  </main>

  <!-- Футер -->
  <?php include_once('../includes/footer.php'); ?>

</body>
</html>