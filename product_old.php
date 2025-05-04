<?php
session_start();
$pageTitle = "Товар | Типография";
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID товара не указан.");
}
$id = intval($_GET['id']);

$product_stmt = $pdo->prepare("SELECT * FROM calculator_products WHERE id = ?");
$product_stmt->execute([$id]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Товар не найден.");
}

$attribute_stmt = $pdo->prepare("SELECT * FROM calculator_attributes WHERE product_id = ?");
$attribute_stmt->execute([$id]);
$attributes = $attribute_stmt->fetchAll(PDO::FETCH_ASSOC);

$productImage = !empty($product['image']) ? "/images/" . htmlspecialchars($product['image']) : "https://via.placeholder.com/500";
$productDescription = !empty($product['description']) ? htmlspecialchars($product['description']) : "Описание временно отсутствует.";
$basePrice = floatval($product['base_price']);

// Получаем данные о товаре
$product_id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM calculator_products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Получаем характеристики и их значения
$attribute_query = $pdo->prepare("
    SELECT at.id AS type_id, at.name AS type_name, av.id AS value_id, av.value_name, av.price_multiplier
    FROM attribute_types at
    JOIN attribute_values av ON at.id = av.attribute_type_id
    WHERE at.product_id = ?
    ORDER BY at.name, av.value_name
");
$attribute_query->execute([$product_id]);
$attributes_raw = $attribute_query->fetchAll(PDO::FETCH_ASSOC);

// Группировка по типу характеристики
$attributes = [];
foreach ($attributes_raw as $row) {
    $attributes[$row['type_name']][] = $row;
}
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6"><?= htmlspecialchars($product['name']) ?></h1>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <div>
      <img src="<?= $productImage ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-auto rounded-xl shadow-lg object-cover">
    </div>

    <div>
      <h2 class="text-2xl font-bold text-gray-800 mb-2">Описание</h2>
      <p class="text-gray-700 leading-relaxed mb-6 whitespace-pre-line"><?= $productDescription ?></p>

      <?php if ($attributes): ?>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Характеристики</h2>
        <ul class="space-y-2 mb-6">
          <?php foreach ($attributes as $attr): ?>
            <li class="text-gray-700">
              <strong><?= htmlspecialchars($attr['attribute_name']) ?>:</strong> 
              <?= htmlspecialchars($attr['attribute_value']) ?> 
              <span class="text-sm text-gray-500">(×<?= htmlspecialchars($attr['price_multiplier']) ?>)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <h2 class="text-2xl font-bold text-gray-800 mb-4">Оформить заказ</h2>
      <form action="/order" method="POST" class="space-y-4" id="orderForm">
        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
        <input type="hidden" id="base_price" value="<?= $basePrice ?>">

        <!-- Тираж -->
        <div>
          <label for="quantity" class="block text-gray-700 font-medium mb-1">Тираж</label>
          <input type="number" id="quantity" name="quantity" min="1" value="1"
                 class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Характеристика -->
        <?php if ($attributes): ?>
        <div>
          <label for="attribute" class="block text-gray-700 font-medium mb-1">Характеристика</label>
          <select id="attribute" name="attribute_id"
                  class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <?php foreach ($attributes as $attr): ?>
              <option value="<?= $attr['id'] ?>" data-multiplier="<?= $attr['price_multiplier'] ?>">
                <?= htmlspecialchars($attr['attribute_value']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <!-- Итоговая цена -->
        <div class="text-xl font-semibold text-gray-800">
          Итоговая цена: <span id="totalPrice">0</span> ₽
        </div>

        <!-- Кнопка -->
        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300 font-semibold">
          Оформить заказ
        </button>
      </form>
    </div>
  </div>
</main>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const basePrice = parseFloat(document.getElementById("base_price").value);
    const quantityInput = document.getElementById("quantity");
    const attributeSelect = document.getElementById("attribute");
    const totalPriceSpan = document.getElementById("totalPrice");

    function updatePrice() {
      const quantity = parseInt(quantityInput.value) || 0;
      const multiplier = parseFloat(attributeSelect.selectedOptions[0].dataset.multiplier) || 1;
      const total = (basePrice * multiplier * quantity).toFixed(2);
      totalPriceSpan.textContent = total;
    }

    quantityInput.addEventListener("input", updatePrice);
    attributeSelect.addEventListener("change", updatePrice);

    updatePrice(); // при загрузке
  });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>