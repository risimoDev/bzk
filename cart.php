<?php
session_start();
$pageTitle = "Корзина";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// --- Добавлено: Обработка действий с корзиной ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['index'])) {
        $action = $_POST['action'];
        $index = intval($_POST['index']);
        
        if (isset($_SESSION['cart'][$index])) {
            if ($action === 'increase') {
                $_SESSION['cart'][$index]['quantity']++;
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Количество товара увеличено.'];
            } elseif ($action === 'decrease' && $_SESSION['cart'][$index]['quantity'] > 1) {
                $_SESSION['cart'][$index]['quantity']--;
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Количество товара уменьшено.'];
            } elseif ($action === 'remove') {
                unset($_SESSION['cart'][$index]);
                // Переиндексируем массив, чтобы избежать "дыр" в ключах
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Товар удален из корзины.'];
            } elseif ($action === 'update_quantity') {
                // --- Добавлено: Обработка изменения количества через ручной ввод ---
                $new_quantity = max(1, intval($_POST['quantity'] ?? 1));
                $_SESSION['cart'][$index]['quantity'] = $new_quantity;
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Количество товара обновлено.'];
                // --- Конец добавленного кода ---
            }
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Товар не найден в корзине.'];
        }
        
        // Перенаправляем, чтобы избежать повторной отправки при F5
        header("Location: /cart");
        exit();
    }
}
// --- Конец добавленного кода ---

$cart = $_SESSION['cart'] ?? [];
$cart_items = [];

if (!empty($cart)) {
    foreach ($cart as $index => $item) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $selected_attributes = [];
            $total_attributes_price = 0;
            
            foreach ($item['attributes'] as $attribute_id => $value_id) {
                $stmt = $pdo->prepare("
                    SELECT av.value, av.price_modifier, pa.name as attribute_name
                    FROM attribute_values av 
                    JOIN product_attributes pa ON av.attribute_id = pa.id 
                    WHERE av.id = ?
                ");
                $stmt->execute([$value_id]);
                $attribute_data = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($attribute_data) {
                    $selected_attributes[] = $attribute_data;
                    $total_attributes_price += $attribute_data['price_modifier'];
                }
            }

            $base_price = $product['base_price'];
            $item_total_price = ($base_price + $total_attributes_price) * $item['quantity'];

            // Получаем главное изображение товара
            $main_image = null;
            $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_main = 1 LIMIT 1");
            $stmt->execute([$product['id']]);
            $image_result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($image_result) {
                $main_image = $image_result['image_url'];
            }

            $cart_items[] = [
                'index' => $index, // --- Обновлено: Сохраняем индекс для действий ---
                'product' => $product,
                'quantity' => $item['quantity'],
                'attributes' => $selected_attributes,
                'base_price' => $base_price,
                'total_attributes_price' => $total_attributes_price,
                'total_price' => $item_total_price,
                'main_image' => $main_image
            ];
        }
    }
}

$total_cart_price = array_sum(array_column($cart_items, 'total_price'));
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-6xl">
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
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Ваша корзина</h1>
      <p class="text-xl text-gray-700 max-w-2xl mx-auto">
        <?php echo empty($cart) ? 'Ваша корзина пуста' : 'Проверьте выбранные товары перед оформлением заказа'; ?>
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php if (isset($_SESSION['notifications'])): ?>
      <?php foreach ($_SESSION['notifications'] as $notification): ?>
        <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
          <?php echo htmlspecialchars($notification['message']); ?>
        </div>
      <?php endforeach; ?>
      <?php unset($_SESSION['notifications']); ?>
    <?php endif; ?>

    <?php if (empty($cart)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="text-8xl mb-6">🛒</div>
        <h2 class="text-3xl font-bold text-gray-800 mb-4">Корзина пуста</h2>
        <p class="text-gray-600 mb-8 text-lg">Добавьте товары в корзину, чтобы продолжить покупки</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/catalog" class="px-8 py-4 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Перейти в каталог
          </a>
          <a href="/" class="px-8 py-4 bg-[#DEE5E5] text-[#118568] rounded-xl hover:bg-[#9DC5BB] transition-all duration-300 font-bold text-lg">
            На главную
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Список товаров -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <div class="p-6 border-b border-gray-200">
              <h2 class="text-2xl font-bold text-gray-800">Товары в корзине</h2>
              <p class="text-gray-600"><?php echo count($cart_items); ?> <?php echo count($cart_items) == 1 ? 'товар' : (count($cart_items) < 5 ? 'товара' : 'товаров'); ?></p>
            </div>
            
            <div class="divide-y divide-gray-100">
              <?php foreach ($cart_items as $item): ?>
                <div class="p-6 hover:bg-gray-50 transition-colors duration-300">
                  <div class="flex flex-col md:flex-row gap-6">
                    <!-- Изображение товара -->
                    <div class="flex-shrink-0">
                      <?php $image_url = $item['main_image'] ?: '/assets/images/no-image.webp'; ?>
                      <img src="<?php echo htmlspecialchars($image_url); ?>" 
                           alt="<?php echo htmlspecialchars($item['product']['name']); ?>" 
                           class="w-24 h-24 object-cover rounded-xl">
                    </div>
                    
                    <!-- Информация о товаре -->
                    <div class="flex-grow">
                      <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                        <div>
                          <h3 class="text-xl font-bold text-gray-800 mb-2">
                            <?php echo htmlspecialchars($item['product']['name']); ?>
                          </h3>
                          
                          <!-- Характеристики -->
                          <?php if (!empty($item['attributes'])): ?>
                            <div class="mb-3">
                              <div class="text-sm text-gray-600 mb-1">Характеристики:</div>
                              <div class="flex flex-wrap gap-2">
                                <?php foreach ($item['attributes'] as $attribute): ?>
                                  <span class="px-2 py-1 bg-[#DEE5E5] text-gray-700 text-xs rounded-full">
                                    <?php echo htmlspecialchars($attribute['attribute_name']); ?>: 
                                    <?php echo htmlspecialchars($attribute['value']); ?>
                                    <?php if ($attribute['price_modifier'] > 0): ?>
                                      <span class="text-[#17B890]">(+<?php echo number_format($attribute['price_modifier'], 0, '', ' '); ?> руб.)</span>
                                    <?php endif; ?>
                                  </span>
                                <?php endforeach; ?>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>
                        
                        <!-- Цена и количество -->
                        <div class="text-right">
                          <div class="text-2xl font-bold text-[#118568]">
                            <?php echo number_format($item['total_price'], 0, '', ' '); ?> <span class="text-lg">руб.</span>
                          </div>
                          <div class="text-gray-600 text-sm mt-1">
                            <?php echo number_format($item['base_price'] + $item['total_attributes_price'], 0, '', ' '); ?> руб. × <?php echo $item['quantity']; ?> шт.
                          </div>
                        </div>
                      </div>
                      <!-- Контролы количества и удаления -->
                      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-4">
                        <div class="flex items-center">
                          <!-- --- Исправлено: Форма изменения количества с ручным вводом --- -->
                          <form action="" method="POST" class="flex items-center">
                            <input type="hidden" name="index" value="<?php echo $item['index']; ?>">

                            <button type="submit" name="action" value="decrease" 
                                    class="w-10 h-10 bg-[#DEE5E5] rounded-l-lg hover:bg-[#9DC5BB] transition-colors duration-300 flex items-center justify-center <?php echo $item['quantity'] <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                              </svg>
                            </button>

                            <!-- --- Добавлено: Поле ввода количества с обработкой onchange --- -->
                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                   min="1" 
                                   class="w-16 h-10 text-center border-y border-gray-200 focus:outline-none font-bold text-lg"
                                   onchange="updateCartItemQuantity(this)"
                                   data-index="<?php echo $item['index']; ?>">
                            <!-- --- Конец добавленного кода --- -->

                            <button type="submit" name="action" value="increase" 
                                    class="w-10 h-10 bg-[#DEE5E5] rounded-r-lg hover:bg-[#9DC5BB] transition-colors duration-300 flex items-center justify-center">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                              </svg>
                            </button>
                          </form>
                          <!-- --- Конец исправленного кода --- -->
                        </div>

                        <form action="" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить этот товар из корзины?')" class="m-0">
                          <input type="hidden" name="index" value="<?php echo $item['index']; ?>">
                          <button type="submit" name="action" value="remove" 
                                  class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Удалить
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Итог заказа -->
        <div class="lg:col-span-1">
          <div class="bg-white rounded-3xl shadow-2xl p-6 sticky top-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Итог заказа</h2>
            
            <div class="space-y-4 mb-6">
              <div class="flex justify-between">
                <span class="text-gray-600">Товары (<?php echo array_sum(array_column($cart_items, 'quantity')); ?> шт.):</span>
                <span class="font-medium"><?php echo number_format($total_cart_price, 0, '', ' '); ?> руб.</span>
              </div>
              
              <!--<div class="flex justify-between">
                <span class="text-gray-600">Доставка:</span>
                <span class="font-medium">Бесплатно</span>
              </div>
              
              <!--<div class="flex justify-between">
                <span class="text-gray-600">Налоги:</span>
                <span class="font-medium">0 руб.</span>
              </div> -->
              
              <div class="border-t border-gray-200 pt-4">
                <div class="flex justify-between text-xl font-bold">
                  <span>Итого к оплате:</span>
                  <span class="text-[#118568]"><?php echo number_format($total_cart_price, 0, '', ' '); ?> руб.</span>
                </div>
              </div>
            </div>
            
            <div class="space-y-4">
              <a href="/checkoutcart" class="block w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg text-center shadow-lg hover:shadow-xl">
                Оформить заказ
              </a>
              
              <a href="/catalog" class="block w-full bg-[#DEE5E5] text-[#118568] py-3 rounded-xl hover:bg-[#9DC5BB] transition-all duration-300 font-medium text-center">
                Продолжить покупки
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>
<script>
function updateCartItemQuantity(inputElement) {
    const index = inputElement.getAttribute('data-index');
    const newQuantity = parseInt(inputElement.value) || 1;
    
    // Ограничиваем минимальное и максимальное значение
    if (newQuantity < 1) {
        inputElement.value = 1;
        return;
    }
    if (newQuantity > 1000) {
        inputElement.value = 1000;
        return;
    }
    
    // Создаем форму для отправки
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    // Добавляем скрытые поля
    const indexInput = document.createElement('input');
    indexInput.type = 'hidden';
    indexInput.name = 'index';
    indexInput.value = index;
    form.appendChild(indexInput);
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'update_quantity';
    form.appendChild(actionInput);
    
    const quantityInput = document.createElement('input');
    quantityInput.type = 'hidden';
    quantityInput.name = 'quantity';
    quantityInput.value = newQuantity;
    form.appendChild(quantityInput);
    
    // Добавляем форму в документ и отправляем
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php include_once __DIR__ . '/includes/footer.php'; ?>