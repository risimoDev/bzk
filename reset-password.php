<?php
session_start();
$pageTitle = "Сброс пароля";

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';
include_once __DIR__ . '/includes/security.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  
  $token = $_POST['token'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  // Валидация
  if (empty($password)) {
    $error_message = 'Пожалуйста, введите новый пароль.';
  } elseif (strlen($password) < 6) {
    $error_message = 'Пароль должен содержать минимум 6 символов.';
  } elseif ($password !== $confirm_password) {
    $error_message = 'Пароли не совпадают.';
  } else {
    // Проверка токена и его срока действия
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW() AND is_blocked = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
      // Обновление пароля
      $hashed_password = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
      $stmt->execute([$hashed_password, $user['id']]);

      $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Пароль успешно изменен! Теперь вы можете войти с новым паролем.'];
      header("Location: /login");
      exit();
    } else {
      $error_message = 'Недействительный или истекший токен. Попробуйте запросить сброс пароля заново.';
    }
  }
}

// Проверка токена из GET-параметра
$token = $_GET['token'] ?? $_POST['token'] ?? null;

if (!$token) {
  header("Location: /404");
  exit();
}

// Проверка действительности токена
$stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW() AND is_blocked = 0");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Недействительная ссылка для сброса пароля. Попробуйте запросить новую.'];
  header("Location: /forgot-password");
  exit();
}
?>

<?php include_once __DIR__ . '/includes/header.php'; ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
  <div class="container mx-auto px-6 max-w-md">

    <!-- Заголовок -->
    <div class="text-center mb-8">
      <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Сброс пароля</h1>
      <p class="text-lg text-gray-700">Создайте новый надежный пароль</p>
      <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php if ($error_message): ?>
      <div class="mb-6 p-4 rounded-xl bg-red-100 border border-red-400 text-red-700">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
      <div class="mb-6 p-4 rounded-xl bg-green-100 border border-green-400 text-green-700">
        <?php echo htmlspecialchars($success_message); ?>
      </div>
    <?php endif; ?>

    <!-- Форма -->
    <div class="bg-white rounded-3xl shadow-xl p-8">
      <form method="POST" class="space-y-6" id="reset-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div>
          <label for="password" class="block text-gray-700 font-medium mb-2">Новый пароль</label>
          <div class="relative">
            <input type="password" id="password" name="password"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition pr-12"
              placeholder="Минимум 6 символов" minlength="6" required>
            <button type="button"
              class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
              onclick="togglePassword('password')">
              <span id="password-icon">👁️</span>
            </button>
          </div>
          <div class="mt-2">
            <div class="h-2 bg-gray-200 rounded">
              <div id="password-strength" class="h-full rounded transition-all duration-300"></div>
            </div>
            <p id="password-feedback" class="text-sm mt-1"></p>
          </div>
        </div>

        <div>
          <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Подтвердите пароль</label>
          <div class="relative">
            <input type="password" id="confirm_password" name="confirm_password"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition pr-12"
              placeholder="Повторите пароль" required>
            <button type="button"
              class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
              onclick="togglePassword('confirm_password')">
              <span id="confirm_password-icon">👁️</span>
            </button>
          </div>
          <p id="match-feedback" class="text-sm mt-1"></p>
        </div>

        <button type="submit" id="submit-btn"
          class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:scale-105 transition font-bold text-lg shadow disabled:opacity-50 disabled:cursor-not-allowed"
          disabled>
          Сохранить новый пароль
        </button>
      </form>

      <div class="mt-6 text-center">
        <a href="/login" class="text-[#118568] hover:text-[#0f755a] font-medium">← Вернуться ко входу</a>
      </div>
    </div>

    <!-- Требования к паролю -->
    <div class="mt-8 bg-blue-50 rounded-2xl p-6">
      <h3 class="text-lg font-bold text-blue-800 mb-3">Требования к паролю</h3>
      <ul class="text-blue-700 space-y-2">
        <li class="flex items-start">
          <span class="text-blue-500 mr-2">✓</span>
          Минимум 6 символов
        </li>
        <li class="flex items-start">
          <span class="text-blue-500 mr-2">✓</span>
          Рекомендуем использовать буквы, цифры и спецсимволы
        </li>
        <li class="flex items-start">
          <span class="text-blue-500 mr-2">✓</span>
          Пароль должен быть уникальным
        </li>
      </ul>
    </div>
  </div>
</main>

<script>
  function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');

    if (field.type === 'password') {
      field.type = 'text';
      icon.textContent = '🙈';
    } else {
      field.type = 'password';
      icon.textContent = '👁️';
    }
  }

  function checkPasswordStrength(password) {
    let strength = 0;
    let feedback = '';

    if (password.length >= 6) strength += 1;
    if (password.length >= 8) strength += 1;
    if (/[a-z]/.test(password)) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^\w\s]/.test(password)) strength += 1;

    const strengthBar = document.getElementById('password-strength');
    const feedbackEl = document.getElementById('password-feedback');

    if (strength <= 2) {
      strengthBar.className = 'h-full rounded transition-all duration-300 bg-red-500';
      strengthBar.style.width = '33%';
      feedback = 'Слабый пароль';
      feedbackEl.className = 'text-sm mt-1 text-red-600';
    } else if (strength <= 4) {
      strengthBar.className = 'h-full rounded transition-all duration-300 bg-yellow-500';
      strengthBar.style.width = '66%';
      feedback = 'Средний пароль';
      feedbackEl.className = 'text-sm mt-1 text-yellow-600';
    } else {
      strengthBar.className = 'h-full rounded transition-all duration-300 bg-green-500';
      strengthBar.style.width = '100%';
      feedback = 'Надежный пароль';
      feedbackEl.className = 'text-sm mt-1 text-green-600';
    }

    feedbackEl.textContent = feedback;
    return strength;
  }

  function validateForm() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const submitBtn = document.getElementById('submit-btn');
    const matchFeedback = document.getElementById('match-feedback');

    let isValid = true;

    // Проверка совпадения паролей
    if (confirmPassword) {
      if (password === confirmPassword) {
        matchFeedback.textContent = 'Пароли совпадают';
        matchFeedback.className = 'text-sm mt-1 text-green-600';
      } else {
        matchFeedback.textContent = 'Пароли не совпадают';
        matchFeedback.className = 'text-sm mt-1 text-red-600';
        isValid = false;
      }
    } else {
      matchFeedback.textContent = '';
    }

    // Проверка длины пароля
    if (password.length < 6) {
      isValid = false;
    }

    submitBtn.disabled = !isValid || !password || !confirmPassword;
  }

  document.addEventListener('DOMContentLoaded', function () {
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');

    passwordField.addEventListener('input', function () {
      checkPasswordStrength(this.value);
      validateForm();
    });

    confirmPasswordField.addEventListener('input', validateForm);

    // Проверка при отправке формы
    document.getElementById('reset-form').addEventListener('submit', function (e) {
      const password = passwordField.value;
      const confirmPassword = confirmPasswordField.value;

      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Пароли не совпадают!');
        return false;
      }

      if (password.length < 6) {
        e.preventDefault();
        alert('Пароль должен содержать минимум 6 символов!');
        return false;
      }
    });
  });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>