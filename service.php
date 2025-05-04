<?php
session_start();
$pageTitle = "Товар | Типография";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    die("Товар не найден.");
}

// Получение информации о товаре
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Товар не найден.");
}

// Получение изображений товара
$stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Получение характеристик товара
$stmt = $pdo->prepare("
    SELECT pa.id AS attribute_id, pa.name, pa.type, av.id AS value_id, av.value, av.price_modifier 
    FROM product_attributes pa 
    LEFT JOIN attribute_values av ON pa.id = av.attribute_id 
    WHERE pa.product_id = ?
");
$stmt->execute([$product_id]);
$attributes = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6"><?php echo htmlspecialchars($product['name']); ?></h1>

  <!-- Галерея изображений -->
  <div class="mb-6">
    <div class="flex overflow-x-auto">
      <?php foreach ($images as $image): ?>
        <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-48 h-48 object-cover mr-4 rounded-lg">
      <?php endforeach; ?>
    </div>
  </div>

  <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($product['description']); ?></p>

  <!-- Форма выбора характеристик -->
  <form action="/cart/add" method="POST" class="mb-4">
    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

    <div class="mb-4">
      <label for="quantity" class="block text-gray-700 font-medium mb-2">Количество</label>
      <input type="number" id="quantity" name="quantity" value="1" min="1" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
    </div>

    <?php if (!empty($attributes)): ?>
      <?php foreach ($attributes as $attribute_id => $values): ?>
        <div class="mb-4">
          <strong><?php echo htmlspecialchars($values[0]['name']); ?>:</strong>
          <?php foreach ($values as $value): ?>
            <label class="block">
              <input type="radio" name="attributes[<?php echo $attribute_id; ?>]" value="<?php echo $value['value_id']; ?>" required>
              <?php echo htmlspecialchars($value['value']); ?>
              <?php if ($value['price_modifier'] > 0): ?>
                (+<?php echo htmlspecialchars($value['price_modifier']); ?> руб.)
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>Нет доступных характеристик.</p>
    <?php endif; ?>

    <div class="mb-4">
      <p class="text-lg font-semibold text-green-600">Итого: <span id="total-price"><?php echo htmlspecialchars($product['base_price']); ?></span> руб.</p>
    </div>

    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
      Добавить в корзину
    </button>
  </form>
</main>

<script>
  // Обновление стоимости в реальном времени
  document.addEventListener('DOMContentLoaded', () => {
    const basePrice = parseFloat(<?php echo $product['base_price']; ?>);
    const totalPriceElement = document.getElementById('total-price');
    const radioInputs = document.querySelectorAll('input[type="radio"]');

    function updateTotalPrice() {
      let total = basePrice;
      radioInputs.forEach(input => {
        if (input.checked) {
          const modifier = parseFloat(input.dataset.priceModifier || 0);
          total += modifier;
        }
      });
      totalPriceElement.textContent = total.toFixed(2);
    }

    radioInputs.forEach(input => {
      input.addEventListener('change', updateTotalPrice);
    });

    // Инициализация при загрузке
    updateTotalPrice();
  });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>