<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Добавление нового сотрудника
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manager'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    // Проверка уникальности email
    $stmt = $pdo->prepare("SELECT * FROM managers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "<p class='text-red-600 text-center'>Сотрудник с таким email уже зарегистрирован.</p>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO managers (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role]);
        echo "<p class='text-green-600 text-center'>Сотрудник успешно добавлен!</p>";
    }
}

// Удаление сотрудника
if (isset($_GET['delete_manager'])) {
    $id = $_GET['delete_manager'];
    $stmt = $pdo->prepare("DELETE FROM managers WHERE id = ?");
    $stmt->execute([$id]);
}

// Получение списка сотрудников
$stmt = $pdo->query("SELECT * FROM managers");
$managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Управление сотрудниками -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление сотрудниками</h1>

    <!-- Форма добавления сотрудника -->
    <section class="mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Добавить нового сотрудника</h2>
      <form action="" method="POST" class="max-w-lg">
        <div class="mb-4">
          <label for="name" class="block text-gray-700 font-medium mb-2">Имя</label>
          <input type="text" id="name" name="name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <div class="mb-4">
          <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
          <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <div class="mb-4">
          <label for="password" class="block text-gray-700 font-medium mb-2">Пароль</label>
          <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <div class="mb-6">
          <label for="role" class="block text-gray-700 font-medium mb-2">Роль</label>
          <select id="role" name="role" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
            <option value="manager">Менеджер</option>
            <option value="admin">Администратор</option>
          </select>
        </div>
        <button type="submit" name="add_manager" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
          Добавить сотрудника
        </button>
      </form>
    </section>

    <!-- Таблица сотрудников -->
    <section>
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Текущие сотрудники</h2>
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
          <?php foreach ($managers as $manager): ?>
          <tr class="border-t border-gray-200">
            <td class="py-2 px-4"><?php echo htmlspecialchars($manager['id']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($manager['name']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($manager['email']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($manager['role']); ?></td>
            <td class="py-2 px-4">
              <a href="?delete_manager=<?php echo $manager['id']; ?>" class="text-red-600 hover:text-red-700">Удалить</a>
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