<?php
session_start();
$pageTitle = "Редактирование товара | Админ-панель";
include_once('../../includes/header.php');

// Подключение к базе данных
include_once('../../includes/db.php');

$product_id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $base_price = $_POST['base_price'];
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;

    // Обновление данных товара
    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, base_price = ?, is_popular = ? WHERE id = ?");
    $stmt->execute([$name, $description, $base_price, $is_popular, $product_id]);

    header("Location: /admin/products");
    exit();
}

// Получение информации о товаре
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Редактирование товара</h1>

  <form action="" method="POST">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" placeholder="Название товара" class="w-full px-4 py-2 border rounded-lg" required>
      <textarea name="description" placeholder="Описание товара" class="w-full px-4 py-2 border rounded-lg"><?php echo htmlspecialchars($product['description']); ?></textarea>
      <input type="number" step="0.01" name="base_price" value="<?php echo htmlspecialchars($product['base_price']); ?>" placeholder="Базовая цена" class="w-full px-4 py-2 border rounded-lg" required>
      <div class="flex items-center">
        <label for="is_popular" class="mr-2">Популярный товар:</label>
        <input type="checkbox" id="is_popular" name="is_popular" <?php echo $product['is_popular'] ? 'checked' : ''; ?>>
      </div>
    </div>
    <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
      Сохранить изменения
    </button>
  </form>
</main>

<?php include_once('../../includes/footer.php'); ?>