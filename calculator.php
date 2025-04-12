<?php
session_start();
$pageTitle = "Калькулятор цен | Типография";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Получение данных о товарах и характеристиках
$stmt = $pdo->query("
    SELECT cp.id AS product_id, cp.name AS product_name, cp.base_price,
           ca.attribute_name, ca.attribute_value, ca.price_multiplier
    FROM calculator_products cp
    LEFT JOIN calculator_attributes ca ON cp.id = ca.product_id
");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-8">Калькулятор цен</h1>
  <form id="price-calculator" class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md">
    <!-- Тип продукции -->
    <div class="mb-4">
      <label for="product-type" class="block text-gray-700 font-medium mb-2">Тип продукции</label>
      <select id="product-type" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
        <option value="0">Выберите тип</option>
        <?php
        foreach ($data as $item) {
            if ($item['attribute_name'] === null) {
                echo "<option value='" . $item['base_price'] . "'>" . htmlspecialchars($item['product_name']) . "</option>";
            }
        }
        ?>
      </select>
    </div>

    <!-- Характеристики -->
    <div id="attributes-container" class="space-y-4"></div>

    <!-- Тираж -->
    <div class="mb-4">
      <label for="quantity" class="block text-gray-700 font-medium mb-2">Тираж</label>
      <input type="number" id="quantity" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" min="1" value="1">
    </div>

    <!-- Итоговая стоимость -->
    <div class="mb-6">
      <h2 class="text-xl font-bold text-gray-800">Итоговая стоимость: <span id="total-price">0 ₽</span></h2>
    </div>

    <!-- Кнопка оформления заказа -->
    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
      Оформить заказ
    </button>
  </form>
</main>

<script>
  const data = <?php echo json_encode($data); ?>;

  const productTypeSelect = document.getElementById('product-type');
  const attributesContainer = document.getElementById('attributes-container');
  const quantityInput = document.getElementById('quantity');
  const totalPriceElement = document.getElementById('total-price');

  // Обновление характеристик при выборе товара
  productTypeSelect.addEventListener('change', () => {
    const selectedProductId = productTypeSelect.value;
    const productAttributes = data.filter(item => item.product_id == selectedProductId);

    attributesContainer.innerHTML = '';

    productAttributes.forEach(attr => {
      if (attr.attribute_name) {
        const div = document.createElement('div');
        div.className = 'mb-4';
        div.innerHTML = `
          <label class="block text-gray-700 font-medium mb-2">${attr.attribute_name}</label>
          <select class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 attribute-select">
            <option value="${attr.price_multiplier}">${attr.attribute_value} (+${(attr.price_multiplier - 1) * 100}%)</option>
          </select>
        `;
        attributesContainer.appendChild(div);
      }
    });

    calculateTotalPrice();
  });

  // Расчет итоговой стоимости
  function calculateTotalPrice() {
    const basePrice = parseFloat(productTypeSelect.value) || 0;
    const quantity = parseInt(quantityInput.value) || 1;

    let multiplier = 1;
    document.querySelectorAll('.attribute-select').forEach(select => {
      multiplier *= parseFloat(select.value);
    });

    const totalPrice = basePrice * multiplier * quantity;
    totalPriceElement.textContent = `${totalPrice.toFixed(2)} ₽`;
  }

  // Пересчет при изменении значений
  quantityInput.addEventListener('input', calculateTotalPrice);
  attributesContainer.addEventListener('change', calculateTotalPrice);
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>