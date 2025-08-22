<?php
session_start();
$pageTitle = "Корзина";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

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
                'index' => $index,
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

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
  <div class="container mx-auto px-4 max-w-6xl">
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
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Ваша корзина</h1>
      <p class="text-xl text-gray-700 max-w-2xl mx-auto">
        <?php echo empty($cart) ? 'Ваша корзина пуста' : 'Проверьте выбранные товары перед оформлением заказа'; ?>
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

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
                              <?php foreach ($item['attributes'] as $attribute): ?>
                                <div class="flex items-center text-sm text-gray-600 mb-1">
                                  <span class="font-medium"><?php echo htmlspecialchars($attribute['attribute_name']); ?>:</span>
                                  <span class="ml-2"><?php echo htmlspecialchars($attribute['value']); ?></span>
                                  <?php if ($attribute['price_modifier'] > 0): ?>
                                    <span class="ml-2 text-[#17B890]">+<?php echo number_format($attribute['price_modifier'], 0, '', ' '); ?> руб.</span>
                                  <?php endif; ?>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                        
                        <!-- Цена и количество -->
                        <div class="text-right">
                          <div class="text-2xl font-bold text-[#118568] mb-2">
                            <?php echo number_format($item['total_price'], 0, '', ' '); ?> <span class="text-lg">руб.</span>
                          </div>
                          <div class="text-gray-500 text-sm">
                            <?php echo number_format($item['base_price'] + $item['total_attributes_price'], 0, '', ' '); ?> руб. × <?php echo $item['quantity']; ?> шт.
                          </div>
                        </div>
                      </div>
                      
                      <!-- Контролы количества и удаления -->
                      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-4">
                        <div class="flex items-center">
                          <form action="/cart/update" method="POST" class="flex items-center">
                            <input type="hidden" name="index" value="<?php echo $item['index']; ?>">
                            
                            <button type="submit" name="action" value="decrease" 
                                    class="w-10 h-10 bg-[#DEE5E5] rounded-l-lg hover:bg-[#9DC5BB] transition-colors duration-300 flex items-center justify-center <?php echo $item['quantity'] <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                              </svg>
                            </button>
                            
                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                   min="1" 
                                   class="w-16 h-10 text-center border-y border-gray-200 focus:outline-none font-bold text-lg"
                                   onchange="this.form.submit()">
                            
                            <button type="submit" name="action" value="increase" 
                                    class="w-10 h-10 bg-[#DEE5E5] rounded-r-lg hover:bg-[#9DC5BB] transition-colors duration-300 flex items-center justify-center">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                              </svg>
                            </button>
                          </form>
                        </div>
                        
                        <form action="/cart/remove" method="POST">
                          <input type="hidden" name="index" value="<?php echo $item['index']; ?>">
                          <button type="submit" 
                                  class="flex items-center text-red-600 hover:text-red-800 transition-colors duration-300 group">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1 group-hover:scale-110 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1-1v3M4 7h16" />
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
                <span class="text-gray-600">Товары (<?php echo count($cart_items); ?>)</span>
                <span class="font-medium"><?php echo number_format($total_cart_price, 0, '', ' '); ?> руб.</span>
              </div>
              
              <div class="flex justify-between">
                <span class="text-gray-600">Доставка</span>
                <span class="font-medium">Бесплатно</span>
              </div>
              
              <div class="flex justify-between">
                <span class="text-gray-600">Налоги</span>
                <span class="font-medium">0 руб.</span>
              </div>
              
              <div class="border-t border-gray-200 pt-4">
                <div class="flex justify-between text-xl font-bold text-gray-800">
                  <span>Итого</span>
                  <span><?php echo number_format($total_cart_price, 0, '', ' '); ?> руб.</span>
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
  // Добавить в конец файла cart.php перед
document.addEventListener('DOMContentLoaded', function() {
    // Валидация количества товаров
    const quantityInputs = document.querySelectorAll('input[name="quantity"]');
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const maxQuantity = 1000; // Максимальное количество
            const minQuantity = 1;    // Минимальное количество
            
            if (this.value > maxQuantity) {
                this.value = maxQuantity;
                showNotification('Максимальное количество товара: ' + maxQuantity, 'warning');
            } else if (this.value < minQuantity) {
                this.value = minQuantity;
                showNotification('Минимальное количество товара: ' + minQuantity, 'warning');
            }
        });
    });
    
    // Плавное удаление товаров из корзины
    const removeForms = document.querySelectorAll('form[action="/cart/remove"]');
    
    removeForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const itemElement = this.closest('.hover\\:bg-gray-50');
            if (itemElement) {
                itemElement.style.opacity = '0.5';
                itemElement.style.transition = 'opacity 0.3s ease';
            }
            
            // AJAX запрос для удаления
            fetch('/cart/remove', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (itemElement) {
                        itemElement.style.transition = 'all 0.3s ease';
                        itemElement.style.height = '0';
                        itemElement.style.opacity = '0';
                        itemElement.style.margin = '0';
                        itemElement.style.padding = '0';
                        
                        setTimeout(() => {
                            itemElement.remove();
                            updateCartSummary();
                            showNotification('Товар удален из корзины', 'success');
                        }, 300);
                    }
                } else {
                    if (itemElement) {
                        itemElement.style.opacity = '1';
                    }
                    showNotification('Ошибка при удалении товара', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (itemElement) {
                    itemElement.style.opacity = '1';
                }
                showNotification('Произошла ошибка', 'error');
            });
        });
    });
    
    function updateCartSummary() {
        // Обновляем итоговую сумму и количество товаров
        fetch('/api/cart-count')
            .then(response => response.json())
            .then(data => {
                if (data.total_items === 0) {
                    location.reload(); // Перезагружаем если корзина пуста
                }
            });
    }
    
    function showNotification(message, type = 'info') {
        // Создаем уведомление
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 
            type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
        }`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Автоматическое скрытие через 3 секунды
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    }
});
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>