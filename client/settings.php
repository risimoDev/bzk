<?php
session_start();
$pageTitle = "Настройки аккаунта";
include_once('../includes/header.php');

// Подключение к базе данных
include_once('../includes/db.php');

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Пароль успешно изменен.'];
            header("Location: /client/settings");
            exit();
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Новый пароль и подтверждение не совпадают.'];
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Текущий пароль неверный.'];
    }
}
?>

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
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Настройки аккаунта</h1>

  <form action="" method="POST" class="max-w-lg mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Изменение пароля</h2>
    <div class="grid grid-cols-1 gap-4">
      <input type="password" name="password" placeholder="Текущий пароль" class="w-full px-4 py-2 border rounded-lg" required>
      <input type="password" name="new_password" placeholder="Новый пароль" class="w-full px-4 py-2 border rounded-lg" required>
      <input type="password" name="confirm_password" placeholder="Подтвердите новый пароль" class="w-full px-4 py-2 border rounded-lg" required>
    </div>

    <button type="submit" class="mt-6 w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
      Сохранить изменения
    </button>
  </form>
</main>

<?php include_once('../includes/footer.php'); ?>