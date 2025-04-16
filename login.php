<?php
session_start();

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Проверка данных пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Добавляем уведомление
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Вы успешно вошли!'];

        header("Location: " . ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager' ? '/admin' : '/client/dashboard'));
        exit();
    } else {
        // Добавляем уведомление об ошибке
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Неверный email или пароль.'];
        header("Location: /login");
        exit();
    }
  }    
?>


  <!-- Шапка -->
  <?php include_once __DIR__ . '/includes/header.php'; ?>

  <!-- Форма входа -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Вход</h1>
    <form action="/login" method="POST" class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
  <h2 class="text-2xl font-bold text-gray-800 mb-4">Вход</h2>
  <div class="mb-4">
    <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
    <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
  </div>
  <div class="mb-4">
    <label for="password" class="block text-gray-700 font-medium mb-2">Пароль</label>
    <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
  </div>
  <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
    Войти
  </button>
  <p class="mt-4 text-center">
    <a href="/forgot-password" class="text-blue-600 hover:text-blue-800">Забыли пароль?</a>
  </p>
</form>
  </main>

  <!-- Футер -->
  <?php include_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>