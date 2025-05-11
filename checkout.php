<?php
session_start();
$pageTitle = "Оформление заказа";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: /cart");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    // Если пользователь не авторизован, перенаправляем на страницу входа/регистрации
    $_SESSION['notifications'][] = ['type' => 'info', 'message' => 'Для оформления заказа необходимо войти или зарегистрироваться.'];
    header("Location: /login");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$cart_items = [];
$total_price = 0;

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

    $total_price += $item['total_price'];
}
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
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Оформление заказа</h1>
  <!-- Содержимое корзины -->
  <div class="grid grid-cols-1 gap-6 mb-8">
    <?php foreach ($cart_items as $item): ?>
      <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($item['product']['name']); ?></h2>
        <p class="text-gray-600">Количество: <?php echo $item['quantity']; ?></p>
        <p class="text-gray-600">
          Характеристики:
          <?php foreach ($item['attributes'] as $attribute): ?>
            <?php echo htmlspecialchars($attribute['value']); ?> (+<?php echo htmlspecialchars($attribute['price_modifier']); ?> руб.)
          <?php endforeach; ?>
        </p>
        <p class="text-lg font-semibold text-green-600">Итого: <?php echo htmlspecialchars($item['total_price']); ?> руб.</p>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mb-8">
    <p class="text-xl font-bold text-right text-gray-800">
      Общая стоимость: <?php echo htmlspecialchars($total_price); ?> руб.
    </p>
  </div>

  <!-- Форма оформления заказа -->
  <form action="/checkoutshopcart/process" method="POST" class="max-w-lg mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Контактные данные</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" placeholder="Имя" class="w-full px-4 py-2 border rounded-lg" required>
      <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Email" class="w-full px-4 py-2 border rounded-lg" required>
      <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="Телефон" class="w-full px-4 py-2 border rounded-lg" required>
    </div>

    <h2 class="text-2xl font-bold text-gray-800 mt-6 mb-4">Адрес доставки</h2>
    <textarea name="shipping_address" placeholder="Введите адрес доставки" class="w-full px-4 py-2 border rounded-lg" rows="4" required><?php echo htmlspecialchars($user['shipping_address']); ?></textarea>

    <button type="submit" class="mt-6 w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
      Оформить заказ
    </button>
  </form>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>