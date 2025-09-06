<?php
session_start();
$pageTitle = "Редактирование товара";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: /admin/products");
    exit();
}

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
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $base_price = $_POST['base_price'];
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $type = $_POST['type'] ?? 'product';
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    
    // --- Добавлено: Получение данных о кратности и минимальном количестве ---
    $min_quantity = intval($_POST['min_quantity'] ?? 1);
    $multiplicity = intval($_POST['multiplicity'] ?? 1);
    $unit = trim($_POST['unit'] ?? 'шт.');
    // --- Конец добавленного кода ---

    if (!empty($name) && is_numeric($base_price) && $base_price >= 0) {
        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, base_price = ?, category_id = ?, type = ?, is_popular = ?, min_quantity = ?, multiplicity = ?, unit = ? WHERE id = ?");
        $result = $stmt->execute([$name, $description, $base_price, $category_id, $type, $is_popular, $min_quantity, $multiplicity, $unit, $product_id]);
        
        if ($result) {
            $_SESSION['notifications'][] = [
                'type' => 'success',
                'message' => 'Товар успешно обновлен.'
            ];
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Ошибка при обновлении товара.'
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
}

// Получение информации о товаре
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: /admin/products");
    exit();
}

// Получение статистики товара
$stmt_attrs = $pdo->prepare("SELECT COUNT(*) FROM product_attributes WHERE product_id = ?");
$stmt_attrs->execute([$product_id]);
$attributes_count = $stmt_attrs->fetchColumn();

$stmt_imgs = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
$stmt_imgs->execute([$product_id]);
$images_count = $stmt_imgs->fetchColumn();

$stmt_orders = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
$stmt_orders->execute([$product_id]);
$orders_count = $stmt_orders->fetchColumn();
?>

<?php include_once('../../includes/header.php');?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin/products" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">
          Все товары
        </a>
      </div>
    </div>

    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Редактирование товара</h1>
      <p class="text-xl text-gray-700"><?php echo htmlspecialchars($product['name']); ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Статистика товара -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
          </svg>
        </div>
        <div class="text-2xl font-bold text-[#118568] mb-2"><?php echo $attributes_count; ?></div>
        <div class="text-gray-600">Характеристик</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div class="text-2xl font-bold text-[#17B890] mb-2"><?php echo $images_count; ?></div>
        <div class="text-gray-600">Изображений</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
          </svg>
        </div>
        <div class="text-2xl font-bold text-[#5E807F] mb-2"><?php echo $orders_count; ?></div>
        <div class="text-gray-600">В заказах</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#9DC5BB] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-2xl font-bold text-[#9DC5BB] mb-2"><?php echo number_format($product['base_price'], 0, '', ' '); ?> ₽</div>
        <div class="text-gray-600">Базовая цена</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Основная форма -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Основная информация</h2>
          
          <form action="" method="POST" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div>
                <label for="name" class="block text-gray-700 font-medium mb-2">Название товара *</label>
                <input type="text" id="name" name="name" 
                       value="<?php echo htmlspecialchars($product['name']); ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                       placeholder="Введите название товара" required>
              </div>
              
              <div>
                <label for="base_price" class="block text-gray-700 font-medium mb-2">Базовая цена (руб.) *</label>
                <input type="number" step="0.01" id="base_price" name="base_price" 
                       value="<?php echo htmlspecialchars($product['base_price']); ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                       placeholder="0.00" min="0" required>
              </div>
              <div>
                <label for="min_quantity" class="block text-gray-700 font-medium mb-2">Минимальное количество *</label>
                <input type="number" id="min_quantity" name="min_quantity" 
                       value="<?php echo htmlspecialchars($product['min_quantity'] ?? 1); ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                       placeholder="1" min="1" required>
              </div>

              <div>
                <label for="multiplicity" class="block text-gray-700 font-medium mb-2">Кратность *</label>
                <input type="number" id="multiplicity" name="multiplicity" 
                       value="<?php echo htmlspecialchars($product['multiplicity'] ?? 1); ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                       placeholder="1" min="1" required>
              </div>
              <!-- --- Конец добавленного кода --- -->

              <div>
                <label for="unit" class="block text-gray-700 font-medium mb-2">Единица измерения</label>
                <input type="text" id="unit" name="unit" 
                       value="<?php echo htmlspecialchars($product['unit'] ?? 'шт.'); ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                       placeholder="шт.">
              </div>
              <div>
                <label for="category_id" class="block text-gray-700 font-medium mb-2">Категория</label>
                <select id="category_id" name="category_id" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
                  <option value="">Без категории</option>
                  <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div>
                <label for="type" class="block text-gray-700 font-medium mb-2">Тип</label>
                <select id="type" name="type" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
                  <option value="product" <?php echo $product['type'] === 'product' ? 'selected' : ''; ?>>Товар</option>
                  <option value="service" <?php echo $product['type'] === 'service' ? 'selected' : ''; ?>>Услуга</option>
                </select>
              </div>
              
              <div>
                <label for="discount" class="block text-gray-700 font-medium mb-2">Скидка (%)</label>
                <input type="number" step="0.01" id="discount" name="discount" 
                       value="<?php echo htmlspecialchars($product['discount']); ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                       placeholder="0.00" min="0" max="100">
              </div>
              
              <div class="flex items-end">
                <div class="flex items-center h-full">
                  <input type="checkbox" id="is_popular" name="is_popular" value="1" 
                         <?php echo $product['is_popular'] ? 'checked' : ''; ?>
                         class="rounded border-gray-300 text-[#118568] focus:ring-[#17B890] w-5 h-5">
                  <label for="is_popular" class="ml-2 text-gray-700 font-medium">Популярный товар</label>
                </div>
              </div>
            </div>
            
            <div>
              <label for="description" class="block text-gray-700 font-medium mb-2">Описание товара</label>
              <textarea id="description" name="description" rows="4" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                        placeholder="Введите описание товара"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 pt-4">
              <button type="submit" 
                      class="flex-1 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
                Сохранить изменения
              </button>
              
              <a href="/admin/products" 
                 class="flex-1 px-4 py-4 bg-gray-200 text-gray-700 text-center rounded-xl hover:bg-gray-300 transition-colors duration-300 font-bold text-lg">
                Отмена
              </a>
            </div>
          </form>
        </div>
      </div>

      <!-- Боковая панель -->
      <div class="space-y-8">
        <!-- Управление изображениями -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Изображения</h2>
          
          <div class="text-center mb-4">
            <div class="w-16 h-16 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <p class="text-gray-600 mb-2">Всего изображений: <?php echo $images_count; ?></p>
          </div>
          
          <a href="/admin/images?product_id=<?php echo $product['id']; ?>" 
             class="block w-full px-4 py-3 bg-[#17B890] text-white text-center rounded-lg hover:bg-[#14a380] transition-colors duration-300 font-medium">
            Управление изображениями
          </a>
        </div>

        <!-- Управление характеристиками -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Характеристики</h2>
          
          <div class="text-center mb-4">
            <div class="w-16 h-16 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
              </svg>
            </div>
            <p class="text-gray-600 mb-2">Всего характеристик: <?php echo $attributes_count; ?></p>
          </div>
          
          <a href="/admin/attributes?product_id=<?php echo $product['id']; ?>" 
             class="block w-full px-4 py-3 bg-[#118568] text-white text-center rounded-lg hover:bg-[#0f755a] transition-colors duration-300 font-medium">
            Управление характеристиками
          </a>
        </div>
        <!-- Управление расходниками -->
<div class="bg-white rounded-3xl shadow-2xl p-6">
  <h2 class="text-2xl font-bold text-gray-800 mb-6">Расходники</h2>
  
  <div class="text-center mb-4">
    <div class="w-16 h-16 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-3">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
      </svg>
    </div>
    <?php
    $stmt_exp_count = $pdo->prepare("SELECT COUNT(*) FROM product_expenses WHERE product_id = ?");
    $stmt_exp_count->execute([$product['id']]);
    $expenses_count = $stmt_exp_count->fetchColumn();
    ?>
    <p class="text-gray-600 mb-2">Всего расходников: <?php echo $expenses_count; ?></p>
  </div>
  
  <a href="/admin/product/manage_expenses.php?product_id=<?php echo $product['id']; ?>" 
     class="block w-full px-4 py-3 bg-[#5E807F] text-white text-center rounded-lg hover:bg-[#4a6665] transition-colors duration-300 font-medium">
    Управление расходниками
  </a>
</div>
        <!-- Информация о товаре -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Информация</h2>
          
          <div class="space-y-4">
            <div class="flex justify-between">
              <span class="text-gray-600">ID:</span>
              <span class="font-medium"><?php echo $product['id']; ?></span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-gray-600">Создан:</span>
              <span class="font-medium"><?php echo date('d.m.Y H:i', strtotime($product['created_at'])); ?></span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-gray-600">Тип:</span>
              <span class="font-medium capitalize"><?php echo $product['type']; ?></span>
            </div>
            
            <?php if ($product['discount'] > 0): ?>
              <div class="flex justify-between">
                <span class="text-gray-600">Скидка:</span>
                <span class="font-medium text-[#17B890]"><?php echo number_format($product['discount'], 2, '.', ''); ?>%</span>
              </div>
            <?php endif; ?>
            
            <div class="flex justify-between">
              <span class="text-gray-600">Популярный:</span>
              <span class="font-medium <?php echo $product['is_popular'] ? 'text-[#17B890]' : 'text-gray-500'; ?>">
                <?php echo $product['is_popular'] ? 'Да' : 'Нет'; ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include_once('../../includes/footer.php'); ?>