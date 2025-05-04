<?php
session_start();
$pageTitle = "Детали заказа | Админ-панель";
include_once('../../includes/header.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');

$statuses = [
  'pending' => 'В ожидании',
  'processing' => 'В обработке',
  'shipped' => 'Отправлен',
  'delivered' => 'Доставлен',
  'cancelled' => 'Отменен',
  'completed' => 'Полностью готов'
];

$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: /admin/orders");
    exit();
}

// Получение информации о заказе
$stmt = $pdo->prepare("
    SELECT o.*, u.name AS user_name, u.email, u.phone 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: /admin/orders");
    exit();
}

// Получение товаров в заказе
$stmt = $pdo->prepare("
    SELECT oi.quantity, oi.price, p.name AS product_name, oi.attributes
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Функция для получения названий характеристик
function getAttributeNames($pdo, $attributes) {
  $result = [];
  foreach ($attributes as $attribute_id => $value_id) {
      $stmt = $pdo->prepare("
          SELECT av.value 
          FROM attribute_values av 
          JOIN product_attributes pa ON av.attribute_id = pa.id 
          WHERE av.id = ?
      ");
      $stmt->execute([$value_id]);
      $value = $stmt->fetchColumn();
      if ($value) {
          $result[] = $value;
      }
  }
  return implode(', ', $result);
}
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Детали заказа #<?php echo htmlspecialchars($order['id']); ?></h1>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Информация о заказе -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h2 class="text-xl font-bold text-gray-800">Информация о заказе</h2>
      <p class="text-gray-600">Пользователь: <?php echo htmlspecialchars($order['user_name']); ?> (<?php echo htmlspecialchars($order['email']); ?>)</p>
      <p class="text-gray-600">Телефон: <?php echo htmlspecialchars($order['phone']); ?></p>
      <p class="text-gray-600">Адрес доставки: <?php echo htmlspecialchars($order['shipping_address']); ?></p>
      <p class="text-gray-600">Статус: <?php echo htmlspecialchars($statuses[$order['status']] ?? 'Неизвестно'); ?></p>
      <p class="text-gray-600">Дата создания: <?php echo htmlspecialchars($order['created_at']); ?></p>
      <p class="text-gray-600">Общая стоимость: <?php echo htmlspecialchars($order['total_price']); ?> руб.</p>
    </div>

    <!-- Товары в заказе -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h2 class="text-xl font-bold text-gray-800">Товары в заказе</h2>
      <div class="space-y-4 mt-4">
        <?php foreach ($order_items as $item): ?>
          <div class="flex items-center space-x-4">
            <div>
              <p class="text-gray-800"><?php echo htmlspecialchars($item['product_name']); ?></p>
              <p class="text-gray-600">Количество: <?php echo htmlspecialchars($item['quantity']); ?></p>
              <p class="text-gray-600">Цена: <?php echo htmlspecialchars($item['price']); ?> руб.</p>
              <?php if (!empty($item['attributes'])): ?>
                <p class="text-gray-600">
                  Характеристики: 
                  <?php 
                    $attributes = json_decode($item['attributes'], true);
                    echo htmlspecialchars(getAttributeNames($pdo, $attributes));
                  ?>
                </p>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<?php include_once('../../includes/footer.php'); ?>