<?php
session_start();

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Обработка регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $phone = htmlspecialchars($_POST['phone']);

    // Проверка уникальности email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "<p class='text-red-600 text-center'>Пользователь с таким email уже зарегистрирован.</p>";
    } else {
        // Роль по умолчанию: user
        $role = 'user';
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $phone, $role]);
        echo "<p class='text-green-600 text-center'>Вы успешно зарегистрировались!</p>";
    }
}
?>


  <!-- Шапка -->
  <?php include_once __DIR__ . '/includes/header.php'; ?>

  <!-- Форма регистрации -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Регистрация</h1>
    <form action="" method="POST" class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md">
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
        <label for="phone" class="block text-gray-700 font-medium mb-2">Телефон</label>
        <input type="tel" id="phone" name="phone" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
      </div>
      <button type="submit" name="register" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
        Зарегистрироваться
      </button>
    </form>
  </main>

  <!-- Футер -->
  <?php include_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>