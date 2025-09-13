<?php
session_start();
$pageTitle = "Регистрация";
// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

function verify_turnstile($token) {
    $secret = $_ENV['CLOUDFLARE_TURNSTILE_SECRET_KEY']; // ⚡ вставь свой ключ
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

    $data = [
        "secret" => $secret,
        "response" => $token,
        "remoteip" => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        "http" => [
            "header"  => "Content-type: application/x-www-form-urlencoded\r\n",
            "method"  => "POST",
            "content" => http_build_query($data)
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    return json_decode($result, true);
}

// Обработка регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $phone = htmlspecialchars($_POST['phone']);

    $token = $_POST['cf-turnstile-response'] ?? '';
    $captcha = verify_turnstile($token);

    if (!$captcha['success']) {
        $error_message = "Проверка безопасности не пройдена!";
    } else {
      // Проверка уникальности email
      $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
      $stmt->execute([$email]);
      if ($stmt->fetch()) {
          echo "<p class='text-red-600 text-center'>Пользователь с таким email уже зарегистрирован.</p>";
      } else {
          // Роль по умолчанию: user
          $role = 'user';
          $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
          $stmt->execute([$name, $email, $password, $phone, $role]);
          echo "<p class='text-green-600 text-center'>Вы успешно зарегистрировались!</p>";
      }
    }
}
?>


  <!-- Шапка -->
  <?php include_once __DIR__ . '/includes/header.php'; ?>

  <main class="min-h-screen from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
  <div class="container mx-auto px-4 max-w-4xl">
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

    <div class="flex flex-col lg:flex-row gap-12 items-center">
      <!-- Левая колонка с информацией -->
      <div class="w-full lg:w-1/2 text-center lg:text-left">
        <h1 class="text-4xl font-bold text-gray-800 mb-6">Создайте аккаунт</h1>
        <p class="text-xl text-gray-700 mb-8 leading-relaxed">
          Присоединяйтесь к нашему сообществу и получите доступ к эксклюзивным предложениям и персональным услугам.
        </p>
        
        <div class="space-y-6 mb-8">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-4 px-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">Персональные скидки</h3>
              <p class="text-gray-600 text-sm">Специальные предложения для постоянных клиентов</p>
            </div>
          </div>
          
          <div class="flex items-center">
            <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-4 px-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">История заказов</h3>
              <p class="text-gray-600 text-sm">Отслеживайте все ваши заказы в одном месте</p>
            </div>
          </div>
          
          <div class="flex items-center">
            <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mr-4 px-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">Безопасность данных</h3>
              <p class="text-gray-600 text-sm">Ваши персональные данные надежно защищены</p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-2xl p-6 shadow-lg">
          <p class="text-gray-700 mb-4">Уже есть аккаунт?</p>
          <a href="/login" class="lg:pl-2 inline-block w-full bg-[#DEE5E5] text-[#118568] py-3 rounded-lg hover:bg-[#9DC5BB] transition-all duration-300 font-medium">
            Войти в аккаунт
          </a>
        </div>
      </div>

      <!-- Правая колонка с формой -->
      <div class="w-full lg:w-1/2">
        <div class="bg-white rounded-3xl shadow-2xl p-8 transform transition-all duration-300 hover:shadow-3xl">
          <div class="text-center mb-8">
            <div class="w-16 h-16 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
              </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Регистрация</h2>
            <p class="text-gray-600">Заполните форму для создания аккаунта</p>
            <div class="w-12 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
          </div>

          <?php if (isset($error_message)): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
              <?php echo $error_message; ?>
            </div>
          <?php endif; ?>

          <?php if (isset($success_message)): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
              <?php echo $success_message; ?>
            </div>
          <?php endif; ?>

          <form action="" method="POST" class="space-y-6">
            <div>
              <label for="name" class="block text-gray-700 font-medium mb-2">Ваше имя</label>
              <div class="relative">
                <input type="text" id="name" name="name" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       required>
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
              </div>
            </div>

            <div>
              <label for="email" class="block text-gray-700 font-medium mb-2">Email адрес</label>
              <div class="relative">
                <input type="email" id="email" name="email" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       required>
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
            </div>

            <div>
              <label for="password" class="block text-gray-700 font-medium mb-2">Пароль</label>
              <div class="relative">
                <input type="password" id="password" name="password" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       required minlength="6">
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Минимум 6 символов</p>
            </div>

            <div>
              <label for="phone" class="block text-gray-700 font-medium mb-2">Номер телефона</label>
              <div class="relative">
                <input type="tel" id="phone" name="phone" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       placeholder="+7 (___) ___-__-__" required>
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                </div>
              </div>
            </div>

            <div class="flex items-start">
              <label class="flex items-start">
                <input type="checkbox" class="rounded border-gray-300 text-[#118568] focus:ring-[#17B890] mt-1" required>
                <span class="ml-2 text-gray-700 text-sm">
                  Я согласен с <a href="/terms" class="text-[#118568] hover:underline">условиями использования</a> 
                  и <a href="/privacy" class="text-[#118568] hover:underline">политикой конфиденциальности</a>
                </span>
              </label>
            </div>
            <div class="cf-turnstile" data-sitekey="0x4AAAAAABzFgQHD_KaZTnsZ"></div>
            <button type="submit" name="register" 
                    class="w-full bg-gradient-to-r from-[#17B890] to-[#118568] text-white py-4 rounded-lg hover:from-[#14a380] hover:to-[#0f755a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
              Создать аккаунт
            </button>
          </form>

        </div>
      </div>
    </div>
  </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    Inputmask({
        mask: "+7 (999) 999-99-99",
        showMaskOnHover: false,
        clearIncomplete: true // убирает недописанные номера при потере фокуса
    }).mask("#phone");
});
</script>

  <!-- Футер -->
  <?php include_once __DIR__ . '/includes/footer.php'; ?>
