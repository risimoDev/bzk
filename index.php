<?php
session_start();
$pageTitle = "Главная страница | Типография";
include_once __DIR__ . '/includes/header.php';

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

<main class="font-sans bg-litegray">
  <!-- Баннер -->
  <section class="bg-emerald text-white py-20">
    <div class="container mx-auto px-4 text-center">
      <h1 class="text-5xl font-bold mb-4">Профессиональная типография</h1>
      <p class="text-xl mb-8">Высокое качество печати и индивидуальный подход к каждому клиенту.</p>
      <a href="/catalog" class="px-6 py-3 bg-litegreen rounded-lg hover:bg-green-600 transition duration-300">
        Перейти в каталог
      </a>
    </div>
  </section>

  <!-- Популярные товары -->
  <section class="py-16">
    <div class="container mx-auto px-4">
      <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">Популярные товары</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($popularProducts as $product): ?>
          <a href="/service?id=<?php echo $product['id']; ?>">
          <div class="bg-white p-6 rounded-lg shadow-md">
            <?php
            // Получаем главное изображение товара
            $main_image = getProductMainImage($pdo, $product['id']);
            $image_url = $main_image ?: '/assets/images/no-image.webp'; // Заглушка, если изображение отсутствует
            ?>
            <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-60 object-cover rounded-t-lg">
            <h3 class="text-xl font-bold text-gray-800 mt-4"><?php echo htmlspecialchars($product['name']); ?></h3>
            <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($product['description']); ?></p>
            <p class="text-lg font-semibold text-green-600 mt-4"><?php echo htmlspecialchars($product['base_price']); ?> руб.</p>
          </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- О компании -->
  <section class="py-16 bg-white">
    <div class="container mx-auto px-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
        <div>
          <h2 class="text-3xl font-bold text-gray-800 mb-4">О компании</h2>
          <p class="text-gray-600 leading-relaxed">
            Мы — молодая, быстро развивающаяся типография. Наша цель — предоставить клиентам высококачественные печатные материалы и услуги, которые соответствуют стандартам.
          </p>
          <a href="/about" class="inline-block mt-6 px-6 py-3 bg-litegreen text-white rounded-lg hover:bg-green-600 transition duration-300">
            Узнать больше
          </a>
        </div>
        <div class="flex justify-center">
          <img src="/assets/images/about.jpg" alt="О компании" class="w-full h-64 object-cover rounded-lg">
        </div>
      </div>
    </div>
  </section>

  <!-- Почему выбирают нас -->
  <section class="py-16">
    <div class="container mx-auto px-4">
      <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">Почему выбирают нас?</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div class="text-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h3 class="text-xl font-bold text-gray-800 mt-4">Высокое качество</h3>
          <p class="text-gray-600 mt-2">Мы гарантируем высокое качество всех наших услуг.</p>
        </div>
        <div class="text-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h3 class="text-xl font-bold text-gray-800 mt-4">Скорость выполнения</h3>
          <p class="text-gray-600 mt-2">Быстрое выполнение заказов без потери качества.</p>
        </div>
        <div class="text-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <h3 class="text-xl font-bold text-gray-800 mt-4">Индивидуальный подход</h3>
          <p class="text-gray-600 mt-2">Каждый клиент получает персонализированное обслуживание.</p>
        </div>
      </div>
    </div>
  </section>

<!-- Партнеры -->
<section class="py-16 bg-litegray">
  <div class="container mx-auto px-4">
    <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">Наши партнеры</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8">
      <?php foreach ($partners as $partner): ?>
        <div class="flex justify-center items-center">
          <img src="<?php echo htmlspecialchars($partner['logo_url']); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>" class="h-12">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>