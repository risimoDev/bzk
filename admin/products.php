<?php
session_start();
$pageTitle = "Управление товарами";
include_once('../includes/header.php');

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: /login");
  exit();
}

// Подключение к базе данных
include_once('../includes/db.php');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action'])) {
      $action = $_POST['action'];

      if ($action === 'add_product') {
          $name = $_POST['name'];
          $description = $_POST['description'];
          $base_price = $_POST['base_price'];

          $stmt = $pdo->prepare("INSERT INTO products (name, description, base_price) VALUES (?, ?, ?)");
          $stmt->execute([$name, $description, $base_price]);
      } elseif ($action === 'delete_product') {
          $product_id = $_POST['product_id'];
          $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
          $stmt->execute([$product_id]);
      }
  }
}

// Получение списка товаров
$stmt = $pdo->prepare("SELECT * FROM products");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление товарами</h1>

<!-- Форма добавления товара -->
<form action="" method="POST" class="mb-6">
  <input type="hidden" name="action" value="add_product">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <input type="text" name="name" placeholder="Название товара" class="w-full px-4 py-2 border rounded-lg" required>
    <textarea name="description" placeholder="Описание товара" class="w-full px-4 py-2 border rounded-lg"></textarea>
    <input type="number" step="0.01" name="base_price" placeholder="Базовая цена" class="w-full px-4 py-2 border rounded-lg" required>
  </div>
  <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
    Добавить товар
  </button>
</form>

<!-- Список товаров -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  <?php foreach ($products as $product): ?>
    <div class="bg-white p-4 rounded-lg shadow-md">
      <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($product['name']); ?></h2>
      <p class="text-gray-600"><?php echo htmlspecialchars($product['description']); ?></p>
      <p class="text-lg font-semibold text-green-600">Цена: <?php echo htmlspecialchars($product['base_price']); ?> руб.</p>
      <p class="text-gray-600">
        Популярный: 
        <?php echo $product['is_popular'] ? '<span class="text-green-600">Да</span>' : '<span class="text-red-600">Нет</span>'; ?>
      </p>

      <div class="flex justify-between mt-4">
        <a href="/admin/product/edit?id=<?php echo $product['id']; ?>" class="text-blue-600 hover:text-blue-800">Редактировать</a>
        <form action="" method="POST" onsubmit="return confirm('Вы уверены?')">
          <input type="hidden" name="action" value="delete_product">
          <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
          <button type="submit" class="text-red-600 hover:text-red-800">Удалить</button>
        </form>
      </div>

      <!-- Кнопки управления характеристиками и изображениями -->
      <div class="mt-4">
        <a href="/admin/attributes?product_id=<?php echo $product['id']; ?>" class="block text-blue-600 hover:text-blue-800">Управление характеристиками</a>
        <a href="/admin/images?product_id=<?php echo $product['id']; ?>" class="block text-blue-600 hover:text-blue-800">Управление изображениями</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</main>

<?php include_once('../includes/footer.php'); ?>