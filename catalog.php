<?php
session_start();
$pageTitle = "Каталог | Типография";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Получение списка товаров
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.base_price, 
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) AS image_url
    FROM products p
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Каталог</h1>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($products as $product): ?>
      <div class="bg-white p-4 rounded-lg shadow-md">
      <a href="/service?id=<?php echo $product['id']; ?>" class="block">
        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-48 object-cover rounded-t-lg">
      </a>
        <h2 class="text-xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($product['name']); ?></h2>
        <p class="text-gray-600 mt-2">Цена от <?php echo htmlspecialchars($product['base_price']); ?> руб.</p>
        <button class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300 mt-4">
          Добавить в корзину
        </button>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>