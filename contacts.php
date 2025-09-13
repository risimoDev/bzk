<?php
require_once __DIR__ . '/includes/session.php';
$pageTitle = "Контакты";
require_once __DIR__ . '/includes/db.php';

// Сообщения для уведомлений
$success_message = $error_message = null;

function verify_turnstile($token)
{
  $secret = $_ENV['CLOUDFLARE_TURNSTILE_SECRET_KEY']; // ⚡ вставь свой ключ
  $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

  $data = [
    "secret" => $secret,
    "response" => $token,
    "remoteip" => $_SERVER['REMOTE_ADDR']
  ];

  $options = [
    "http" => [
      "header" => "Content-type: application/x-www-form-urlencoded\r\n",
      "method" => "POST",
      "content" => http_build_query($data)
    ]
  ];
  $context = stream_context_create($options);
  $result = file_get_contents($url, false, $context);

  return json_decode($result, true);
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone'] ?? '');
  $preferred_contact = $_POST['preferred_contact'] ?? 'email';
  $message = trim($_POST['message']);
  $agreement = isset($_POST['agreement']) ? 1 : 0;

  $token = $_POST['cf-turnstile-response'] ?? '';
  $captcha = verify_turnstile($token);

  if (!$captcha['success']) {
    $error_message = "Проверка безопасности не пройдена!";
  } else {
    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && $message && $agreement) {
      try {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, preferred_contact, message, status, created_at) 
                         VALUES (?, ?, ?, ?, ?, 'new', NOW())");
        $stmt->execute([$name, $email, $phone, $preferred_contact, $message]);

        // Отправка email администратору (оставим как было)
        $to = 'bzkprint@yandex.ru';
        $subject = 'Новое сообщение с сайта bzk print';
        $body = "Имя: $name\nEmail: $email\nТелефон: $phone\nСвязь: $preferred_contact\nСообщение: $message";
        mail($to, $subject, $body);

        $success_message = "Ваше сообщение успешно отправлено!";
      } catch (Exception $e) {
        error_log("Ошибка записи сообщения: " . $e->getMessage());
        $error_message = "Ошибка при отправке. Попробуйте ещё раз.";
      }
    } else {
      $error_message = "Заполните все обязательные поля и согласитесь с обработкой данных.";
    }
  }
}
?>
<?php include_once __DIR__ . '/includes/header.php'; ?>
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
      <p class="text-xl text-gray-700 max-w-2xl mx-auto">У вас есть вопросы? Мы всегда рады помочь! Заполните форму ниже
        или свяжитесь с нами удобным для вас способом.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-16">
      <!-- Форма обратной связи -->
      <!-- Форма -->
      <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="mb-6">
          <h2 class="text-3xl font-bold text-gray-800 mb-2">Отправить сообщение</h2>
          <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full"></div>
        </div>

        <?php if ($success_message): ?>
          <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <?php echo $success_message; ?>
          </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
          <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg"><?php echo $error_message; ?>
          </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6">
          <div>
            <label for="name" class="block text-gray-700 font-medium mb-2 text-lg">Ваше имя *</label>
            <input type="text" id="name" name="name" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg"
              required>
          </div>

          <div>
            <label for="email" class="block text-gray-700 font-medium mb-2 text-lg">Email *</label>
            <input type="email" id="email" name="email" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg"
              required>
          </div>

          <div>
            <label for="phone" class="block text-gray-700 font-medium mb-2 text-lg">Телефон</label>
            <input type="text" id="phone" name="phone" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg">
          </div>

          <div>
            <label for="preferred_contact" class="block text-gray-700 font-medium mb-2 text-lg">Предпочтительный способ
              связи</label>
            <select id="preferred_contact" name="preferred_contact"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg">
              <option value="phone">По телефону</option>
              <option value="telegram">Telegram</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="email" selected>Email</option>
            </select>
          </div>

          <div>
            <label for="message" class="block text-gray-700 font-medium mb-2 text-lg">Ваше сообщение *</label>
            <textarea id="message" name="message" rows="5" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg"
              required></textarea>
          </div>

          <div class="flex items-start">
            <input type="checkbox" id="agreement" name="agreement" class="mt-1 mr-2" required>
            <label for="agreement" class="text-gray-700 text-sm">Я согласен на обработку персональных данных</label>
          </div>
          <div class="cf-turnstile" data-sitekey="0x4AAAAAABzFgQHD_KaZTnsZ"></div>
          <button type="submit" name="submit"
            class="w-full bg-[#118568] text-white py-4 rounded-lg hover:bg-[#0f755a] transition font-bold text-lg">Отправить
            сообщение</button>
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
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-800">Телефон</h3>
                <p class="text-gray-700 text-xl font-medium">+7 (922) 304-04-65</p>
              </div>
            </div>

            <div class="flex items-start p-4 rounded-lg bg-[#9DC5BB] hover:bg-[#5E807F] transition-colors duration-300">
              <div class="flex-shrink-0 w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-800">Email</h3>
                <p class="text-gray-700 text-xl font-medium">bzkprint@yandex.ru</p>
              </div>
            </div>

            <div class="flex items-start p-4 rounded-lg bg-[#5E807F] hover:bg-[#17B890] transition-colors duration-300">
              <div class="flex-shrink-0 w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
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
        <div
          class="bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all duration-300 hover:shadow-2xl">
          <div class="h-80 w-full">
            <iframe
              src="https://yandex.ru/map-widget/v1/?um=constructor%3Abd2ca03f1c317bdfc7e12d46366794ced5b2771bf174068e0d80976394bdf1af&amp;source=constructor"
              width="978" height="720" frameborder="0"></iframe>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include_once __DIR__ . '/includes/footer.php'; ?>