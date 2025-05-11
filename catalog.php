<?php
session_start();
$pageTitle = "Каталог | Типография";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Получение параметров из GET-запроса
$category_id = $_GET['category'] ?? null;
$type = $_GET['type'] ?? 'product'; // По умолчанию показываем товары
$sort = $_GET['sort'] ?? 'default'; // По умолчанию без сортировки

// Формирование SQL-запроса
$query = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.type = :type";
$params = [':type' => $type];

if ($category_id) {
    $query .= " AND p.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if ($sort === 'price_asc') {
    $query .= " ORDER BY p.base_price ASC";
} elseif ($sort === 'price_desc') {
    $query .= " ORDER BY p.base_price DESC";
} elseif ($sort === 'popularity') {
    $query .= " ORDER BY p.is_popular DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Функция для получения главного изображения товара
function getProductMainImage($pdo, $product_id) {
    $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_main = 1 LIMIT 1");
    $stmt->execute([$product_id]);
    return $stmt->fetchColumn();
}
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Каталог</h1>

  <!-- Фильтры -->
  <div class="flex flex-col md:flex-row justify-between items-center mb-6">
    <div class="mb-4 md:mb-0">
      <label for="type" class="mr-2">Тип:</label>
      <select id="type" onchange="location.href=this.value" class="px-4 py-2 border rounded-lg">
        <option value="/catalog?type=product" <?php echo $type === 'product' ? 'selected' : ''; ?>>Товары</option>
        <option value="/catalog?type=service" <?php echo $type === 'service' ? 'selected' : ''; ?>>Услуги</option>
      </select>
    </div>

    <div class="mb-4 md:mb-0">
      <label for="category" class="mr-2">Категория:</label>
      <select id="category" onchange="location.href=this.value" class="px-4 py-2 border rounded-lg">
        <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>">Все категории</option>
        <?php foreach ($categories as $category): ?>
          <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo $category['id']; ?>" 
            <?php echo ($category['id'] == $category_id) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($category['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="sort" class="mr-2">Сортировка:</label>
      <select id="sort" onchange="location.href=this.value" class="px-4 py-2 border rounded-lg">
        <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo htmlspecialchars($category_id); ?>&sort=default">По умолчанию</option>
        <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo htmlspecialchars($category_id); ?>&sort=price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Цена (по возрастанию)</option>
        <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo htmlspecialchars($category_id); ?>&sort=price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Цена (по убыванию)</option>
        <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo htmlspecialchars($category_id); ?>&sort=popularity" <?php echo $sort === 'popularity' ? 'selected' : ''; ?>>Популярность</option>
      </select>
    </div>
  </div>

  <!-- Список товаров/услуг -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($products as $product): ?>
    <a href="/service?id=<?php echo $product['id']; ?>">
      <div class="bg-white p-6 rounded-lg shadow-md">
        <?php
        // Получаем главное изображение товара
        $main_image = getProductMainImage($pdo, $product['id']);
        $image_url = $main_image ?: '/assets/images/no-image.webp'; // Заглушка, если изображение отсутствует
        ?>
        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-60 object-cover rounded-t-lg">
        <h3 class="text-xl font-bold text-gray-800 mt-4"><?php echo htmlspecialchars($product['name']); ?></h3>
        <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($product['description']); ?></p>
        <p class="text-lg font-semibold text-green-600 mt-4"><?php echo htmlspecialchars($product['base_price']); ?> руб.</p>
      </div>
      </a>
    <?php endforeach; ?>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>