<?php
session_start();
$pageTitle = "Личный кабинет | Типография";
include_once('../includes/header.php');

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

// Обработка обновления данных профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $shipping_address = $_POST['shipping_address'];

    // Обновление данных пользователя
    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, shipping_address = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $shipping_address, $user_id]);

    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Данные успешно обновлены.'];
    header("Location: /client/dashboard");
    exit();
}
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Личный кабинет</h1>

  <!-- Основная информация профиля -->
  <div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Профиль</h2>
    <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
  <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['name']); ?>" placeholder="ФИО" class="w-full px-4 py-2 border rounded-lg" required>
  <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100 cursor-not-allowed">
  <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="Телефон" class="w-full px-4 py-2 border rounded-lg">
  <textarea name="shipping_address" placeholder="Адрес доставки" class="w-full px-4 py-2 border rounded-lg" rows="4"><?php echo htmlspecialchars($user['shipping_address']); ?></textarea>

  <!-- Кнопка "Сохранить изменения" -->
  <div class="col-span-full flex justify-center mt-4">
    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-300">
      Сохранить изменения
    </button>
  </div>
</form>
  </div>

  <!-- Меню разделов -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <a href="/client/orders" class="bg-white p-6 rounded-lg shadow-md text-center hover:bg-litegray transition duration-300">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h2a2 2 0 000-4M9 9a2 2 0 010 4h2a2 2 0 010-4m0 4a2 2 0 012 2v3m2 4H9.83a3 3 0 01-2.12-.88l-1.88-1.88A3 3 0 015 14.17V12" />
      </svg>
      <h2 class="text-xl font-bold text-gray-800 mt-4">История заказов</h2>
    </a>

    <a href="/client/settings" class="bg-white p-6 rounded-lg shadow-md text-center hover:bg-litegray transition duration-300">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <h2 class="text-xl font-bold text-gray-800 mt-4">Настройки аккаунта</h2>
    </a>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>