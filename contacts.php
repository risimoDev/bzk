<?php
session_start();
$pageTitle = "Контакты";
include_once __DIR__ . '/includes/header.php';

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);

    // Отправка email администратору
    $to = 'bzkprint@yandex.ru';
    $subject = 'Новое сообщение с сайта bzk print';
    $body = "Имя: $name\nEmail: $email\nСообщение: $message";
    mail($to, $subject, $body);

    echo "<p class='text-green-600 text-center'>Ваше сообщение успешно отправлено!</p>";
}
?>

<main class="min-h-screen bg-pattern py-8">
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
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Свяжитесь с нами</h1>
      <p class="text-xl text-gray-700 max-w-2xl mx-auto">У вас есть вопросы? Мы всегда рады помочь! Заполните форму ниже или свяжитесь с нами удобным для вас способом.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-16">
      <!-- Форма обратной связи -->
      <div class="bg-white rounded-2xl shadow-xl p-8 transform transition-all duration-300 hover:shadow-2xl">
        <div class="mb-6">
          <h2 class="text-3xl font-bold text-gray-800 mb-2">Отправить сообщение</h2>
          <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full"></div>
        </div>

        <?php if (isset($success_message)): ?>
          <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <?php echo $success_message; ?>
          </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
          <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <?php echo $error_message; ?>
          </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6">
          <div>
            <label for="name" class="block text-gray-700 font-medium mb-2 text-lg">Ваше имя</label>
            <div class="relative">
              <input type="text" id="name" name="name" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" required>
              <div class="absolute left-4 top-3.5 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            </div>
          </div>

          <div>
            <label for="email" class="block text-gray-700 font-medium mb-2 text-lg">Email адрес</label>
            <div class="relative">
              <input type="email" id="email" name="email" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" required>
              <div class="absolute left-4 top-3.5 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
            </div>
          </div>

          <div>
            <label for="message" class="block text-gray-700 font-medium mb-2 text-lg">Ваше сообщение</label>
            <div class="relative">
              <textarea id="message" name="message" rows="5" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12 resize-none" required></textarea>
              <div class="absolute left-4 top-3.5 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
              </div>
            </div>
          </div>

          <button type="submit" name="submit" class="w-full bg-[#118568] text-white py-4 rounded-lg hover:bg-[#0f755a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Отправить сообщение
          </button>
        </form>
      </div>

      <!-- Контактная информация и карта -->
      <div class="space-y-8">
        <!-- Контактная информация -->
        <div class="bg-white rounded-2xl shadow-xl p-8 transform transition-all duration-300 hover:shadow-2xl">
          <div class="mb-6">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Контактная информация</h2>
            <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full"></div>
          </div>

          <div class="space-y-6">
            <div class="flex items-start p-4 rounded-lg bg-[#DEE5E5] hover:bg-[#9DC5BB] transition-colors duration-300">
              <div class="flex-shrink-0 w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-800">Телефон</h3>
                <p class="text-gray-700 text-xl font-medium">+7 (922) 304-04-65</p>
              </div>
            </div>

            <div class="flex items-start p-4 rounded-lg bg-[#9DC5BB] hover:bg-[#5E807F] transition-colors duration-300">
              <div class="flex-shrink-0 w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-800">Email</h3>
                <p class="text-gray-700 text-xl font-medium">bzkprint@yandex.ru</p>
              </div>
            </div>

            <div class="flex items-start p-4 rounded-lg bg-[#5E807F] hover:bg-[#17B890] transition-colors duration-300">
              <div class="flex-shrink-0 w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-800">Адрес</h3>
                <p class="text-gray-100 text-lg">г. Пермь, ул. Сухобруса, д. 27<br>Офис 101</p>
              </div>
            </div>
          </div>

          <!-- Часы работы -->
          <div class="mt-8 pt-6 border-t border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Часы работы</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div class="text-center p-3 bg-[#DEE5E5] rounded-lg">
                <div class="font-bold text-[#118568]">Пн-Пт</div>
                <div class="text-gray-700 text-sm">11:00 - 18:00</div>
              </div>
              <div class="text-center p-3 bg-[#9DC5BB] rounded-lg">
                <div class="font-bold text-[#17B890]">Сб</div>
                <div class="text-gray-700 text-sm">По заявкам</div>
              </div>
              <div class="text-center p-3 bg-[#5E807F] rounded-lg">
                <div class="font-bold text-white">Вс</div>
                <div class="text-white text-sm">по заявкам</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Карта -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all duration-300 hover:shadow-2xl">
          <div class="h-80 w-full">
            <iframe src="https://yandex.ru/map-widget/v1/?um=constructor%3Abd2ca03f1c317bdfc7e12d46366794ced5b2771bf174068e0d80976394bdf1af&amp;source=constructor" width="978" height="720" frameborder="0"></iframe>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include_once __DIR__ . '/includes/footer.php'; ?>