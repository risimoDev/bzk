<?php
session_start();
$pageTitle = "Сброс пароля";

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Проверка токена и его срока действия
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Обновление пароля
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->execute([$password, $user['id']]);

        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Пароль успешно изменен!'];
        header("Location: /login");
        exit();
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Недействительный или истекший токен.'];
        header("Location: /forgot-password");
        exit();
    }
}

// Проверка токена из GET-параметра
$token = $_GET['token'] ?? null;

if (!$token) {
    header("Location: /404");
    exit();
}

// Проверка действительности токена
$stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Недействительный или истекший токен.'];
    header("Location: /forgot-password");
    exit();
}
?>

<?php include_once __DIR__ . '/includes/header.php';?>

<main class="container mx-auto px-4 py-8">
  <!-- Вставка breadcrumbs и кнопки "Назад" -->
<div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <!-- Breadcrumbs -->
    <div>
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
    </div>

    <!-- Кнопка "Назад" -->
    <div>
        <?php echo backButton(); ?>
    </div>
</div>
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Сброс пароля</h1>
  <form action="/reset-password" method="POST" class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
    <div class="mb-4">
      <label for="password" class="block text-gray-700 font-medium mb-2">Новый пароль</label>
      <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
    </div>
    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
      Сохранить новый пароль
    </button>
  </form>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>