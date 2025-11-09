<?php

session_start();
$pageTitle = "Регистрация";
// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';
include_once __DIR__ . '/includes/telegram.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // если через Composer

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

/**
 * Send welcome email to new user
 */
function sendWelcomeEmail($userEmail, $userName) {
    global $_ENV;
    
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->CharSet = 'UTF-8';
        
        // Sender and recipient
        $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($userEmail, $userName);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Добро пожаловать в BZK Print!';
        $mail->Body = "
            <html>
            <head>
                <meta charset='UTF-8'>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='background: linear-gradient(135deg, #118568 0%, #17B890 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                        <h1 style='margin: 0; font-size: 28px;'>🎉 Добро пожаловать!</h1>
                        <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Спасибо за регистрацию в BZK Print</p>
                    </div>
                    <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #ddd; border-top: none;'>
                        <p style='margin-top: 0; font-size: 16px;'>Здравствуйте, <strong>" . htmlspecialchars($userName) . "</strong>!</p>
                        
                        <p>Поздравляем! Ваш аккаунт в типографии BZK Print успешно создан. Теперь вы можете:</p>
                        
                        <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #118568;'>
                            <ul style='margin: 0; padding-left: 20px;'>
                                <li style='margin-bottom: 8px;'>📋 Оформлять заказы онлайн</li>
                                <li style='margin-bottom: 8px;'>📊 Отслеживать статус ваших заказов</li>
                                <li style='margin-bottom: 8px;'>💰 Получать персональные скидки</li>
                                <li style='margin-bottom: 8px;'>📱 Управлять профилем и настройками</li>
                                <li style='margin-bottom: 8px;'>💬 Связываться с нашей службой поддержки</li>
                            </ul>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='https://" . $_SERVER['HTTP_HOST'] . "/client/dashboard' style='background: linear-gradient(135deg, #118568 0%, #17B890 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Перейти в личный кабинет</a>
                        </div>
                        
                        <div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                            <p style='margin: 0; font-size: 14px; color: #2d5a2d;'>
                                <strong>💡 Совет:</strong> Добавьте наш сайт в закладки для быстрого доступа к услугам типографии!
                            </p>
                        </div>
                        
                        <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                        <p style='font-size: 12px; color: #666; margin-bottom: 0; text-align: center;'>
                            С уважением,<br>
                            Команда типографии BZK Print<br>
                            <a href='https://" . $_SERVER['HTTP_HOST'] . "' style='color: #118568;'>bzkprint.ru</a> • <a href='tel:+71234567890' style='color: #118568;'>+7 (123) 456-78-90</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Добро пожаловать в BZK Print, " . $userName . "! Ваш аккаунт успешно создан. Теперь вы можете пользоваться всеми услугами нашей типографии.";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log('Welcome email error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send welcome Telegram notification
 */
function sendWelcomeTelegram($userName) {
    $telegram = getTelegramBot();
    
    // Send to admin group if configured
    $group_chat_id = $_ENV['TELEGRAM_GROUP_CHAT_ID'] ?? '';
    if (!empty($group_chat_id)) {
        $message = "🎉 <b>Новая регистрация!</b>\n\n";
        $message .= "👤 <b>Пользователь:</b> " . htmlspecialchars($userName) . "\n";
        $message .= "📅 <b>Дата:</b> " . date('d.m.Y H:i') . "\n\n";
        $message .= "🌐 <b>Сайт:</b> https://" . $_SERVER['HTTP_HOST'];
        
        return $telegram->sendMessage($group_chat_id, $message);
    }
    return false;
}

// Обработка регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log form submission
    error_log('Registration form submitted');
    
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email = trim(strtolower(htmlspecialchars($_POST['email'] ?? '')));
    $password = $_POST['password'] ?? '';
    $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));

    $token = $_POST['cf-turnstile-response'] ?? '';
    
    // Debug: Log received data
    error_log('Registration data: name=' . $name . ', email=' . $email . ', phone=' . $phone);
    
    // Verify turnstile (skip if no token for testing)
    if (!empty($token)) {
        $captcha = verify_turnstile($token);
    } else {
        // For testing - remove this in production
        $captcha = ['success' => true];
        error_log('Skipping captcha verification for testing');
    }

    // Additional validation
    $validation_errors = [];
    
    if (strlen($name) < 2) {
        $validation_errors[] = 'Имя должно содержать не менее 2 символов.';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = 'Пожалуйста, введите корректный email адрес.';
    }
    
    if (strlen($password) < 6) {
        $validation_errors[] = 'Пароль должен содержать не менее 6 символов.';
    }
    
    if (empty($phone)) {
        $validation_errors[] = 'Пожалуйста, введите номер телефона.';
    }

    if (!$captcha['success']) {
        $validation_errors[] = "Проверка безопасности не пройдена! Пожалуйста, попробуйте еще раз.";
    }
    
    if (!empty($validation_errors)) {
        $error_message = implode('<br>', $validation_errors);
        error_log('Validation errors: ' . $error_message);
    } else {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error_message = "Пользователь с таким email уже зарегистрирован. <a href='/login' class='underline text-blue-600'>Войти в аккаунт</a>";
        } else {
            try {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                // Default role: user
                $role = 'user';
                
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $password_hash, $phone, $role]);
                
                // Get the new user ID
                $user_id = $pdo->lastInsertId();
                
                // Automatic login after registration
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['role'] = $role;
                $_SESSION['is_authenticated'] = true;
                
                // Send welcome notifications (async - don't block registration if they fail)
                $welcome_email_sent = sendWelcomeEmail($email, $name);
                $welcome_telegram_sent = sendWelcomeTelegram($name);
                
                // Success notification
                $_SESSION['notifications'][] = [
                    'type' => 'success', 
                    'message' => 'Добро пожаловать! Регистрация прошла успешно, вы автоматически вошли в аккаунт.'
                ];
                
                // Add notification about email if sent
                if ($welcome_email_sent) {
                    $_SESSION['notifications'][] = [
                        'type' => 'info',
                        'message' => 'На ваш email отправлено приветственное письмо с полезной информацией.'
                    ];
                }
                
                error_log('Registration successful, redirecting to dashboard');
                // Redirect to user dashboard
                header("Location: /client/dashboard");
                exit();
                
            } catch (Exception $e) {
                error_log('Registration error: ' . $e->getMessage());
                $error_message = "Произошла ошибка при регистрации. Пожалуйста, попробуйте еще раз.";
            }
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
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg relative">
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <div><?php echo $error_message; ?></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if (isset($success_message)): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg relative">
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <div><?php echo $success_message; ?></div>
              </div>
            </div>
          <?php endif; ?>

          <form action="" method="POST" class="space-y-6">
            <div>
              <label for="name" class="block text-gray-700 font-medium mb-2">Ваше имя *</label>
              <div class="relative">
                <input type="text" id="name" name="name" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                       required minlength="2" maxlength="50">
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Минимум 2 символа</p>
            </div>

            <div>
              <label for="email" class="block text-gray-700 font-medium mb-2">Email адрес *</label>
              <div class="relative">
                <input type="email" id="email" name="email" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required maxlength="100">
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Используется для входа в аккаунт</p>
            </div>

            <div>
              <label for="password" class="block text-gray-700 font-medium mb-2">Пароль *</label>
              <div class="relative">
                <input type="password" id="password" name="password" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12 pr-12" 
                       required minlength="6" maxlength="100">
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <button type="button" id="togglePassword" class="absolute right-4 top-3.5 text-gray-400 hover:text-gray-600 transition-colors duration-200">
                  <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-1">Минимум 6 символов, используйте надежный пароль</p>
            </div>

            <div>
              <label for="phone" class="block text-gray-700 font-medium mb-2">Номер телефона *</label>
              <div class="relative">
                <input type="tel" id="phone" name="phone" 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 pl-12" 
                       placeholder="+7 (___) ___-__-__" 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                       required>
                <div class="absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Для связи по вопросам заказов</p>
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
            <button type="submit" name="register" id="registerBtn"
                    class="w-full bg-gradient-to-r from-[#17B890] to-[#118568] text-white py-4 rounded-lg hover:from-[#14a380] hover:to-[#0f755a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
              <span id="registerBtnText">Создать аккаунт</span>
              <span id="registerBtnLoading" class="hidden">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Создание аккаунта...
              </span>
            </button>
          </form>

        </div>
      </div>
    </div>
  </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Phone input mask
    Inputmask({
        mask: "+7 (999) 999-99-99",
        showMaskOnHover: false,
        clearIncomplete: true
    }).mask("#phone");
    
    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    togglePassword?.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        if (type === 'text') {
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
            `;
        } else {
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            `;
        }
    });
    
    // Form submission with loading state
    const form = document.querySelector('form');
    const registerBtn = document.getElementById('registerBtn');
    const registerBtnText = document.getElementById('registerBtnText');
    const registerBtnLoading = document.getElementById('registerBtnLoading');
    
    form?.addEventListener('submit', function(e) {
        // Show loading state
        registerBtn.disabled = true;
        registerBtnText.classList.add('hidden');
        registerBtnLoading.classList.remove('hidden');
        
        // Form validation
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const phone = document.getElementById('phone').value.trim();
        
        let isValid = true;
        
        if (name.length < 2) {
            e.preventDefault();
            isValid = false;
            showFieldError('name', 'Имя должно содержать не менее 2 символов');
        }
        
        if (!isValidEmail(email)) {
            e.preventDefault();
            isValid = false;
            showFieldError('email', 'Пожалуйста, введите корректный email');
        }
        
        if (password.length < 6) {
            e.preventDefault();
            isValid = false;
            showFieldError('password', 'Пароль должен содержать не менее 6 символов');
        }
        
        if (phone.length === 0) {
            e.preventDefault();
            isValid = false;
            showFieldError('phone', 'Пожалуйста, введите номер телефона');
        }
        
        if (!isValid) {
            // Reset button state if validation failed
            registerBtn.disabled = false;
            registerBtnText.classList.remove('hidden');
            registerBtnLoading.classList.add('hidden');
        }
    });
    
    // Helper functions
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        field.classList.add('border-red-500');
        field.classList.remove('border-gray-200');
        
        // Remove existing error message
        const existingError = field.parentNode.nextElementSibling;
        if (existingError && existingError.classList.contains('field-error')) {
            existingError.remove();
        }
        
        // Add new error message
        const errorDiv = document.createElement('p');
        errorDiv.className = 'field-error text-xs text-red-500 mt-1';
        errorDiv.textContent = message;
        field.parentNode.parentNode.appendChild(errorDiv);
        
        // Remove error styling after user starts typing
        field.addEventListener('input', function() {
            field.classList.remove('border-red-500');
            field.classList.add('border-gray-200');
            errorDiv.remove();
        }, { once: true });
    }
    
    // Real-time validation feedback
    const inputs = document.querySelectorAll('input[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            // Clear previous errors
            this.classList.remove('border-red-500');
            this.classList.add('border-gray-200');
            const errorMsg = this.parentNode.parentNode.querySelector('.field-error');
            if (errorMsg) errorMsg.remove();
        });
    });
    
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        switch(field.id) {
            case 'name':
                if (value.length < 2) {
                    isValid = false;
                    message = 'Имя должно содержать не менее 2 символов';
                }
                break;
            case 'email':
                if (!isValidEmail(value)) {
                    isValid = false;
                    message = 'Пожалуйста, введите корректный email';
                }
                break;
            case 'password':
                if (value.length < 6) {
                    isValid = false;
                    message = 'Пароль должен содержать не менее 6 символов';
                }
                break;
            case 'phone':
                if (value.length === 0) {
                    isValid = false;
                    message = 'Пожалуйста, введите номер телефона';
                }
                break;
        }
        
        if (!isValid) {
            showFieldError(field.id, message);
        } else {
            // Show success indicator
            field.classList.remove('border-red-500');
            field.classList.add('border-green-500');
            setTimeout(() => {
                field.classList.remove('border-green-500');
                field.classList.add('border-gray-200');
            }, 1000);
        }
    }
});
</script>

  <!-- Футер -->
  <?php include_once __DIR__ . '/includes/footer.php'; ?>
