<?php
session_start();
include_once('../includes/header.php');
// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: /login");
  exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'];
  $phone = $_POST['phone'];
  $shipping_address = $_POST['shipping_address'];

  // Обновление данных пользователя
  $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, shipping_address = ? WHERE id = ?");
  $stmt->execute([$name, $phone, $shipping_address, $user_id]);

  header("Location: /client/dashboard");
  exit();
}
?>

<main class="container mx-auto px-4 py-8">
<h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Личный кабинет</h1>

<form action="" method="POST" class="max-w-lg mx-auto">
  <h2 class="text-2xl font-bold text-gray-800 mb-4">Мои данные</h2>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" placeholder="Имя" class="w-full px-4 py-2 border rounded-lg" required>
    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100 cursor-not-allowed">
    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="Телефон" class="w-full px-4 py-2 border rounded-lg">
  </div>

  <h2 class="text-2xl font-bold text-gray-800 mt-6 mb-4">Адрес доставки</h2>
  <textarea name="shipping_address" placeholder="Введите адрес доставки" class="w-full px-4 py-2 border rounded-lg" rows="4"><?php echo htmlspecialchars($user['shipping_address']); ?></textarea>

  <button type="submit" class="mt-6 w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
    Сохранить изменения
  </button>
</form>
</main>

<?php include_once('../includes/footer.php'); ?>