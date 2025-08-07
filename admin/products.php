<?php
session_start();
$pageTitle = "Управление товарами";
include_once('../includes/header.php');

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Получение категорий
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action'])) {
      $action = $_POST['action'];

      if ($action === 'add_product') {
          $name = trim($_POST['name']);
          $description = trim($_POST['description']);
          $base_price = $_POST['base_price'];
          $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
          $type = $_POST['type'] ?? 'product';
          $is_popular = isset($_POST['is_popular']) ? 1 : 0;

          if (!empty($name) && is_numeric($base_price) && $base_price >= 0) {
              $stmt = $pdo->prepare("INSERT INTO products (name, description, base_price, category_id, type, is_popular) VALUES (?, ?, ?, ?, ?, ?)");
              $result = $stmt->execute([$name, $description, $base_price, $category_id, $type, $is_popular]);
              
              if ($result) {
                  $_SESSION['notifications'][] = [
                      'type' => 'success',
                      'message' => 'Товар успешно добавлен.'
                  ];
              } else {
                  $_SESSION['notifications'][] = [
                      'type' => 'error',
                      'message' => 'Ошибка при добавлении товара.'
                  ];
              }
          } else {
              $_SESSION['notifications'][] = [
                  'type' => 'error',
                  'message' => 'Пожалуйста, заполните все обязательные поля корректно.'
              ];
          }
          
          header("Location: " . $_SERVER['REQUEST_URI']);
          exit();
          
      } elseif ($action === 'delete_product') {
          $product_id = $_POST['product_id'];
          
          // Проверяем, есть ли заказы с этим товаром
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
          $stmt->execute([$product_id]);
          $order_count = $stmt->fetchColumn();
          
          if ($order_count > 0) {
              $_SESSION['notifications'][] = [
                  'type' => 'error',
                  'message' => 'Невозможно удалить товар, так как он присутствует в заказах.'
              ];
          } else {
              $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
              $result = $stmt->execute([$product_id]);
              
              if ($result) {
                  // Также удаляем связанные данные
                  $stmt = $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?");
                  $stmt->execute([$product_id]);
                  
                  $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
                  $stmt->execute([$product_id]);
                  
                  $_SESSION['notifications'][] = [
                      'type' => 'success',
                      'message' => 'Товар успешно удален.'
                  ];
              } else {
                  $_SESSION['notifications'][] = [
                      'type' => 'error',
                      'message' => 'Ошибка при удалении товара.'
                  ];
              }
          }
          
          header("Location: " . $_SERVER['REQUEST_URI']);
          exit();
      }
  }
}

// Получение списка товаров с категориями
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$popular_products = $pdo->query("SELECT COUNT(*) FROM products WHERE is_popular = 1")->fetchColumn();
$products_with_images = $pdo->query("
    SELECT COUNT(DISTINCT p.id) 
    FROM products p 
    JOIN product_images pi ON p.id = pi.product_id
")->fetchColumn();
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <?php echo backButton(); ?>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление товарами</h1>
      <p class="text-xl text-gray-700">Всего товаров: <?php echo $total_products; ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo $total_products; ?></div>
        <div class="text-gray-600">Всего товаров</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#17B890] mb-2"><?php echo $popular_products; ?></div>
        <div class="text-gray-600">Популярных</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#5E807F] mb-2"><?php echo $products_with_images; ?></div>
        <div class="text-gray-600">С изображениями</div>
      </div>
    </div>

    <!-- Форма добавления товара -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Добавить новый товар</h2>
      
      <form action="" method="POST" class="space-y-6">
        <input type="hidden" name="action" value="add_product">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <label for="name" class="block text-gray-700 font-medium mb-2">Название товара *</label>
            <input type="text" id="name" name="name" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="Введите название товара" required>
          </div>
          
          <div>
            <label for="base_price" class="block text-gray-700 font-medium mb-2">Базовая цена (руб.) *</label>
            <input type="number" step="0.01" id="base_price" name="base_price" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="0.00" min="0" required>
          </div>
          
          <div>
            <label for="category_id" class="block text-gray-700 font-medium mb-2">Категория</label>
            <select id="category_id" name="category_id" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
              <option value="">Без категории</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['id']; ?>">
                  <?php echo htmlspecialchars($category['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div>
            <label for="type" class="block text-gray-700 font-medium mb-2">Тип</label>
            <select id="type" name="type" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
              <option value="product">Товар</option>
              <option value="service">Услуга</option>
            </select>
          </div>
        </div>
        
        <div>
          <label for="description" class="block text-gray-700 font-medium mb-2">Описание товара</label>
          <textarea id="description" name="description" rows="3" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    placeholder="Введите описание товара"></textarea>
        </div>
        
        <div class="flex items-center">
          <input type="checkbox" id="is_popular" name="is_popular" value="1" 
                 class="rounded border-gray-300 text-[#118568] focus:ring-[#17B890] w-5 h-5">
          <label for="is_popular" class="ml-2 text-gray-700 font-medium">Популярный товар</label>
        </div>
        
        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Добавить товар
        </button>
      </form>
    </div>

    <!-- Список товаров -->
    <?php if (empty($products)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Товары не найдены</h2>
        <p class="text-gray-600 mb-8">Добавьте первый товар, используя форму выше</p>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-3xl shadow-2xl p-6">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
          <h2 class="text-2xl font-bold text-gray-800">Список товаров</h2>
          <div class="text-gray-600">
            Найдено: <?php echo count($products); ?> <?php echo count($products) == 1 ? 'товар' : (count($products) < 5 ? 'товара' : 'товаров'); ?>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($products as $product): ?>
            <div class="bg-gray-50 rounded-2xl p-6 hover:bg-white hover:shadow-xl transition-all duration-300 group">
              <div class="flex justify-between items-start mb-4">
                <div>
                  <h3 class="text-xl font-bold text-gray-800 group-hover:text-[#118568] transition-colors duration-300">
                    <?php echo htmlspecialchars($product['name']); ?>
                  </h3>
                  <?php if ($product['category_name']): ?>
                    <span class="inline-block px-2 py-1 bg-[#DEE5E5] text-gray-700 text-xs rounded-full mt-2">
                      <?php echo htmlspecialchars($product['category_name']); ?>
                    </span>
                  <?php endif; ?>
                </div>
                <?php if ($product['is_popular']): ?>
                  <span class="px-2 py-1 bg-[#17B890] text-white text-xs rounded-full">
                    Популярный
                  </span>
                <?php endif; ?>
              </div>
              
              <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                <?php echo htmlspecialchars($product['description'] ?: 'Без описания'); ?>
              </p>
              
              <div class="flex justify-between items-center mb-4">
                <div class="text-2xl font-bold text-[#118568]">
                  <?php echo number_format($product['base_price'], 0, '', ' '); ?> ₽
                </div>
                <span class="px-2 py-1 bg-gray-200 text-gray-700 text-xs rounded-full capitalize">
                  <?php echo $product['type'] === 'service' ? 'Услуга' : 'Товар'; ?>
                </span>
              </div>
              
              <!-- Получение изображения товара -->
              <?php
              $stmt_img = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_main = 1 LIMIT 1");
              $stmt_img->execute([$product['id']]);
              $main_image = $stmt_img->fetchColumn();
              $image_url = $main_image ?: '/assets/images/no-image.webp';
              ?>
              
              <div class="mb-4">
                <img src="<?php echo htmlspecialchars($image_url); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                     class="w-full h-32 object-cover rounded-lg">
              </div>
              
              <div class="flex flex-wrap gap-2 mb-4">
                <?php
                $stmt_attrs = $pdo->prepare("SELECT COUNT(*) FROM product_attributes WHERE product_id = ?");
                $stmt_attrs->execute([$product['id']]);
                $attributes_count = $stmt_attrs->fetchColumn();
                ?>
                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                  Характеристики: <?php echo $attributes_count; ?>
                </span>
                
                <?php
                $stmt_imgs = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
                $stmt_imgs->execute([$product['id']]);
                $images_count = $stmt_imgs->fetchColumn();
                ?>
                <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                  Изображения: <?php echo $images_count; ?>
                </span>
              </div>
              
              <div class="flex flex-col sm:flex-row gap-2">
                <a href="/admin/product/edit?id=<?php echo $product['id']; ?>" 
                   class="flex-1 px-4 py-2 bg-[#118568] text-white text-center rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm font-medium">
                  Редактировать
                </a>
                
                <div class="flex gap-1">
                  <a href="/admin/attributes?product_id=<?php echo $product['id']; ?>" 
                     class="px-3 py-2 bg-[#17B890] text-white rounded-lg hover:bg-[#14a380] transition-colors duration-300"
                     title="Характеристики">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                    </svg>
                  </a>
                  
                  <a href="/admin/images?product_id=<?php echo $product['id']; ?>" 
                     class="px-3 py-2 bg-[#5E807F] text-white rounded-lg hover:bg-[#4a6665] transition-colors duration-300"
                     title="Изображения">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </a>
                  
                  <form action="" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить этот товар?\n\nВнимание: Если товар присутствует в заказах, его удаление невозможно.')" class="m-0">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <button type="submit" 
                            class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300"
                            title="Удалить">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>