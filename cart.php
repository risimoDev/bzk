<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/security.php';
$pageTitle = "Корзина";


// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// --- Добавлено: Обработка действий с корзиной ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    verify_csrf();
    
    if (isset($_POST['action']) && isset($_POST['index'])) {
        $action = $_POST['action'];
        $index = intval($_POST['index']);
        
        if (isset($_SESSION['cart'][$index])) {
                $product_id = $_SESSION['cart'][$index]['product_id'];
                $stmt_step = $pdo->prepare("SELECT multiplicity FROM products WHERE id = ?");
                $stmt_step->execute([$product_id]);
                $step = (int)$stmt_step->fetchColumn() ?: 1;

                if ($action === 'increase') {
                    $_SESSION['cart'][$index]['quantity'] += $step;
                    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Количество товара увеличено.'];
                } elseif ($action === 'decrease' && $_SESSION['cart'][$index]['quantity'] > $step) {
                    $_SESSION['cart'][$index]['quantity'] = max($step, $_SESSION['cart'][$index]['quantity'] - $step);
                    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Количество товара уменьшено.'];
                } elseif ($action === 'remove') {
                    unset($_SESSION['cart'][$index]);
                    $_SESSION['cart'] = array_values($_SESSION['cart']);
                    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Товар удален из корзины.'];
                } elseif ($action === 'update_quantity') {
                    $new_quantity = max($step, intval($_POST['quantity'] ?? $step));
                    // округляем вниз до ближайшего кратного
                    $new_quantity = floor($new_quantity / $step) * $step;
                    if ($new_quantity < $step) $new_quantity = $step;
                    $_SESSION['cart'][$index]['quantity'] = $new_quantity;
                    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Количество товара обновлено.'];
                }
            }
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Товар не найден в корзине.'];
        }
        
        // Перенаправляем, чтобы избежать повторной отправки при F5
        header("Location: /cart");
        exit();
}
// --- Конец добавленного кода ---

function getUnitPrice($pdo, $product_id, $quantity) {
    // Получаем все диапазоны для товара
    $stmt = $pdo->prepare("SELECT * FROM product_quantity_prices WHERE product_id = ? ORDER BY min_qty ASC");
    $stmt->execute([$product_id]);
    $ranges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unitPrice = null;

    foreach ($ranges as $r) {
        $min = (int)$r['min_qty'];
        $max = $r['max_qty'] ? (int)$r['max_qty'] : PHP_INT_MAX;

        if ($quantity >= $min && $quantity <= $max) {
            $unitPrice = (float)$r['price'];
        }
    }

    // Если диапазон не найден → используем базовую цену
    if ($unitPrice === null) {
        $stmt = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $unitPrice = (float)$stmt->fetchColumn();
    }

    return $unitPrice;
}



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

            $unit_price = getUnitPrice($pdo, $product['id'], $item['quantity'], $product['base_price']);
            $item_total_price = ($unit_price + $total_attributes_price) * $item['quantity'];

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
                'main_image' => $main_image,
                'step' => (int)($product['quantity_step'] ?? 1) // --- Кратность ---
            ];
        }
    }
}

$total_cart_price = array_sum(array_column($cart_items, 'total_price'));
?>
<?php include_once __DIR__ . '/includes/header.php';?>
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
          <?php echo e($notification['message']); ?>
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
                <div class="p-6 hover:bg-gray-50 transition-colors duration-300 cart-item">
                  <div class="flex flex-col md:flex-row gap-6">
                    <!-- Изображение товара -->
                    <div class="flex-shrink-0">
                      <?php $image_url = $item['main_image'] ?: '/assets/images/no-image.webp'; ?>
                      <img src="<?php echo e($image_url); ?>" 
                           alt="<?php echo e($item['product']['name']); ?>" 
                           class="w-24 h-24 object-cover rounded-xl">
                    </div>
                    
                    <!-- Информация о товаре -->
                    <div class="flex-grow">
                      <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                        <div>
                          <h3 class="text-xl font-bold text-gray-800 mb-2">
                            <?php echo e($item['product']['name']); ?>
                          </h3>
                          
                          <!-- Характеристики с возможностью редактирования -->
                          <?php if (!empty($item['attributes'])): ?>
                            <div class="mb-3">
                              <div class="flex items-center justify-between mb-2">
                                <div class="text-sm text-gray-600">Характеристики:</div>
                                <button type="button" onclick="toggleAttributeEdit(<?php echo $item['index']; ?>)" 
                                        class="text-xs text-[#118568] hover:text-[#0f755a] font-medium flex items-center">
                                  <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                  </svg>
                                  Изменить
                                </button>
                              </div>
                              <div class="flex flex-wrap gap-2" id="attributes-display-<?php echo $item['index']; ?>">
                                <?php foreach ($item['attributes'] as $attribute): ?>
                                  <span class="px-2 py-1 bg-[#DEE5E5] text-gray-700 text-xs rounded-full">
                                    <?php echo e($attribute['attribute_name']); ?>: 
                                    <?php echo e($attribute['value']); ?>
                                    <?php if ($attribute['price_modifier'] > 0): ?>
                                      <span class="text-[#17B890]">(+<?php echo number_format($attribute['price_modifier'], 0, '', ' '); ?> руб.)</span>
                                    <?php endif; ?>
                                  </span>
                                <?php endforeach; ?>
                              </div>
                              
                              <!-- Форма редактирования характеристик (скрытая по умолчанию) -->
                              <div class="hidden mt-3 p-3 bg-gray-50 rounded-lg" id="attributes-edit-<?php echo $item['index']; ?>">
                                <form action="/cart0/update_attributes.php" method="POST" class="space-y-2">
                                  <?php echo csrf_field(); ?>
                                  <input type="hidden" name="cart_index" value="<?php echo $item['index']; ?>">
                                  <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>">
                                  
                                  <!-- Здесь будут динамически загружены варианты атрибутов -->
                                  <div class="text-xs text-gray-500 mb-2">Перейдите на страницу товара для изменения характеристик</div>
                                  <div class="flex gap-2">
                                    <a href="/service?id=<?php echo $item['product']['id']; ?>" 
                                       class="px-3 py-1 bg-[#118568] text-white text-xs rounded hover:bg-[#0f755a] transition-colors">
                                      Настроить
                                    </a>
                                    <button type="button" onclick="toggleAttributeEdit(<?php echo $item['index']; ?>)" 
                                            class="px-3 py-1 bg-gray-300 text-gray-700 text-xs rounded hover:bg-gray-400 transition-colors">
                                      Отмена
                                    </button>
                                  </div>
                                </form>
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
                            <?php 
$unitPrice = getUnitPrice($pdo, $product['id'], $item['quantity']); 
?>
<?= number_format($unitPrice, 0, '', ' ') ?> руб. × <?= $item['quantity'] ?> шт.
                          </div>
                        </div>
                      </div>
                      <!-- Контролы количества и удаления -->
                      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-4 item-actions">
                        <div class="flex items-center quantity-controls">
                          <!-- --- Исправлено: Форма изменения количества с ручным вводом --- -->
                          <form action="" method="POST" class="flex items-center">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="index" value="<?php echo e($item['index']); ?>">

                            <button type="submit" name="action" value="decrease" 
                                    class="w-10 h-10 bg-[#DEE5E5] rounded-l-lg hover:bg-[#9DC5BB] transition-colors duration-300 flex items-center justify-center <?php echo $item['quantity'] <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                              </svg>
                            </button>

                            <!-- --- Добавлено: Поле ввода количества с обработкой onchange --- -->
                                                        <input type="number" name="quantity"
                                  value="<?php echo $item['quantity']; ?>"
                                  min="<?php echo $item['step']; ?>"
                                  step="<?php echo $item['step']; ?>"
                                  class="w-16 h-10 text-center border-y border-gray-200 focus:outline-none font-bold text-lg"
                                  onchange="updateCartItemQuantity(this)"
                                  data-index="<?php echo $item['index']; ?>"
                                  data-step="<?php echo $item['step']; ?>">
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
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="index" value="<?php echo e($item['index']); ?>">
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
            
            <!-- Переключатель срочного заказа -->
            <div class="mb-6 p-4 bg-gradient-to-r from-orange-50 to-red-50 rounded-xl border border-orange-200">
              <label class="flex items-center cursor-pointer">
                <input type="checkbox" id="urgent-order" class="mr-3 w-4 h-4 text-orange-600 rounded focus:ring-orange-500" onchange="toggleUrgentOrder()">
                <div>
                  <span class="font-bold text-orange-800">Срочный заказ</span>
                  <div class="text-xs text-orange-600">Выполнение за 1-2 дня (+50% к стоимости)</div>
                </div>
              </label>
            </div>
            
            <div class="space-y-4 mb-6" id="order-summary">
              <div class="flex justify-between">
                <span class="text-gray-600">Товары (<?php echo array_sum(array_column($cart_items, 'quantity')); ?> шт.):</span>
                <span class="font-medium" id="base-price"><?php echo number_format($total_cart_price, 0, '', ' '); ?> руб.</span>
              </div>
              
              <!-- Доплата за срочность (скрытая по умолчанию) -->
              <div class="flex justify-between hidden" id="urgent-surcharge">
                <span class="text-orange-600">Доплата за срочность (+50%):</span>
                <span class="font-medium text-orange-600" id="urgent-amount">0 руб.</span>
              </div>
              
              <div class="border-t border-gray-200 pt-4">
                <div class="flex justify-between text-xl font-bold">
                  <span>Итого к оплате:</span>
                  <span class="text-[#118568]" id="total-price"><?php echo number_format($total_cart_price, 0, '', ' '); ?> руб.</span>
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
// Визуальная обратная связь при обновлении
function showUpdateFeedback(element, type = 'success') {
    element.classList.add('animate-pulse');
    const originalBg = element.style.backgroundColor;
    element.style.backgroundColor = type === 'success' ? '#10B981' : '#EF4444';
    element.style.color = 'white';
    
    setTimeout(() => {
        element.style.backgroundColor = originalBg;
        element.style.color = '';
        element.classList.remove('animate-pulse');
    }, 1000);
}

function updateCartItemQuantity(inputElement) {
    const index = inputElement.getAttribute('data-index');
    const step = parseInt(inputElement.getAttribute('data-step')) || 1;
    let newQuantity = parseInt(inputElement.value) || step;

    // Округляем до кратности
    newQuantity = Math.max(step, Math.round(newQuantity / step) * step);

    if (newQuantity > 1000) newQuantity = 1000;

    inputElement.value = newQuantity;

    // Показываем обратную связь
    showUpdateFeedback(inputElement.parentElement);

    // Создаем форму для отправки
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';

    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);

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

    document.body.appendChild(form);
    form.submit();
}

// Переключение редактирования атрибутов
function toggleAttributeEdit(index) {
    const displayElement = document.getElementById(`attributes-display-${index}`);
    const editElement = document.getElementById(`attributes-edit-${index}`);
    
    if (displayElement && editElement) {
        displayElement.parentElement.classList.toggle('editing');
        displayElement.classList.toggle('hidden');
        editElement.classList.toggle('hidden');
    }
}

// Калькулятор срочного заказа
function toggleUrgentOrder() {
    const checkbox = document.getElementById('urgent-order');
    const urgentSurcharge = document.getElementById('urgent-surcharge');
    const urgentAmount = document.getElementById('urgent-amount');
    const totalPrice = document.getElementById('total-price');
    
    const basePrice = <?php echo $total_cart_price; ?>;
    
    if (checkbox.checked) {
        const surcharge = Math.round(basePrice * 0.5);
        const newTotal = basePrice + surcharge;
        
        urgentAmount.textContent = surcharge.toLocaleString('ru-RU') + ' руб.';
        totalPrice.textContent = newTotal.toLocaleString('ru-RU') + ' руб.';
        urgentSurcharge.classList.remove('hidden');
        
        // Анимация изменения цены
        totalPrice.classList.add('animate-pulse', 'text-orange-600');
        setTimeout(() => {
            totalPrice.classList.remove('animate-pulse');
        }, 1000);
    } else {
        totalPrice.textContent = basePrice.toLocaleString('ru-RU') + ' руб.';
        urgentSurcharge.classList.add('hidden');
        totalPrice.classList.remove('text-orange-600');
        totalPrice.classList.add('text-[#118568]');
    }
}

// Улучшенная мобильная адаптация
document.addEventListener('DOMContentLoaded', function() {
    // Адаптивные карточки товаров для мобильных устройств
    if (window.innerWidth <= 768) {
        const cartItems = document.querySelectorAll('.cart-item');
        cartItems.forEach(item => {
            item.classList.add('mobile-optimized');
        });
    }
    
    // Плавная прокрутка к корзине при изменениях
    const forms = document.querySelectorAll('form[action=""]');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<div class="spinner">⏳</div> Обновление...';
            }
        });
    });
});
</script>

<!-- Дополнительные стили для анимаций -->
<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.animate-pulse {
    animation: pulse 1s ease-in-out;
}

.mobile-optimized {
    padding: 1rem;
    margin-bottom: 1rem;
}

.editing {
    border-left: 3px solid #118568;
    padding-left: 1rem;
}

.spinner {
    display: inline-block;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .cart-item {
        flex-direction: column;
        gap: 1rem;
    }
    
    .quantity-controls {
        justify-content: center;
    }
    
    .item-actions {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
<?php include_once __DIR__ . '/includes/footer.php'; ?>