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

    $stmt = $pdo->prepare("INSERT INTO calculator_products (name, base_price) VALUES (?, ?)");
    $stmt->execute([$name, $base_price]);
}

// Удаление товара
if (isset($_GET['delete_product'])) {
    $id = $_GET['delete_product'];
    $stmt = $pdo->prepare("DELETE FROM calculator_products WHERE id = ?");
    $stmt->execute([$id]);
}

// Добавление новой характеристики
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attribute'])) {
    $product_id = intval($_POST['product_id']);
    $attribute_name = htmlspecialchars($_POST['attribute_name']);
    $attribute_value = htmlspecialchars($_POST['attribute_value']);
    $price_multiplier = floatval($_POST['price_multiplier']);

    $stmt = $pdo->prepare("INSERT INTO calculator_attributes (product_id, attribute_name, attribute_value, price_multiplier) VALUES (?, ?, ?, ?)");
    $stmt->execute([$product_id, $attribute_name, $attribute_value, $price_multiplier]);
}

// Удаление характеристики
if (isset($_GET['delete_attribute'])) {
    $id = $_GET['delete_attribute'];
    $stmt = $pdo->prepare("DELETE FROM calculator_attributes WHERE id = ?");
    $stmt->execute([$id]);
}

// Получение данных о товарах и характеристиках
$product_stmt = $pdo->query("SELECT * FROM calculator_products");
$products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);

$attribute_stmt = $pdo->query("
    SELECT ca.id AS attribute_id, cp.name AS product_name, ca.attribute_name, ca.attribute_value, ca.price_multiplier
    FROM calculator_attributes ca
    JOIN calculator_products cp ON ca.product_id = cp.id
");
$attributes = $attribute_stmt->fetchAll(PDO::FETCH_ASSOC);
?>


  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Настройки калькулятора -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Настройки калькулятора</h1>

    <!-- Форма добавления товара -->
    <section class="mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Добавить новый товар</h2>
      <form action="" method="POST" class="max-w-lg">
        <div class="mb-4">
          <label for="name" class="block text-gray-700 font-medium mb-2">Название товара</label>
          <input type="text" id="name" name="name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <div class="mb-6">
          <label for="base_price" class="block text-gray-700 font-medium mb-2">Базовая цена</label>
          <input type="number" step="0.01" id="base_price" name="base_price" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <button type="submit" name="add_product" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
          Добавить товар
        </button>
      </form>
    </section>

    <!-- Таблица товаров -->
    <section class="mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Текущие товары</h2>
      <table class="min-w-full bg-white border border-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="py-2 px-4 text-left">ID</th>
            <th class="py-2 px-4 text-left">Название</th>
            <th class="py-2 px-4 text-left">Базовая цена</th>
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
              <a href="?delete_product=<?php echo $product['id']; ?>" class="text-red-600 hover:text-red-700">Удалить</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <!-- Форма добавления характеристики -->
    <section class="mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Добавить характеристику</h2>
      <form action="" method="POST" class="max-w-lg">
        <div class="mb-4">
          <label for="product_id" class="block text-gray-700 font-medium mb-2">Товар</label>
          <select id="product_id" name="product_id" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
            <?php foreach ($products as $product): ?>
            <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-4">
          <label for="attribute_name" class="block text-gray-700 font-medium mb-2">Название характеристики</label>
          <input type="text" id="attribute_name" name="attribute_name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <div class="mb-4">
          <label for="attribute_value" class="block text-gray-700 font-medium mb-2">Значение характеристики</label>
          <input type="text" id="attribute_value" name="attribute_value" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <div class="mb-6">
          <label for="price_multiplier" class="block text-gray-700 font-medium mb-2">Коэффициент цены</label>
          <input type="number" step="0.01" id="price_multiplier" name="price_multiplier" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
        </div>
        <button type="submit" name="add_attribute" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
          Добавить характеристику
        </button>
      </form>
    </section>

    <!-- Таблица характеристик -->
    <section>
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Текущие характеристики</h2>
      <table class="min-w-full bg-white border border-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="py-2 px-4 text-left">ID</th>
            <th class="py-2 px-4 text-left">Товар</th>
            <th class="py-2 px-4 text-left">Характеристика</th>
            <th class="py-2 px-4 text-left">Значение</th>
            <th class="py-2 px-4 text-left">Коэффициент цены</th>
            <th class="py-2 px-4 text-left">Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attributes as $attribute): ?>
          <tr class="border-t border-gray-200">
            <td class="py-2 px-4"><?php echo htmlspecialchars($attribute['attribute_id']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($attribute['product_name']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($attribute['attribute_name']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($attribute['attribute_value']); ?></td>
            <td class="py-2 px-4"><?php echo htmlspecialchars($attribute['price_multiplier']); ?></td>
            <td class="py-2 px-4">
              <a href="?delete_attribute=<?php echo $attribute['attribute_id']; ?>" class="text-red-600 hover:text-red-700">Удалить</a>
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