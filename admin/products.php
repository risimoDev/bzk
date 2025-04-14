<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Добавление нового товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = htmlspecialchars($_POST['name']);
    $base_price = floatval($_POST['base_price']);
    $image = $_FILES['image'];

    // Проверка загрузки изображения
    if ($image['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../assets/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Создаем директорию, если её нет
        }
        $image_path = $upload_dir . basename($image['name']);
        move_uploaded_file($image['tmp_name'], $image_path);
        $image_url = "/assets/images/" . basename($image['name']);
    } else {
        $image_url = "/assets/images/default.jpg"; // Заглушка
    }

    $stmt = $pdo->prepare("INSERT INTO calculator_products (name, base_price, image) VALUES (?, ?, ?)");
    $stmt->execute([$name, $base_price, $image_url]);
}

// Удаление товара
if (isset($_GET['delete_product'])) {
    $id = intval($_GET['delete_product']);
    $stmt = $pdo->prepare("DELETE FROM calculator_products WHERE id = ?");
    $stmt->execute([$id]);
}

// Получение списка товаров
$stmt = $pdo->query("SELECT * FROM calculator_products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Управление товарами -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление товарами</h1>

    <!-- Форма добавления товара -->
    <section class="mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Добавить новый товар</h2>
      <form action="" method="POST" enctype="multipart/form-data" class="max-w-lg">
        <div class="mb-4">
          <label for="name" class="block text-gray-700 font-medium mb-2">Название</label>
          <input type="text" id="name" name="name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <div class="mb-4">
          <label for="base_price" class="block text-gray-700 font-medium mb-2">Базовая цена</label>
          <input type="number" step="0.01" id="base_price" name="base_price" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <div class="mb-6">
          <label for="image" class="block text-gray-700 font-medium mb-2">Изображение</label>
          <input type="file" id="image" name="image" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
        </div>
        <button type="submit" name="add_product" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
          Добавить товар
        </button>
      </form>
    </section>

    <!-- Таблица товаров -->
    <section class="overflow-x-auto">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Текущие товары</h2>
      <table class="min-w-full bg-white border border-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="py-2 px-4 text-left">ID</th>
            <th class="py-2 px-4 text-left">Название</th>
            <th class="py-2 px-4 text-left">Базовая цена</th>
            <th class="py-2 px-4 text-left">Изображение</th>
            <th class="py-2 px-4 text-left">Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $product): ?>
          <tr class="border-t border-gray-200">
            <td class="py-2 px-4"><?php echo htmlspecialchars($product['id']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($product['name']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($product['base_price']); ?> ₽</td>
            <td class="py-2 px-4">
              <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-16 h-16 object-cover">
            </td>
            <td class="py-2 px-4">
              <a href="?delete_product=<?php echo $product['id']; ?>" class="text-red-600 hover:text-red-700">Удалить</a>
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