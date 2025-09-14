<?php
require_once __DIR__ . '/includes/session.php';
$pageTitle = "Главная страница";


// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';


// Получение популярных товаров
$stmt = $pdo->prepare("SELECT * FROM products WHERE is_popular = 1 LIMIT 6");
$stmt->execute();
$popularProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение партнеров (пример таблицы partners)
$stmt = $pdo->prepare("SELECT * FROM partners");
$stmt->execute();
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Функция для получения главного изображения товара
function getProductMainImage($pdo, $product_id) {
    $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_main = 1 LIMIT 1");
    $stmt->execute([$product_id]);
    return $stmt->fetchColumn();
}
?>

<?php include_once __DIR__ . '/includes/header.php';?>

<main class="font-sans bg-pattern min-h-screen">
  <!-- Баннер -->
  <section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-[#118568] to-[#17B890]"></div>
    <div class="absolute inset-0 opacity-10">
      <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\" viewBox=\"0 0 100 100\"><rect width=\"100\" height=\"100\" fill=\"none\" stroke=\"white\" stroke-width=\"0.5\" opacity=\"0.3\"/></svg>');"></div>
    </div>
    <div class="container mx-auto px-4 py-24 md:py-32 relative z-10">
      <div class="max-w-4xl mx-auto text-center">
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-6 leading-tight">
          <p class="text-[#9DC5BB]">Рекламно-производственная компания</p> <br><span">BZK PRINT</span>
        </h1>
        <p class="text-xl md:text-2xl text-white/90 mb-10 max-w-3xl mx-auto leading-relaxed">
          Высокое качество печати и индивидуальный подход к каждому клиенту. 
          Создаем решения, которые вдохновляют и впечатляют.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/catalog" class="px-8 py-4 bg-[#17B890] text-white rounded-xl hover:bg-[#14a380] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Перейти в каталог
          </a>
          <a href="/contacts" class="px-8 py-4 bg-transparent border-2 border-white text-white rounded-xl hover:bg-white hover:text-[#118568] transition-all duration-300 font-bold text-lg">
            Связаться с нами
          </a>
        </div>
      </div>
    </div>
    
    <!-- Анимированные элементы -->

  </section>

  <!-- Популярные товары -->
  <section class="py-16 bg-gradient-to-b from-white to-[#DEE5E5]">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="text-center mb-16">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">Популярные товары</h2>
        <p class="text-xl text-gray-600 max-w-2xl mx-auto">Товары пользующиеся большим спросом</p>
        <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
        <?php foreach ($popularProducts as $product): ?>
          <a href="/service?id=<?php echo $product['id']; ?>" class="group">
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
                     class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-500">
                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <!-- Популярный бейдж -->
                <div class="absolute top-4 left-4">
                  <span class="px-3 py-1 bg-[#17B890] text-white text-xs font-bold rounded-full">
                    ПОПУЛЯРНОЕ
                  </span>
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
                      <span class="text-lg">от </span>
                      <?php echo number_format($product['base_price'], 0, '', ' '); ?> 
                      <span class="text-lg">руб.</span>
                    </div>
                    
                    <div class="w-10 h-10 bg-[#118568] rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-x-2 group-hover:translate-x-0">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                      </svg>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="text-center mt-12">
        <a href="/catalog" class="inline-block px-8 py-4 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Смотреть весь каталог
        </a>
      </div>
    </div>
  </section>

  <!-- О компании -->
  <section class="py-20 bg-white">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
        <div class="order-2 lg:order-1">
          <div class="relative">
            <div class="absolute -top-6 -left-6 w-full h-full bg-[#17B890] rounded-2xl"></div>
            <img src="/assets/images/about.jpg" alt="О компании" class="relative w-full h-96 object-cover rounded-2xl shadow-2xl">
            
            <!-- Статистика -->
            <div class="absolute -bottom-6 -right-4 bg-white rounded-2xl shadow-xl p-6 w-64">
              <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                  <div class="text-2xl font-bold text-[#118568]">100+</div>
                  <div class="text-sm text-gray-600">Проектов</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-[#17B890]">4+</div>
                  <div class="text-sm text-gray-600">Лет опыта</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="order-1 lg:order-2">
          <div class="mb-6">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-6">О компании</h2>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full"></div>
          </div>
          
          <p class="text-lg text-gray-600 leading-relaxed mb-8">
            Мы — молодая, быстро развивающаяся рекламно-производственная компания. 
            Наша цель — предоставить клиентам высококачественные печатные материалы и услуги, 
            которые соответствуют самым высоким стандартам.
          </p>
          
          <p class="text-lg text-gray-600 leading-relaxed mb-10">
            Каждый проект мы рассматриваем как возможность создать что-то уникальное и запоминающееся. 
          </p>
          
          <div class="space-y-4 mb-10">
            <div class="flex items-center">
              <div class="w-8 h-8 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <span class="text-gray-700">Качественные материалы</span>
            </div>
            <div class="flex items-center">
              <div class="w-8 h-8 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <span class="text-gray-700">Индивидуальный подход</span>
            </div>
            <div class="flex items-center">
              <div class="w-8 h-8 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <span class="text-gray-700">Гарантия качества</span>
            </div>
          </div>
          
          <a href="#" class="inline-block px-8 py-4 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Узнать больше о нас
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Почему выбирают нас -->
  <section class="py-20 bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB]">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="text-center mb-16">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">Почему выбирают нас?</h2>
        <p class="text-xl text-gray-700 max-w-3xl mx-auto">Мы гордимся тем, что делаем свою работу на довольно высоком уровне</p>
        <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div class="bg-white rounded-2xl shadow-xl p-8 transform transition-all duration-300 hover:shadow-2xl hover:-translate-y-2">
          <div class="w-16 h-16 bg-[#118568] rounded-2xl flex items-center justify-center mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Высокое качество</h3>
          <p class="text-gray-600 leading-relaxed">
            Мы используем только качественные материалы для достижения безупречного результата.
          </p>
        </div>
        
        <div class="bg-white rounded-2xl shadow-xl p-8 transform transition-all duration-300 hover:shadow-2xl hover:-translate-y-2">
          <div class="w-16 h-16 bg-[#17B890] rounded-2xl flex items-center justify-center mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Скорость выполнения</h3>
          <p class="text-gray-600 leading-relaxed">
            Быстрое выполнение заказов без потери качества. Соблюдаем сроки и учитываем срочность.
          </p>
        </div>
        
        <div class="bg-white rounded-2xl shadow-xl p-8 transform transition-all duration-300 hover:shadow-2xl hover:-translate-y-2">
          <div class="w-16 h-16 bg-[#5E807F] rounded-2xl flex items-center justify-center mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Индивидуальный подход</h3>
          <p class="text-gray-600 leading-relaxed">
            Каждый клиент получает персонализированное обслуживание и внимание к деталям. Подстроимся под ваш бюджет.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Партнеры -->
  <section class="py-20 bg-white">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="text-center mb-16">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">Наши партнеры</h2>
        <p class="text-xl text-gray-600 max-w-2xl mx-auto">С нами работают лучшие компании и бренды</p>
        <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
      </div>
      
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-8">
        <?php if (!empty($partners)): ?>
          <?php foreach ($partners as $partner): ?>
            <div class="flex justify-center items-center p-6 bg-[#DEE5E5] rounded-2xl hover:bg-[#9DC5BB] transition-colors duration-300 transform hover:scale-105">
              <img src="<?php echo htmlspecialchars($partner['logo_url']); ?>" 
                   alt="<?php echo htmlspecialchars($partner['name']); ?>" 
                   class="h-12 max-w-full">
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="flex justify-center items-center p-6 bg-[#DEE5E5] rounded-2xl">
              <div class="h-12 w-32 bg-gray-300 rounded-lg"></div>
            </div>
          <?php endfor; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- CTA секция -->
  <section class="py-20 bg-gradient-to-r from-[#118568] to-[#17B890]">
    <div class="container mx-auto px-4 max-w-4xl text-center">
      <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">Готовы начать проект?</h2>
      <p class="text-xl text-white/90 mb-10 max-w-2xl mx-auto">
        Свяжитесь с нами сегодня и получите бесплатную консультацию по вашему проекту
      </p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="/contacts" class="px-8 py-4 bg-white text-[#118568] rounded-xl hover:bg-gray-100 transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Связаться с нами
        </a>
        <a href="/catalog" class="px-8 py-4 bg-transparent border-2 border-white text-white rounded-xl hover:bg-white hover:text-[#118568] transition-all duration-300 font-bold text-lg">
          Посмотреть каталог
        </a>
      </div>
    </div>
  </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>