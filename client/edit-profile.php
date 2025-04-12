<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Получение данных пользователя
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Обработка обновления данных
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);

    // Проверка уникальности email (если он изменён)
    if ($email !== $user['email']) {
        $check_stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->fetch()) {
            echo "<p class='text-red-600 text-center'>Пользователь с таким email уже зарегистрирован.</p>";
        } else {
            $update_stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            $update_stmt->execute([$name, $email, $phone, $user_id]);
            echo "<p class='text-green-600 text-center'>Данные успешно обновлены!</p>";
        }
    } else {
        $update_stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $update_stmt->execute([$name, $phone, $user_id]);
        echo "<p class='text-green-600 text-center'>Данные успешно обновлены!</p>";
    }
}
?>


  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Форма редактирования профиля -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Редактирование профиля</h1>
    <form action="" method="POST" class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md">
      <div class="mb-4">
        <label for="name" class="block text-gray-700 font-medium mb-2">Имя</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
      </div>
      <div class="mb-4">
        <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
      </div>
      <div class="mb-6">
        <label for="phone" class="block text-gray-700 font-medium mb-2">Телефон</label>
        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
      </div>
      <button type="submit" name="update" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
        Сохранить изменения
      </button>
    </form>
  </main>

  <!-- Футер -->
  <?php include_once('../includes/footer.php'); ?>

</body>
</html>