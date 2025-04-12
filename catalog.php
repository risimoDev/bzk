<?php
session_start();
$pageTitle = "Каталог | Типография";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Получение данных о товарах
$stmt = $pdo->query("SELECT * FROM calculator_products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Каталог товаров</h1>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($products as $product): ?>
    <a href="/product?id=<?php echo $product['id']; ?>" class="block bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
      <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-48 object-cover">
      <div class="p-4">
        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
        <p class="text-gray-700">Базовая цена: <?php echo htmlspecialchars($product['base_price']); ?> ₽</p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>