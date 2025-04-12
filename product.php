<?php
session_start();
$pageTitle = "Товар | Типография";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Получение ID товара из URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID товара не указан.");
}
$id = intval($_GET['id']);

// Загрузка данных о товаре
$product_stmt = $pdo->prepare("SELECT * FROM calculator_products WHERE id = ?");
$product_stmt->execute([$id]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Товар не найден.");
}

// Загрузка характеристик товара
$attribute_stmt = $pdo->prepare("SELECT * FROM calculator_attributes WHERE product_id = ?");
$attribute_stmt->execute([$id]);
$attributes = $attribute_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6"><?php echo htmlspecialchars($product['name']); ?></h1>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <!-- Левая колонка: фото -->
    <div>
      <img src="https://via.placeholder.com/500" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-auto rounded-lg shadow-md">
    </div>
    <!-- Правая колонка: информация -->
    <div>
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Описание</h2>
      <p class="text-gray-700 leading-relaxed mb-6">
        Здесь может быть описание товара. Например, подробности о материале, размерах или технологии производства.
      </p>

      <!-- Характеристики -->
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Характеристики</h2>
      <ul class="space-y-2">
        <?php foreach ($attributes as $attr): ?>
        <li class="text-gray-700">
          <strong><?php echo htmlspecialchars($attr['attribute_name']); ?>:</strong> 
          <?php echo htmlspecialchars($attr['attribute_value']); ?> (Множитель: ×<?php echo htmlspecialchars($attr['price_multiplier']); ?>)
        </li>
        <?php endforeach; ?>
      </ul>

      <!-- Форма заказа -->
      <h2 class="text-2xl font-bold text-gray-800 mt-8 mb-4">Оформить заказ</h2>
      <form action="/order" method="POST" class="space-y-4">
        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
        <div>
          <label for="quantity" class="block text-gray-700 font-medium mb-2">Тираж</label>
          <input type="number" id="quantity" name="quantity" min="1" value="1" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
        </div>
        <div>
          <label for="attribute" class="block text-gray-700 font-medium mb-2">Характеристика</label>
          <select id="attribute" name="attribute_id" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
            <?php foreach ($attributes as $attr): ?>
            <option value="<?php echo $attr['id']; ?>"><?php echo htmlspecialchars($attr['attribute_value']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
          Оформить заказ
        </button>
      </form>
    </div>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>