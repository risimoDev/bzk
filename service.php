<?php
require_once __DIR__ . '/includes/session.php';;
$pageTitle = "Товар";


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

$stmt = $pdo->prepare("SELECT min_qty, max_qty, price FROM product_quantity_prices WHERE product_id = ? ORDER BY min_qty ASC");
$stmt->execute([$product_id]);
$price_ranges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение изображений товара
$stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Получение характеристик товара
$stmt = $pdo->prepare("
    SELECT pa.id AS attribute_id, pa.name, pa.type, av.id AS value_id, av.value, av.price_modifier 
    FROM product_attributes pa 
    LEFT JOIN attribute_values av ON pa.id = av.attribute_id 
    WHERE pa.product_id = ?
    ORDER BY pa.id, av.id
");
$stmt->execute([$product_id]);
$raw_attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Группируем атрибуты по ID
$attributes = [];
foreach ($raw_attributes as $attr) {
  if (!isset($attributes[$attr['attribute_id']])) {
    $attributes[$attr['attribute_id']] = [
      'name' => $attr['name'],
      'type' => $attr['type'],
      'values' => []
    ];
  }
  if ($attr['value_id']) {
    $attributes[$attr['attribute_id']]['values'][] = $attr;
  }
}

// Кратность и минимальное количество
$multiplicity = $product['multiplicity'] ?? 1;
$min_quantity = $product['min_quantity'] ?? 1;

// --- Скидки на товар ---
$discount_value = 0;
$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("SELECT discount_value FROM discounts WHERE product_id = ? AND start_date <= ? AND end_date >= ? LIMIT 1");
$stmt->execute([$product_id, $now, $now]);
$active_discount = $stmt->fetchColumn();
if ($active_discount) {
  $discount_value = (float) $active_discount;
}
$base_price = (float) $product['base_price'];
$final_price = $discount_value ? $base_price * (1 - $discount_value / 100) : $base_price;

?>
<?php include_once __DIR__ . '/includes/header.php'; ?>
<!-- Подключение Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

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
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($product['name']); ?>
      </h1>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
    </div>

    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 lg:gap-0">
        <!-- Галерея изображений -->
        <div class="bg-gradient-to-br from-[#5E807F] to-[#118568] p-8 flex items-center justify-center">
          <div class="w-full max-w-lg">
            <!-- Основной слайдер -->
            <div class="swiper productSwiper rounded-2xl overflow-hidden shadow-2xl">
              <div class="swiper-wrapper">
                <?php if (!empty($images)): ?>
                  <?php foreach ($images as $image): ?>
                    <div class="swiper-slide">
                      <img src="<?php echo htmlspecialchars($image); ?>"
                        alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-96 object-cover">
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="swiper-slide">
                    <img src="/assets/images/no-image.webp" alt="Нет изображения" class="w-full h-96 object-cover">
                  </div>
                <?php endif; ?>
              </div>

              <!-- Навигация -->
              <div class="swiper-button-next text-white"></div>
              <div class="swiper-button-prev text-white"></div>
              <div class="swiper-pagination"></div>
            </div>

            <!-- Миниатюры -->
            <?php if (count($images) > 1): ?>
              <div class="swiper thumbnailSwiper mt-4">
                <div class="swiper-wrapper">
                  <?php foreach ($images as $index => $image): ?>
                    <div class="swiper-slide cursor-pointer opacity-50 hover:opacity-100 transition-opacity duration-300">
                      <img src="<?php echo htmlspecialchars($image); ?>"
                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                        class="w-20 h-20 object-cover rounded-lg border-2 border-white">
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Информация о товаре -->
        <div class="p-8 lg:p-12">
          <div class="flex flex-col h-full">
            <!-- Заголовок -->
            <div class="mb-6">
              <div class="flex items-center justify-between mb-4">
                <span class="px-4 py-2 bg-[#17B890] text-white text-sm font-bold rounded-full">
                  <?php echo $product['type'] === 'product' ? 'ТОВАР' : 'УСЛУГА'; ?>
                </span>
                <div class="flex items-center text-sm text-gray-500">
                  <?php if ($product['is_popular']): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-[#17B890]" fill="none"
                      viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    <span>Популярный товар</span>
                  <?php endif; ?>
                </div>
              </div>

              <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-4 leading-tight">
                <?php echo htmlspecialchars($product['name']); ?>
              </h1>

              <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full"></div>
            </div>

            <!-- Описание -->
            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Описание
              </h3>
              <p class="text-gray-600 leading-relaxed">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
              </p>
            </div>


            <!-- Характеристики -->
            <div class="mb-8 flex-grow">
              <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg>
                Характеристики
              </h3>

              <form id="add-to-cart-form" action="/cart/add" method="POST" class="space-y-6">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                <!-- Количество -->
                <div class="bg-[#DEE5E5] rounded-2xl p-4">
                  <label for="quantity" class="block text-gray-700 font-medium mb-3">Количество</label>
                  <div class="flex items-center">
                    <button type="button"
                      class="w-12 h-12 bg-[#118568] text-white rounded-l-lg decrease-quantity">-</button>
                    <input type="number" id="quantity" name="quantity" value="<?php echo $min_quantity; ?>"
                      min="<?php echo $min_quantity; ?>" step="<?php echo $multiplicity; ?>"
                      data-min="<?php echo $min_quantity; ?>" data-multiplicity="<?php echo $multiplicity; ?>"
                      class="w-20 h-12 text-center border-y border-gray-200 font-bold text-lg quantity-input">
                    <button type="button"
                      class="w-12 h-12 bg-[#118568] text-white rounded-r-lg increase-quantity">+</button>
                  </div>
                  <p class="mt-2 text-sm text-gray-600">
                    Минимум: <?php echo $min_quantity; ?>, Кратность: <?php echo $multiplicity; ?>
                  </p>
                </div>

                <!-- Атрибуты -->
                <?php if (!empty($attributes)): ?>
                  <?php foreach ($attributes as $attribute_id => $attribute_data): ?>
                    <div class="bg-white border-2 border-[#DEE5E5] rounded-2xl p-4 hover:border-[#17B890]">
                      <h4 class="text-gray-700 font-medium mb-3"><?php echo htmlspecialchars($attribute_data['name']); ?>:
                      </h4>
                      <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <?php foreach ($attribute_data['values'] as $value): ?>
                          <label class="cursor-pointer">
                            <input type="radio" name="attributes[<?php echo $attribute_id; ?>]"
                              value="<?php echo $value['value_id']; ?>"
                              data-price-modifier="<?php echo $value['price_modifier']; ?>" class="peer hidden" required>
                            <div
                              class="px-4 py-3 border-2 border-gray-200 rounded-lg text-center 
                                        hover:border-[#17B890] peer-checked:border-[#118568] peer-checked:bg-[#118568] peer-checked:text-white">
                              <?php echo htmlspecialchars($value['value']); ?>
                            </div>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

                <!-- Итоговая цена -->
                <div class="bg-gradient-to-r from-[#118568] to-[#0f755a] rounded-2xl p-6 text-white">
                  <div class="flex justify-between items-center">
                    <div>
                      <div class="text-lg">Примерная стоимость</div>
                      <?php if ($discount_value): ?>
                        <div class="text-sm opacity-90">Скидка <?php echo $discount_value; ?>%</div>
                      <?php endif; ?>
                    </div>
                    <div class="text-right">
                      <div class="text-3xl font-bold">
                        <span
                          id="total-price"><?php echo number_format($final_price * $min_quantity, 0, '', ' '); ?></span>
                        руб.
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Кнопка -->
                <button type="submit"
                  class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl font-bold text-lg">
                  Добавить в корзину
                </button>
                <div id="cart-warning" class="hidden mt-3 text-red-600 text-sm font-medium"></div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Дополнительная информация -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-800 mb-2">Гарантия качества</h3>
        <p class="text-gray-600 text-sm">Все товары проходят строгий контроль качества перед отправкой</p>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-800 mb-2">Скорость выполнения</h3>
        <p class="text-gray-600 text-sm">Быстрое выполнение заказов без потери качества</p>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-800 mb-2">Индивидуальный подход</h3>
        <p class="text-gray-600 text-sm">Каждый клиент получает персонализированное обслуживание</p>
      </div>
    </div>
  </div>
</main>

<!-- Подключение Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const quantityInput = document.getElementById('quantity');
    const totalPriceEl = document.getElementById('total-price');
    const attributeInputs = document.querySelectorAll('input[name^="attributes"]');

    let basePrice = <?php echo (float) $final_price; ?>;
    let priceRanges = <?php echo json_encode($price_ranges); ?>;

    function getUnitPrice(quantity) {
      let unitPrice = basePrice;

      priceRanges.forEach(r => {
        let min = parseInt(r.min_qty);
        let max = r.max_qty ? parseInt(r.max_qty) : Infinity;

        if (quantity >= min && quantity <= max) {
          unitPrice = parseFloat(r.price);
        }
      });

      return unitPrice;
    }

    function updateTotalPrice() {
      let quantity = parseInt(quantityInput.value) || 1;
      let unitPrice = getUnitPrice(quantity);

      let total = unitPrice * quantity;

      attributeInputs.forEach(input => {
        if (input.checked) {
          let modifier = parseFloat(input.dataset.priceModifier || 0);
          total += modifier * quantity;
        }
      });

      totalPriceEl.textContent = total.toLocaleString('ru-RU');
    }

    // --- Обработчики ---
    if (quantityInput) {
      quantityInput.addEventListener('input', updateTotalPrice);
    }
    attributeInputs.forEach(input => {
      input.addEventListener('change', updateTotalPrice);
    });

    document.querySelectorAll('.decrease-quantity').forEach(btn => {
      btn.addEventListener('click', () => {
        let min = parseInt(quantityInput.dataset.min) || 1;
        let step = parseInt(quantityInput.dataset.multiplicity) || 1;
        let newValue = Math.max(min, (parseInt(quantityInput.value) || min) - step);
        quantityInput.value = newValue;
        updateTotalPrice();
      });
    });

    document.querySelectorAll('.increase-quantity').forEach(btn => {
      btn.addEventListener('click', () => {
        let min = parseInt(quantityInput.dataset.min) || 1;
        let step = parseInt(quantityInput.dataset.multiplicity) || 1;
        let newValue = (parseInt(quantityInput.value) || min) + step;
        quantityInput.value = newValue;
        updateTotalPrice();
      });
    });

    updateTotalPrice(); // Первый вызов
  });

  document.addEventListener('DOMContentLoaded', function () {
    var swiper = new Swiper(".productSwiper", {
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

    // Связываем основной слайдер с миниатюрами
    if (swiper && thumbnailSwiper) {
      swiper.thumbs = { swiper: thumbnailSwiper };
      swiper.thumbs.init();
    }
  });
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('add-to-cart-form');
  if (!form) return;

  // Отключаем автоматическую HTML-валидацию, чтобы контролировать процесс самим,
  // но будем вызывать checkValidity/reportValidity для остальных полей.
  form.noValidate = true;

  const warningEl = document.getElementById('cart-warning');
  if (warningEl) {
    // аккуратно сбрасываем стиль при старте
    warningEl.classList.add('hidden');
    warningEl.setAttribute('role', 'alert');
  }

  form.addEventListener('submit', function(e) {
    e.preventDefault(); // всегда предотвращаем дефолт — далее решаем вручную

    // Снимаем предыдущие подсветки / сообщения
    clearHighlights();
    if (warningEl) {
      warningEl.classList.add('hidden');
      warningEl.textContent = '';
    }

    // Собираем группы атрибутов: имя полностью (например "attributes[12]")
    const inputs = Array.from(form.querySelectorAll('input[name^="attributes"]'));
    const groups = {}; // { 'attributes[12]': [input, input, ...], ... }
    inputs.forEach(inp => {
      const name = inp.getAttribute('name');
      groups[name] = groups[name] || [];
      groups[name].push(inp);
    });

    // Проверяем, для каждой группы есть ли выбранный input
    const missingGroups = [];
    Object.keys(groups).forEach(groupName => {
      const checked = form.querySelector(`input[name="${groupName}"]:checked`);
      if (!checked) {
        // Попали в ошибку для этой группы
        // Попробуем найти блок с заголовком h4 (где отображено имя характеристики)
        const firstInput = groups[groupName][0];
        const wrapper = findWrapperWithH4(firstInput);
        let title = groupName;
        if (wrapper) {
          // подсветка контейнера
          wrapper.classList.add('ring-2', 'ring-red-400', 'border-red-400');
          const h4 = wrapper.querySelector('h4');
          if (h4) title = h4.textContent.trim().replace(/:$/, '');
        }
        missingGroups.push(title);
      }
    });

    if (missingGroups.length > 0) {
      // Показываем сообщение и выходим — не отправляем форму
      if (warningEl) {
        warningEl.classList.remove('hidden');
        warningEl.textContent = '⚠️ Выберите характеристики: ' + missingGroups.join(', ');
        warningEl.scrollIntoView({behavior: 'smooth', block: 'center'});
      } else {
        alert('Пожалуйста, выберите характеристики: ' + missingGroups.join(', '));
      }

      // Убираем подсветку через 4.5 секунды
      setTimeout(clearHighlights, 4500);
      return;
    }

    // Если все атрибуты выбраны — проверяем остальные нативные правила
    if (!form.checkValidity()) {
      // Покажем браузерные подсказки (для полей, у которых есть constraints)
      form.reportValidity();
      return;
    }

    // Всё ок — отправляем форму программно
    // (предварительно удаляем временный novalidate, чтобы при submit всё как обычно)
    form.noValidate = false;
    form.submit();
  });

  // ----------------- вспомогательные функции -----------------
  function findWrapperWithH4(startEl) {
    let node = startEl;
    while (node && node !== form) {
      try {
        // если внутри node есть h4 — считаем его контейнером группы
        if (node.querySelector && node.querySelector('h4')) {
          return node;
        }
      } catch (err) {
        // на всякий случай
      }
      node = node.parentElement;
    }
    return null;
  }

  function clearHighlights() {
    // убираем классы подсветки, которые добавляли
    const highlighted = form.querySelectorAll('.ring-2.ring-red-400, .border-red-400');
    highlighted.forEach(el => {
      el.classList.remove('ring-2', 'ring-red-400', 'border-red-400');
    });
  }
});
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>