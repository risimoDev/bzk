<?php
session_start();
$pageTitle = "Каталог";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';



// Получение параметров из GET-запроса
$category_id = $_GET['category'] ?? null;
$type = $_GET['type'] ?? 'product'; // По умолчанию показываем товары
$sort = $_GET['sort'] ?? 'default'; // По умолчанию без сортировки
$cat_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $cat_stmt->fetchAll();
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

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
  <div class="container mx-auto px-4 max-w-7xl">
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

    <div class="text-center mb-12">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Наш каталог</h1>
      <p class="text-xl text-gray-700 max-w-3xl mx-auto">Откройте для себя широкий ассортимент товаров и услуг. Воспользуйтесь фильтрами для быстрого поиска нужного вам продукта.</p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Фильтры с улучшенным дизайном -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-12 transform transition-all duration-300 hover:shadow-2xl">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
        <!-- Тип -->
        <div class="w-full lg:w-auto">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Тип продукции</label>
          <div class="flex rounded-lg overflow-hidden border-2 border-[#118568]">
            <a href="/catalog?type=product" 
               class="px-6 py-3 font-medium transition-all duration-300 <?php echo $type === 'product' ? 'bg-[#118568] text-white' : 'bg-white text-gray-700 hover:bg-[#DEE5E5]'; ?>">
              Товары
            </a>
            <a href="/catalog?type=service" 
               class="px-6 py-3 font-medium transition-all duration-300 <?php echo $type === 'service' ? 'bg-[#118568] text-white' : 'bg-white text-gray-700 hover:bg-[#DEE5E5]'; ?>">
              Услуги
            </a>
          </div>
        </div>

        <!-- Категория -->
        <div class="w-full lg:w-64">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Категория</label>
          <select id="category" onchange="location.href=this.value" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
            <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>">Все категории</option>
            <?php if (is_array($categories)) {
              foreach ($categories as $category): ?>
              <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo $category['id']; ?>" 
                <?php echo ($category['id'] == $category_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($category['name']); ?>
              </option>
            <?php endforeach; } ?>
          </select>
        </div>

        <!-- Сортировка -->
        <div class="w-full lg:w-64">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Сортировка</label>
          <select id="sort" onchange="location.href=this.value" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
            <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo htmlspecialchars($category_id); ?>&sort=default">По умолчанию</option>
            <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo htmlspecialchars($category_id); ?>&sort=price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Цена (по возрастанию)</option>
            <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo htmlspecialchars($category_id); ?>&sort=price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Цена (по убыванию)</option>
            <option value="/catalog?type=<?php echo htmlspecialchars($type); ?>&category=<?php echo htmlspecialchars($category_id); ?>&sort=popularity" <?php echo $sort === 'popularity' ? 'selected' : ''; ?>>Популярность</option>
          </select>
        </div>

        <!-- Результаты -->
        <div class="w-full lg:w-auto flex items-end">
          <div class="text-sm text-gray-600">
            Найдено: <span class="font-bold text-[#118568]"><?php echo count($products); ?></span> <?php echo count($products) == 1 ? 'товар' : (count($products) < 5 ? 'товара' : 'товаров'); ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Список товаров/услуг -->
    <?php if (empty($products)): ?>
      <div class="bg-white rounded-2xl shadow-xl p-12 text-center">
        <div class="text-6xl mb-4">🔍</div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Ничего не найдено</h3>
        <p class="text-gray-600 mb-6">Попробуйте изменить параметры поиска или сбросить фильтры</p>
        <a href="/catalog" class="inline-block bg-[#118568] text-white px-6 py-3 rounded-lg hover:bg-[#0f755a] transition-all duration-300 transform hover:scale-105 font-medium">
          Сбросить фильтры
        </a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
        <?php foreach ($products as $product): ?>
        <a href="<?php echo $type === 'product' ? '/service?id='.$product['id'] : '/service?id='.$product['id'].'&type=service'; ?>" class="group">
          <div class="bg-white rounded-2xl shadow-lg overflow-hidden transform transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 h-full flex flex-col">
            <?php
            // Получаем главное изображение товара
            $main_image = getProductMainImage($pdo, $product['id']);
            $image_url = $main_image ?: '/assets/images/no-image.webp'; // Заглушка, если изображение отсутствует
            ?>
            
            <!-- Изображение -->
            <div class="relative overflow-hidden">
              <img src="<?php echo htmlspecialchars($image_url); ?>" 
                   alt="<?php echo htmlspecialchars($product['name']); ?>" 
                   class="w-full h-52 object-cover group-hover:scale-110 transition-transform duration-500">
              <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
              
              <!-- Тип продукта -->
              <div class="absolute top-4 right-4">
                <span class="px-3 py-1 bg-[#118568] text-white text-xs font-bold rounded-full">
                  <?php echo $type === 'product' ? 'ТОВАР' : 'УСЛУГА'; ?>
                </span>
              </div>
              
              <!-- Иконка просмотра -->
              <div class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">
                <div class="w-10 h-10 bg-[#17B890] rounded-full flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- Контент -->
            <div class="p-6 flex-grow flex flex-col">
              <h3 class="text-lg font-bold text-gray-800 mb-2 group-hover:text-[#118568] transition-colors duration-300 line-clamp-2">
                <?php echo htmlspecialchars($product['name']); ?>
              </h3>
              
              <p class="text-gray-600 text-sm mb-4 flex-grow line-clamp-3">
                <?php echo htmlspecialchars($product['description']); ?>
              </p>

              <!-- Цена -->
              <div class="mt-auto">
                <div class="flex items-center justify-between">
                  <div class="text-2xl font-bold text-[#118568]">
                    <?php echo number_format($product['base_price'], 0, '', ' '); ?> 
                    <span class="text-lg">руб.</span>
                  </div>
                  
                  <?php if ($type === 'product' && isset($product['in_stock']) && $product['in_stock'] > 0): ?>
                    <div class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                      В наличии
                    </div>
                  <?php elseif ($type === 'product'): ?>
                    <div class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full">
                      Под заказ
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Hover эффект -->
            <div class="px-6 pb-6 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              <div class="w-full bg-[#118568] text-white py-3 rounded-lg text-center font-medium hover:bg-[#0f755a] transition-colors duration-300">
                Подробнее
              </div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Пагинация (если есть) -->
    <?php if (isset($total_pages) && $total_pages > 1): ?>
      <div class="mt-12 flex justify-center">
        <div class="bg-white rounded-2xl shadow-lg px-6 py-4 flex items-center space-x-2">
          <?php if ($current_page > 1): ?>
            <a href="/catalog?page=<?php echo $current_page - 1; ?>&type=<?php echo $type; ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>" 
               class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5] transition-colors duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </a>
          <?php endif; ?>

          <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
            <a href="/catalog?page=<?php echo $i; ?>&type=<?php echo $type; ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>" 
               class="w-10 h-10 flex items-center justify-center rounded-lg <?php echo $i == $current_page ? 'bg-[#118568] text-white' : 'hover:bg-[#DEE5E5]'; ?> transition-colors duration-300">
              <?php echo $i; ?>
            </a>
          <?php endfor; ?>

          <?php if ($current_page < $total_pages): ?>
            <a href="/catalog?page=<?php echo $current_page + 1; ?>&type=<?php echo $type; ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>" 
               class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5] transition-colors duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>