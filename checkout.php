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
$applied_promo_code = $_SESSION['applied_promo_code'] ?? '';
$promo_discount_amount = $_SESSION['promo_discount_amount'] ?? 0;
$promo_code_error = $_SESSION['promo_code_error'] ?? '';
$final_total = $_SESSION['final_total'] ?? $cart_total;

// Очищаем сообщения из сессии после отображения
unset($_SESSION['promo_code_error'], $_SESSION['promo_discount_amount'], $_SESSION['final_total']);
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
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
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Оформление заказа</h1>
      <p class="text-xl text-gray-700 max-w-3xl mx-auto">
        Заполните контактные данные и адрес доставки для завершения покупки
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Левая колонка - Содержимое заказа -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden mb-8">
          <div class="p-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              Содержимое заказа
            </h2>
            <p class="text-gray-600 mt-1"><?php echo count($cart_items); ?> <?php echo count($cart_items) == 1 ? 'товар' : (count($cart_items) < 5 ? 'товара' : 'товаров'); ?></p>
          </div>
          
          <div class="divide-y divide-gray-100">
            <?php foreach ($cart_items as $item): ?>
              <div class="p-6">
                <div class="flex flex-col md:flex-row gap-6">
                  <!-- Изображение товара -->
                  <div class="flex-shrink-0">
                    <?php $image_url = $item['main_image'] ?: '/assets/images/no-image.webp'; ?>
                    <img src="<?php echo htmlspecialchars($image_url); ?>" 
                         alt="<?php echo htmlspecialchars($item['product']['name']); ?>" 
                         class="w-20 h-20 object-cover rounded-xl">
                  </div>
                  
                  <!-- Информация о товаре -->
                  <div class="flex-grow">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                      <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-1">
                          <?php echo htmlspecialchars($item['product']['name']); ?>
                        </h3>
                        
                        <!-- Характеристики -->
                        <?php if (!empty($item['attributes'])): ?>
                          <div class="mb-2">
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
                        <div class="text-lg font-bold text-[#118568]">
                          <?php echo number_format($item['total_price'], 0, '', ' '); ?> <span class="text-base">руб.</span>
                        </div>
                        <div class="text-gray-500 text-sm mt-1">
                          <?php echo $item['quantity']; ?> шт. × <?php echo number_format($item['base_price'] + $item['total_attributes_price'], 0, '', ' '); ?> руб.
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          
          <!-- Итог по заказу -->
          <div class="p-6 bg-gray-50">
            <div class="flex justify-between items-center">
              <div class="text-lg font-bold text-gray-800">Итого к оплате:</div>
              <div class="text-2xl font-bold text-[#118568]">
                <?php echo number_format($total_price, 0, '', ' '); ?> <span class="text-xl">руб.</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Информация о доставке -->
        <div class="bg-white rounded-3xl shadow-2xl p-6">
          <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#17B890]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Информация о доставке
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-center p-4 bg-[#DEE5E5] rounded-xl">
              <div class="w-10 h-10 bg-[#118568] rounded-full flex items-center justify-center mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <div class="font-semibold text-gray-800">Сроки</div>
                <div class="text-sm text-gray-600">1-3 рабочих дня</div>
              </div>
            </div>
            <div class="flex items-center p-4 bg-[#9DC5BB] rounded-xl">
              <div class="w-10 h-10 bg-[#17B890] rounded-full flex items-center justify-center mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <div>
                <div class="font-semibold text-gray-800">Гарантия</div>
                <div class="text-sm text-gray-600">Качество проверено</div>
              </div>
            </div>
            <div class="flex items-center p-4 bg-[#5E807F] rounded-xl">
              <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                </svg>
              </div>
              <div>
                <div class="font-semibold text-white">Бесплатно</div>
                <div class="text-sm text-white/90">Доставка по городу</div>
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
              <label class="block text-gray-700 font-medium mb-2">Имя и фамилия</label>
              <input type="text" name="name" 
                     value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" 
                     placeholder="Введите ваше имя" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                     required>
            </div>

            <div>
              <label class="block text-gray-700 font-medium mb-2">Email адрес</label>
              <input type="email" name="email" 
                     value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                     placeholder="Введите ваш email" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                     required>
            </div>

            <div>
              <label class="block text-gray-700 font-medium mb-2">Номер телефона</label>
              <input type="tel" name="phone" 
                     value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                     placeholder="+7 (___) ___-____" 
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                     required>
            </div>

            <div>
              <label class="block text-gray-700 font-medium mb-2">Адрес доставки</label>
              <textarea name="shipping_address" 
                        placeholder="Введите полный адрес доставки" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 resize-none"
                        rows="4" 
                        required><?php echo htmlspecialchars($user['shipping_address'] ?? ''); ?></textarea>
            </div>

            <div>
              <label class="block text-gray-700 font-medium mb-2">Комментарий к заказу</label>
              <textarea name="comment" 
                        placeholder="Дополнительная информация для доставки" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 resize-none"
                        rows="3"></textarea>
            </div>

            <div class="pt-4 border-t border-gray-200">
              <div class="flex items-center mb-4">
                <input type="checkbox" id="terms" class="rounded border-gray-300 text-[#118568] focus:ring-[#17B890]" required>
                <label for="terms" class="ml-2 text-gray-700 text-sm">
                  Я согласен с <a href="/terms" class="text-[#118568] hover:underline">условиями использования</a> 
                  и <a href="/privacy" class="text-[#118568] hover:underline">политикой конфиденциальности</a>
                </label>
              </div>

              <button type="submit" 
                      class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Подтвердить заказ
              </button>
            </div>

            <!-- Методы оплаты -->
            <div class="pt-4 border-t border-gray-200">
              <h3 class="font-medium text-gray-800 mb-3">Способы оплаты</h3>
              <div class="space-y-2">
                <div class="flex items-center p-3 bg-[#DEE5E5] rounded-lg">
                  <div class="w-4 h-4 bg-[#118568] rounded-full flex items-center justify-center mr-3">
                    <div class="w-2 h-2 bg-white rounded-full"></div>
                  </div>
                  <span class="text-gray-800">Наличными при получении</span>
                </div>
                <div class="flex items-center p-3 bg-gray-100 rounded-lg">
                  <div class="w-4 h-4 border-2 border-gray-300 rounded-full mr-3"></div>
                  <span class="text-gray-600">Банковской картой</span>
                </div>
                <div class="flex items-center p-3 bg-gray-100 rounded-lg">
                  <div class="w-4 h-4 border-2 border-gray-300 rounded-full mr-3"></div>
                  <span class="text-gray-600">Электронный кошелек</span>
                </div>
              </div>
            </div>
            <div class="mb-6 p-4 bg-blue-50 rounded-xl">
    <label for="promo_code" class="block text-gray-700 font-medium mb-2">Промокод</label>
    <div class="flex">
        <input type="text" id="promo_code" name="promo_code" 
               value="<?php echo htmlspecialchars($applied_promo_code); ?>"
               class="flex-grow px-4 py-2 border-2 border-gray-200 rounded-l-lg focus:outline-none focus:border-[#118568]">
        <button type="button" id="apply_promo" 
                class="px-4 py-2 bg-[#118568] text-white rounded-r-lg hover:bg-[#0f755a] transition-colors duration-300">
            Применить
        </button>
    </div>
    <div id="promo_message" class="mt-2 text-sm">
        <?php if ($promo_code_error): ?>
            <span class="text-red-600"><?php echo htmlspecialchars($promo_code_error); ?></span>
        <?php elseif ($applied_promo_code): ?>
            <span class="text-green-600">Промокод "<?php echo htmlspecialchars($applied_promo_code); ?>" применен! Скидка: <?php echo number_format($promo_discount_amount, 2, '.', ''); ?> руб.</span>
        <?php endif; ?>
    </div>
</div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>