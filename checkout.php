<?php
session_start();
$pageTitle = "Оформление заказа";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: /cart");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    // Если пользователь не авторизован, перенаправляем на страницу входа/регистрации
    $_SESSION['notifications'][] = ['type' => 'info', 'message' => 'Для оформления заказа необходимо войти или зарегистрироваться.'];
    header("Location: /login");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$cart_items = [];
$total_price = 0;

foreach ($cart as $item) {
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
            'product' => $product,
            'quantity' => $item['quantity'],
            'attributes' => $selected_attributes,
            'base_price' => $base_price,
            'total_attributes_price' => $total_attributes_price,
            'total_price' => $item_total_price,
            'main_image' => $main_image
        ];

        $total_price += $item_total_price;
    }
}

// Проверяем, был ли выбран срочный заказ
$is_urgent = isset($_SESSION['is_urgent_order']) ? $_SESSION['is_urgent_order'] : false;
$urgent_multiplier = $is_urgent ? 1.5 : 1;
$final_total = $total_price * $urgent_multiplier;

$applied_promo_code = $_SESSION['applied_promo_code'] ?? '';
$promo_discount_amount = $_SESSION['promo_discount_amount'] ?? 0;
$promo_code_error = $_SESSION['promo_code_error'] ?? '';

// Очищаем сообщения из сессии после отображения
unset($_SESSION['promo_code_error'], $_SESSION['promo_discount_amount']);
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/cart" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">
          Корзина
        </a>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Оформление заказа</h1>
      <p class="text-xl text-gray-700 max-w-3xl mx-auto">
        Заполните контактные данные и адрес доставки для завершения покупки
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Левая колонка - Содержимое заказа -->
      <div class="lg:col-span-2 space-y-8">
        <!-- Содержимое заказа -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
          <div class="p-6 border-b border-[#DEE5E5]">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-2.293 2.293c-.63.63-1.283 1.283-1.946 1.946-.663.663-1.326 1.326-1.946 1.946-.63.63-1.283 1.283-1.946 1.946-.663.663-1.326 1.326-1.946 1.946L3 21v-5.4L.707 13.707c-.63-.63-.184-1.707.707-1.707H7m0 0v6m0-6l4-4m-4 4l4 4" />
              </svg>
              Содержимое заказа
            </h2>
            <p class="text-gray-600"><?php echo count($cart_items); ?> <?php echo count($cart_items) == 1 ? 'товар' : (count($cart_items) < 5 ? 'товара' : 'товаров'); ?></p>
          </div>
          
          <div class="divide-y divide-[#DEE5E5]">
            <?php foreach ($cart_items as $item): ?>
              <div class="p-6 hover:bg-[#f8fafa] transition-colors duration-300">
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
                        <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($item['product']['name']); ?></h3>
                        
                        <?php if (!empty($item['attributes'])): ?>
                          <div class="mb-3">
                            <div class="text-sm text-gray-600 mb-1">Характеристики:</div>
                            <div class="flex flex-wrap gap-2">
                              <?php foreach ($item['attributes'] as $attribute): ?>
                                <span class="px-3 py-1 bg-[#DEE5E5] text-gray-700 text-xs rounded-full">
                                  <?php echo htmlspecialchars($attribute['attribute_name']); ?>: 
                                  <?php echo htmlspecialchars($attribute['value']); ?>
                                  <?php if ($attribute['price_modifier'] > 0): ?>
                                    <span class="text-[#118568]">(+<?php echo number_format($attribute['price_modifier'], 0, '', ' '); ?> руб.)</span>
                                  <?php endif; ?>
                                </span>
                              <?php endforeach; ?>
                            </div>
                          </div>
                        <?php endif; ?>
                        
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                          <span class="font-medium">Количество: <?php echo htmlspecialchars($item['quantity']); ?> шт.</span>
                          <span class="font-medium">Цена за единицу: <?php echo number_format($item['base_price'] + $item['total_attributes_price'], 0, '', ' '); ?> руб.</span>
                        </div>
                      </div>
                      
                      <div class="flex items-center md:items-end md:flex-col md:justify-end">
                        <div class="text-2xl font-bold text-[#118568]">
                          <?php echo number_format($item['total_price'], 0, '', ' '); ?> руб.
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          
          <!-- Итог по заказу -->
          <div class="p-6 bg-gray-50 border-t border-[#DEE5E5]">
            <div class="space-y-4">
              <div class="flex justify-between">
                <span class="text-gray-600">Стоимость товаров:</span>
                <span class="font-medium"><?php echo number_format($total_price, 0, '', ' '); ?> руб.</span>
              </div>
              
              <?php if ($is_urgent): ?>
              <div class="flex justify-between text-[#17B890]">
                <span class="font-medium">Срочный заказ (+50%):</span>
                <span class="font-bold">+<?php echo number_format($total_price * 0.5, 0, '', ' '); ?> руб.</span>
              </div>
              <?php endif; ?>
              
              <?php if ($promo_discount_amount > 0): ?>
              <div class="flex justify-between text-red-500">
                <span class="font-medium">Скидка по промокоду:</span>
                <span class="font-bold">-<?php echo number_format($promo_discount_amount, 0, '', ' '); ?> руб.</span>
              </div>
              <?php endif; ?>
              
              <div class="border-t border-[#DEE5E5] pt-4">
                <div class="flex justify-between text-xl font-bold">
                  <span>Итого к оплате:</span>
                  <span class="text-[#118568]"><?php echo number_format($final_total - $promo_discount_amount, 0, '', ' '); ?> руб.</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Информация о доставке -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Информация о доставке
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="flex items-center p-4 bg-[#DEE5E5] rounded-2xl hover:bg-[#9DC5BB] transition-colors duration-300">
              <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-gray-800">Сроки</h4>
                <p class="text-gray-600 text-sm">1-3 рабочих дня</p>
              </div>
            </div>
            
            <div class="flex items-center p-4 bg-[#9DC5BB] rounded-2xl hover:bg-[#5E807F] transition-colors duration-300">
              <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-gray-800">Гарантия</h4>
                <p class="text-gray-600 text-sm">Качество проверено</p>
              </div>
            </div>
            
            <div class="flex items-center p-4 bg-[#5E807F] rounded-2xl hover:bg-[#17B890] transition-colors duration-300">
              <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-white">Бесплатно</h4>
                <p class="text-white text-sm">Доставка по городу</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Правая колонка - Форма оформления -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-3xl shadow-2xl p-6 sticky top-8">
          <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Контактные данные
          </h2>

          <form action="/checkoutshopcart/process" method="POST" class="space-y-6">
            <div>
              <label for="name" class="block text-gray-700 font-medium mb-2">Имя и фамилия *</label>
              <input type="text" id="name" name="name" 
                     value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                     placeholder="Введите ваше имя" required>
            </div>

            <div>
              <label for="email" class="block text-gray-700 font-medium mb-2">Email адрес *</label>
              <input type="email" id="email" name="email" 
                     value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                     placeholder="Введите ваш email" required>
            </div>

            <div>
              <label for="phone" class="block text-gray-700 font-medium mb-2">Номер телефона *</label>
              <input type="tel" id="phone" name="phone" 
                     value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                     placeholder="+7 (___) ___-____" required>
            </div>

            <div>
              <label for="shipping_address" class="block text-gray-700 font-medium mb-2">Адрес доставки *</label>
              <textarea id="shipping_address" name="shipping_address" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 resize-none"
                        placeholder="Введите полный адрес доставки" rows="4" required><?php echo htmlspecialchars($user['shipping_address'] ?? ''); ?></textarea>
            </div>

            <div>
              <label for="comment" class="block text-gray-700 font-medium mb-2">Комментарий к заказу</label>
              <textarea id="comment" name="comment" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 resize-none"
                        placeholder="Дополнительная информация для доставки" rows="3"></textarea>
            </div>

            <!-- Чекбокс "Срочный заказ" -->
            <div class="p-4 bg-[#DEE5E5] rounded-2xl">
              <div class="flex items-start">
                <input type="checkbox" id="is_urgent" name="is_urgent" value="1" 
                       <?php echo $is_urgent ? 'checked' : ''; ?>
                       class="mt-1 rounded border-gray-300 text-[#118568] focus:ring-[#17B890] w-5 h-5">
                <label for="is_urgent" class="ml-3">
                  <span class="block font-medium text-gray-800">Срочный заказ</span>
                  <span class="block text-sm text-gray-600 mt-1">
                    Увеличивает стоимость на 50% за срочность выполнения
                  </span>
                  <?php if ($is_urgent): ?>
                    <span class="inline-block mt-2 px-2 py-1 bg-[#17B890] text-white text-xs rounded-full">
                      Активен: +<?php echo number_format($total_price * 0.5, 0, '', ' '); ?> руб.
                    </span>
                  <?php endif; ?>
                </label>
              </div>
            </div>

            <!-- Промокод -->
            <div class="p-4 bg-gray-50 rounded-2xl">
              <label for="promo_code" class="block text-gray-700 font-medium mb-2">Промокод</label>
              <div class="flex">
                <input type="text" id="promo_code" name="promo_code" 
                       value="<?php echo htmlspecialchars($applied_promo_code); ?>"
                       class="flex-grow px-4 py-3 border-2 border-gray-200 rounded-l-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                       placeholder="Введите промокод">
                <button type="button" id="apply_promo" 
                        class="px-4 py-3 bg-[#118568] text-white rounded-r-lg hover:bg-[#0f755a] transition-colors duration-300">
                  Применить
                </button>
              </div>
              <div id="promo_message" class="mt-2 text-sm">
                <?php if ($promo_code_error): ?>
                  <span class="text-red-600"><?php echo htmlspecialchars($promo_code_error); ?></span>
                <?php elseif ($applied_promo_code): ?>
                  <span class="text-green-600">
                    Промокод "<?php echo htmlspecialchars($applied_promo_code); ?>" применен! 
                    Скидка: <?php echo number_format($promo_discount_amount, 0, '', ' '); ?> руб.
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div class="pt-4 border-t border-[#DEE5E5]">
              <div class="flex items-center mb-6">
                <input type="checkbox" id="terms" name="terms" value="1" 
                       class="rounded border-gray-300 text-[#118568] focus:ring-[#17B890] w-5 h-5" required>
                <label for="terms" class="ml-2 text-gray-700 text-sm">
                  Я согласен с <a href="/terms" class="text-[#118568] hover:underline">условиями использования</a> 
                  и <a href="/privacy" class="text-[#118568] hover:underline">политикой конфиденциальности</a>
                </label>
              </div>

              <button type="submit" name="place_order"
                      class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Подтвердить заказ
                <span class="ml-2">(<?php echo number_format($final_total - $promo_discount_amount, 0, '', ' '); ?> руб.)</span>
              </button>
            </div>

            <!-- Методы оплаты -->
            <div class="pt-6 border-t border-[#DEE5E5]">
              <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                Способы оплаты
              </h3>
              <div class="space-y-3">
                <div class="flex items-center p-3 bg-[#DEE5E5] rounded-xl hover:bg-[#9DC5BB] transition-colors duration-300">
                  <div class="w-4 h-4 bg-[#118568] rounded-full flex items-center justify-center mr-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2 w-2 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <span class="text-gray-800">Наличными при получении</span>
                </div>
                <div class="flex items-center p-3 bg-gray-100 rounded-xl">
                  <div class="w-4 h-4 border-2 border-gray-300 rounded-full mr-3"></div>
                  <span class="text-gray-600">Банковской картой</span>
                </div>
                <div class="flex items-center p-3 bg-gray-100 rounded-xl">
                  <div class="w-4 h-4 border-2 border-gray-300 rounded-full mr-3"></div>
                  <span class="text-gray-600">Электронный кошелек</span>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const urgentCheckbox = document.getElementById('is_urgent');
    const applyPromoButton = document.getElementById('apply_promo');
    const promoCodeInput = document.getElementById('promo_code');
    const promoMessage = document.getElementById('promo_message');
    
    if (urgentCheckbox) {
        urgentCheckbox.addEventListener('change', function() {
            // Отправляем AJAX-запрос для обновления состояния срочного заказа
            const formData = new FormData();
            formData.append('is_urgent', this.checked ? '1' : '0');
            
            fetch('/ajax/toggle_urgent_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Перезагружаем страницу для обновления итоговой суммы
                    location.reload();
                } else {
                    alert('Ошибка при изменении статуса срочного заказа.');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при изменении статуса срочного заказа.');
            });
        });
    }
    
    if (applyPromoButton) {
        applyPromoButton.addEventListener('click', function() {
            const promoCode = promoCodeInput.value.trim();
            if (!promoCode) {
                alert('Пожалуйста, введите промокод.');
                return;
            }
            
            // Отправляем AJAX-запрос для проверки промокода
            const formData = new FormData();
            formData.append('promo_code', promoCode);
            
            fetch('/ajax/check_promo_code.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Перезагружаем страницу для обновления итоговой суммы
                    location.reload();
                } else {
                    promoMessage.innerHTML = '<span class="text-red-600">' + data.message + '</span>';
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                promoMessage.innerHTML = '<span class="text-red-600">Произошла ошибка при проверке промокода.</span>';
            });
        });
    }
});
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>