<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Получение ID товара из URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID товара не указан.");
}
$id = intval($_GET['id']);

// Загрузка данных о товаре
$stmt = $pdo->prepare("SELECT * FROM calculator_products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Товар не найден.");
}

// Обновление данных товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = htmlspecialchars($_POST['name']);
    $base_price = floatval($_POST['base_price']);

    $stmt = $pdo->prepare("UPDATE calculator_products SET name = ?, base_price = ? WHERE id = ?");
    $stmt->execute([$name, $base_price, $id]);

    echo "<p class='text-green-600 text-center'>Данные успешно обновлены!</p>";
}
?>


  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Редактирование товара -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Редактирование товара</h1>
    <form action="" method="POST" class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md">
      <div class="mb-4">
        <label for="name" class="block text-gray-700 font-medium mb-2">Название</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
      </div>
      <div class="mb-6">
        <label for="base_price" class="block text-gray-700 font-medium mb-2">Базовая цена</label>
        <input type="number" step="0.01" id="base_price" name="base_price" value="<?php echo htmlspecialchars($product['base_price']); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
      </div>
      <button type="submit" name="update_product" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
        Сохранить изменения
      </button>
    </form>
  </main>

  <!-- Футер -->
  <?php include_once('../includes/footer.php'); ?>

</body>
</html>