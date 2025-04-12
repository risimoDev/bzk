<?php
session_start();
$pageTitle = "О нас | Типография";
include_once __DIR__ . '/includes/header.php';
?>

<main class="container mx-auto px-4 py-8">
  <!-- Описание компании -->
  <section class="mb-12">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">О нас</h1>
    <p class="text-lg text-gray-700 leading-relaxed text-center">
      Мы — современная типография с многолетним опытом в сфере полиграфии. Наша команда специалистов готова воплотить любые ваши идеи в жизнь: от визиток до сложных рекламных материалов.
    </p>
  </section>

  <!-- Фотографии команды -->
  <section class="mb-12">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Наша команда</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white p-4 rounded-lg shadow-md text-center">
        <img src="https://via.placeholder.com/150" alt="Иван Иванов" class="w-32 h-32 object-cover rounded-full mx-auto mb-4">
        <h3 class="text-xl font-semibold text-gray-800">Иван Иванов</h3>
        <p class="text-gray-600">Менеджер проектов</p>
      </div>
      <div class="bg-white p-4 rounded-lg shadow-md text-center">
        <img src="https://via.placeholder.com/150" alt="Мария Петрова" class="w-32 h-32 object-cover rounded-full mx-auto mb-4">
        <h3 class="text-xl font-semibold text-gray-800">Мария Петрова</h3>
        <p class="text-gray-600">Дизайнер</p>
      </div>
      <div class="bg-white p-4 rounded-lg shadow-md text-center">
        <img src="https://via.placeholder.com/150" alt="Алексей Сидоров" class="w-32 h-32 object-cover rounded-full mx-auto mb-4">
        <h3 class="text-xl font-semibold text-gray-800">Алексей Сидоров</h3>
        <p class="text-gray-600">Технолог</p>
      </div>
    </div>
  </section>

  <!-- Отзывы клиентов -->
  <section class="mb-12">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Отзывы клиентов</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white p-6 rounded-lg shadow-md">
        <p class="text-gray-700 leading-relaxed">«Заказывал визитки для своей компании. Все было выполнено быстро и качественно. Рекомендую!»</p>
        <p class="text-gray-600 mt-4">— Дмитрий, предприниматель</p>
      </div>
      <div class="bg-white p-6 rounded-lg shadow-md">
        <p class="text-gray-700 leading-relaxed">«Спасибо за помощь в разработке рекламных материалов! Команда профессионалов, всегда на связи.»</p>
        <p class="text-gray-600 mt-4">— Анна, маркетолог</p>
      </div>
    </div>
  </section>

  <!-- Контактная информация -->
  <section>
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Свяжитесь с нами</h2>
    <div class="text-center">
      <p class="text-gray-700 mb-2">Телефон: +7 (999) 123-45-67</p>
      <p class="text-gray-700 mb-2">Email: info@typography.ru</p>
      <p class="text-gray-700">Адрес: г. Москва, ул. Примерная, д. 10</p>
    </div>
  </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>