<?php
session_start();
$pageTitle = "Корзина | Типография";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

$cart = $_SESSION['cart'] ?? [];
$cart_items = [];

if (!empty($cart)) {
    foreach ($cart as $item) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        $selected_attributes = [];
        foreach ($item['attributes'] as $attribute_id => $value_id) {
            $stmt = $pdo->prepare("
                SELECT av.value, av.price_modifier 
                FROM attribute_values av 
                JOIN product_attributes pa ON av.attribute_id = pa.id 
                WHERE av.id = ?
            ");
            $stmt->execute([$value_id]);
            $selected_attributes[] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $cart_items[] = [
            'product' => $product,
            'quantity' => $item['quantity'],
            'attributes' => $selected_attributes,
            'total_price' => $item['total_price'],
        ];
    }
}
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Корзина</h1>

  <?php if (empty($cart)): ?>
    <p class="text-center text-gray-600">Ваша корзина пуста.</p>
  <?php else: ?>
    <div class="grid grid-cols-1 gap-6">
      <?php foreach ($cart_items as $item): ?>
        <div class="bg-white p-4 rounded-lg shadow-md">
          <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($item['product']['name']); ?></h2>
          <p class="text-gray-600">Количество: <?php echo $item['quantity']; ?></p>
          <p class="text-gray-600">
            Характеристики:
            <?php foreach ($item['attributes'] as $attribute): ?>
              <?php echo htmlspecialchars($attribute['value']); ?> (+<?php echo htmlspecialchars($attribute['price_modifier']); ?> руб.)
            <?php endforeach; ?>
          </p>
          <p class="text-lg font-semibold text-green-600">Итого: <?php echo htmlspecialchars($item['total_price']); ?> руб.</p>
          <form action="/cart/remove" method="POST" class="mt-4">
            <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>">
            <button type="submit" class="text-red-600 hover:text-red-800">Удалить</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-6 text-right">
      <p class="text-xl font-bold text-gray-800">
        Общая стоимость: <?php echo array_sum(array_column($cart, 'total_price')); ?> руб.
      </p>
      <a href="/checkoutcart" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">Оформить заказ</a>
    </div>
  <?php endif; ?>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>