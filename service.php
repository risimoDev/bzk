<?php
session_start();
$pageTitle = "Товар";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    die("Товар не найден.");
}

// Получение информации о товаре
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Товар не найден.");
}

// Получение изображений товара
$stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Получение характеристик товара
$stmt = $pdo->prepare("
    SELECT pa.id AS attribute_id, pa.name, pa.type, av.id AS value_id, av.value, av.price_modifier 
    FROM product_attributes pa 
    LEFT JOIN attribute_values av ON pa.id = av.attribute_id 
    WHERE pa.product_id = ?
");
$stmt->execute([$product_id]);
$attributes = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
?>

<main class="bg-pattern min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
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

    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 lg:gap-0">
        <!-- Галерея изображений -->
        <div class="bg-gradient-to-br from-[#5E807F] to-[#118568] p-8 flex items-center justify-center">
          <div class="w-full max-w-lg">
            <div class="swiper mySwiper rounded-2xl overflow-hidden shadow-2xl">
              <div class="swiper-wrapper">
                <?php if (!empty($images)): ?>
                  <?php foreach ($images as $image): ?>
                    <div class="swiper-slide">
                      <img src="<?php echo htmlspecialchars($image); ?>" 
                           alt="<?php echo htmlspecialchars($product['name']); ?>" 
                           class="w-full h-96 object-cover">
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="swiper-slide">
                    <img src="/assets/images/no-image.webp" 
                         alt="Нет изображения" 
                         class="w-full h-96 object-cover">
                  </div>
                <?php endif; ?>
              </div>
              
              <!-- Навигация -->
              <div class="swiper-button-next text-white"></div>
              <div class="swiper-button-prev text-white"></div>
              <div class="swiper-pagination"></div>
            </div>

            <!-- Миниатюры -->
            <div class="swiper thumbnailSwiper mt-4 hidden lg:block">
              <div class="swiper-wrapper">
                <?php if (!empty($images)): ?>
                  <?php foreach ($images as $index => $image): ?>
                    <div class="swiper-slide cursor-pointer opacity-50 hover:opacity-100 transition-opacity duration-300">
                      <img src="<?php echo htmlspecialchars($image); ?>" 
                           alt="<?php echo htmlspecialchars($product['name']); ?>" 
                           class="w-20 h-20 object-cover rounded-lg border-2 border-white">
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Информация о товаре -->
        <div class="p-8 lg:p-12">
          <div class="flex flex-col h-full">
            <!-- Заголовок -->
            <div class="mb-6">
              <div class="flex items-center justify-between mb-4">
                <span class="px-4 py-2 bg-[#17B890] text-white text-sm font-bold rounded-full">
                  <?php echo $type === 'product' ? 'ТОВАР' : 'УСЛУГА'; ?>
                </span>
                <div class="flex items-center text-sm text-gray-500">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                  </svg>
                  <span>Популярный товар</span>
                </div>
              </div>
              
              <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-4 leading-tight">
                <?php echo htmlspecialchars($product['name']); ?>
              </h1>
              
              <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full"></div>
            </div>

            <!-- Описание -->
            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-800 mb-3">Описание</h3>
              <p class="text-gray-600 leading-relaxed">
                <?php echo htmlspecialchars($product['description']); ?>
              </p>
            </div>

            <!-- Характеристики -->
            <div class="mb-8 flex-grow">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Характеристики</h3>
              
              <form action="/cart/add" method="POST" class="space-y-6">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                <!-- Количество -->
                <div class="bg-[#DEE5E5] rounded-xl p-4">
                  <label for="quantity" class="block text-gray-700 font-medium mb-3">Количество</label>
                  <div class="flex items-center">
                    <button type="button" class="w-12 h-12 bg-[#118568] text-white rounded-l-lg hover:bg-[#0f755a] transition-colors duration-300 flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                      </svg>
                    </button>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" 
                           class="w-20 h-12 text-center border-y border-gray-200 focus:outline-none font-bold text-lg">
                    <button type="button" class="w-12 h-12 bg-[#118568] text-white rounded-r-lg hover:bg-[#0f755a] transition-colors duration-300 flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Атрибуты -->
                <?php if (!empty($attributes)): ?>
                  <?php foreach ($attributes as $attribute_id => $values): ?>
                    <div class="bg-white border-2 border-[#DEE5E5] rounded-xl p-4 hover:border-[#17B890] transition-colors duration-300">
                      <h4 class="text-gray-700 font-medium mb-3"><?php echo htmlspecialchars($values[0]['name']); ?>:</h4>
                      <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <?php foreach ($values as $value): ?>
                          <label class="block">
                            <input type="radio" 
                                   name="attributes[<?php echo $attribute_id; ?>]" 
                                   value="<?php echo $value['value_id']; ?>" 
                                   data-price-modifier="<?php echo $value['price_modifier']; ?>"
                                   required
                                   class="hidden">
                            <div class="px-4 py-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-[#17B890] transition-colors duration-300 text-center peer-checked:border-[#118568] peer-checked:bg-[#118568] peer-checked:text-white">
                              <div class="font-medium"><?php echo htmlspecialchars($value['value']); ?></div>
                              <?php if ($value['price_modifier'] > 0): ?>
                                <div class="text-xs mt-1">+<?php echo number_format($value['price_modifier'], 0, '', ' '); ?> руб.</div>
                              <?php endif; ?>
                            </div>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                    <p class="text-yellow-800">Нет доступных характеристик для выбора.</p>
                  </div>
                <?php endif; ?>

                <!-- Итоговая цена -->
                <div class="bg-gradient-to-r from-[#118568] to-[#17B890] rounded-2xl p-6 text-white">
                  <div class="flex justify-between items-center">
                    <div>
                      <div class="text-lg">Итоговая стоимость</div>
                      <div class="text-sm opacity-90">с учетом выбранных опций</div>
                    </div>
                    <div class="text-right">
                      <div class="text-3xl font-bold">
                        <span id="total-price"><?php echo number_format($product['base_price'], 0, '', ' '); ?></span> 
                        <span class="text-xl">руб.</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Кнопка добавления в корзину -->
                <button type="submit" class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-2xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                  Добавить в корзину
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Дополнительная информация -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
      <div class="bg-white rounded-2xl shadow-lg p-6 text-center hover:shadow-xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Гарантия качества</h3>
        <p class="text-gray-600 text-sm">Все товары проходят строгий контроль качества перед отправкой</p>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6 text-center hover:shadow-xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Безопасная оплата</h3>
        <p class="text-gray-600 text-sm">Различные способы оплаты с гарантией безопасности</p>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6 text-center hover:shadow-xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Быстрая доставка</h3>
        <p class="text-gray-600 text-sm">Доставка по всей России в кратчайшие сроки</p>
      </div>
    </div>
  </div>
</main>

<script>
  // Обновление стоимости в реальном времени
  document.addEventListener('DOMContentLoaded', () => {
    const basePrice = parseFloat(<?php echo $product['base_price']; ?>);
    const totalPriceElement = document.getElementById('total-price');
    const radioInputs = document.querySelectorAll('input[type="radio"]');
    const quantityInput = document.getElementById('quantity');

    // Кнопки изменения количества
    const minusBtn = document.querySelector('button[type="button"]:first-child');
    const plusBtn = document.querySelector('button[type="button"]:last-child');

    function updateTotalPrice() {
      let total = basePrice * parseInt(quantityInput.value);
      radioInputs.forEach(input => {
        if (input.checked) {
          const modifier = parseFloat(input.dataset.priceModifier || 0);
          total += modifier * parseInt(quantityInput.value);
        }
      });
      totalPriceElement.textContent = total.toLocaleString('ru-RU');
    }

    radioInputs.forEach(input => {
      input.addEventListener('change', updateTotalPrice);
    });

    quantityInput.addEventListener('input', updateTotalPrice);

    minusBtn.addEventListener('click', () => {
      let value = parseInt(quantityInput.value);
      if (value > 1) {
        quantityInput.value = value - 1;
        updateTotalPrice();
      }
    });

    plusBtn.addEventListener('click', () => {
      let value = parseInt(quantityInput.value);
      quantityInput.value = value + 1;
      updateTotalPrice();
    });

    // Инициализация при загрузке
    updateTotalPrice();
  });

  // Инициализация Swiper
  document.addEventListener('DOMContentLoaded', function() {
    var swiper = new Swiper(".mySwiper", {
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },
      loop: true,
    });

    var thumbnailSwiper = new Swiper(".thumbnailSwiper", {
      slidesPerView: 4,
      spaceBetween: 10,
      freeMode: true,
      watchSlidesProgress: true,
    });
  });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>