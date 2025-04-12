<?php
session_start();
$pageTitle = "Главная страница | Типография";
include_once __DIR__ . '/includes/header.php';

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Получение популярных товаров
$stmt = $pdo->query("
    SELECT id, name, base_price, image 
    FROM calculator_products 
    ORDER BY RAND() 
    LIMIT 6
");
$popular_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <!-- Приветственный блок -->
  <section class="text-center py-12 bg-white rounded-lg shadow-md mb-12">
    <h1 class="text-4xl font-bold text-gray-800 mb-4">Добро пожаловать в нашу типографию!</h1>
    <p class="text-xl text-gray-600 mb-6">Мы предлагаем широкий выбор полиграфической продукции высокого качества.</p>
    <a href="/catalog" class="px-6 py-3 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300">
      Перейти в каталог
    </a>
  </section>

  <!-- Популярные товары -->
  <section>
    <h2 class="text-3xl font-bold text-gray-800 text-center mb-6">Популярные товары</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($popular_products as $product): ?>
      <a href="/product?id=<?php echo $product['id']; ?>" class="block bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-48 object-cover">
        <div class="p-4">
          <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
          <p class="text-gray-700">от <?php echo htmlspecialchars($product['base_price']); ?> ₽</p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- О компании -->
  <section class="py-12">
    <h2 class="text-3xl font-bold text-gray-800 text-center mb-6">О нашей типографии</h2>
    <div class="flex flex-col md:flex-row items-center justify-between space-y-6 md:space-y-0 md:space-x-6">
      <img src="/assets/images/about.jpg" alt="О компании" class="w-full md:w-1/2 h-96 object-cover rounded-lg shadow-md">
      <div class="w-full md:w-1/2">
        <p class="text-gray-700 leading-relaxed">
          Мы работаем на рынке полиграфических услуг уже более 10 лет. Наша команда профессионалов готова воплотить любые ваши идеи в жизнь: от визиток до крупных рекламных баннеров. Мы используем современное оборудование и качественные материалы, чтобы гарантировать высокий результат.
        </p>
        <a href="/about" class="mt-4 inline-block px-6 py-3 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300">
          Узнать больше
        </a>
      </div>
    </div>
  </section>

  <!-- Почему выбирают нас -->
  <section class="py-12 bg-gray-100 rounded-lg shadow-md">
    <h2 class="text-3xl font-bold text-gray-800 text-center mb-6">Почему выбирают нас?</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-xl font-semibold text-gray-800 mt-4">Гарантия качества</h3>
        <p class="text-gray-600">Используем только проверенные<br> материалы и технологии.</p>
      </div>
      <div class="text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-xl font-semibold text-gray-800 mt-4">Скорость выполнения</h3>
        <p class="text-gray-600">Выполняем заказы точно в срок.</p>
      </div>
      <div class="text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-6a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <h3 class="text-xl font-semibold text-gray-800 mt-4">Доступные цены</h3>
        <p class="text-gray-600">Гибкая система скидок для постоянных клиентов.</p>
      </div>
      <div class="text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-litegreen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        <h3 class="text-xl font-semibold text-gray-800 mt-4">Индивидуальный подход</h3>
        <p class="text-gray-600">Учитываем все пожелания клиентов.</p>
      </div>
    </div>
  </section>

  <!-- Отзывы клиентов -->
  <section class="py-12">
    <h2 class="text-3xl font-bold text-gray-800 text-center mb-6">Отзывы наших клиентов</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div class="bg-white p-6 rounded-lg shadow-md">
        <p class="text-gray-700 leading-relaxed mb-4">Заказывал визитки — все сделали быстро и качественно. Спасибо!</p>
        <p class="font-semibold text-gray-800">— Иван Иванов</p>
      </div>
      <div class="bg-white p-6 rounded-lg shadow-md">
        <p class="text-gray-700 leading-relaxed mb-4">Отличная типография, всегда помогают с дизайном и печатью.</p>
        <p class="font-semibold text-gray-800">— Мария Петрова</p>
      </div>
      <div class="bg-white p-6 rounded-lg shadow-md">
        <p class="text-gray-700 leading-relaxed mb-4">Работаю с этой типографией уже несколько лет, всегда доволен.</p>
        <p class="font-semibold text-gray-800">— Алексей Сидоров</p>
      </div>
    </div>
  </section>

  <!-- Форма обратной связи -->
  <section class="py-12">
    <h2 class="text-3xl font-bold text-gray-800 text-center mb-6">Свяжитесь с нами</h2>
    <form action="/send-feedback" method="POST" class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md">
      <div class="mb-4">
        <label for="name" class="block text-gray-700 font-medium mb-2">Имя</label>
        <input type="text" id="name" name="name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
      </div>
      <div class="mb-4">
        <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
        <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
      </div>
      <div class="mb-4">
        <label for="message" class="block text-gray-700 font-medium mb-2">Сообщение</label>
        <textarea id="message" name="message" rows="4" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required></textarea>
      </div>
      <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
        Отправить сообщение
      </button>
    </form>
  </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>