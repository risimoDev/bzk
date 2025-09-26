<?php
session_start();
$pageTitle = "Добавить поставщика";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');
include_once('../../includes/security.php');
include_once('../../includes/common.php');

// ---------- CSRF ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ---------- Обработка формы ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    // Получение данных из формы
    $name = trim($_POST['name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $service_cost = !empty($_POST['service_cost']) ? floatval($_POST['service_cost']) : null;
    $payment_terms = trim($_POST['payment_terms'] ?? '');
    $delivery_terms = trim($_POST['delivery_terms'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Валидация
    $errors = [];
    if (empty($name)) {
        $errors['name'] = 'Название поставщика обязательно для заполнения';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email адрес';
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors['website'] = 'Некорректный URL сайта';
    }
    
    // Если нет ошибок, сохраняем поставщика
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO suppliers 
                (name, contact_person, phone, email, website, address, service_cost, payment_terms, delivery_terms, notes, is_active) 
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $contact_person, $phone, $email, $website, $address,
                $service_cost, $payment_terms, $delivery_terms, $notes, $is_active
            ]);
            
            add_notification('success', 'Поставщик успешно добавлен.');
            header("Location: /admin/suppliers");
            exit();
        } catch (PDOException $e) {
            add_notification('error', 'Ошибка при добавлении поставщика: ' . $e->getMessage());
        }
    }
}
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- breadcrumbs + назад -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div><?php echo generateBreadcrumbs($pageTitle ?? ''); ?></div>
      <div class="flex gap-2">
        <?php echo backButton(); ?>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Добавить поставщика</h1>
      <p class="text-xl text-gray-700">Заполните информацию о новом поставщике</p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- уведомления -->
    <?php echo display_notifications(); ?>

    <!-- Форма добавления поставщика -->
    <div class="bg-white rounded-3xl shadow-2xl p-8 max-w-4xl mx-auto">
      <form method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Название поставщика -->
          <div class="md:col-span-2">
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Название поставщика *</label>
            <input 
              type="text" 
              id="name" 
              name="name" 
              value="<?php echo e($_POST['name'] ?? ''); ?>"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 <?php echo isset($errors['name']) ? 'border-red-500' : ''; ?>"
              required
            >
            <?php if (isset($errors['name'])): ?>
              <p class="mt-1 text-sm text-red-600"><?php echo $errors['name']; ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Контактное лицо -->
          <div>
            <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-2">Контактное лицо</label>
            <input 
              type="text" 
              id="contact_person" 
              name="contact_person" 
              value="<?php echo e($_POST['contact_person'] ?? ''); ?>"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
            >
          </div>
          
          <!-- Телефон -->
          <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Телефон</label>
            <input 
              type="text" 
              id="phone" 
              name="phone" 
              value="<?php echo e($_POST['phone'] ?? ''); ?>"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
            >
          </div>
          
          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
            <input 
              type="email" 
              id="email" 
              name="email" 
              value="<?php echo e($_POST['email'] ?? ''); ?>"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 <?php echo isset($errors['email']) ? 'border-red-500' : ''; ?>"
            >
            <?php if (isset($errors['email'])): ?>
              <p class="mt-1 text-sm text-red-600"><?php echo $errors['email']; ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Сайт -->
          <div>
            <label for="website" class="block text-sm font-medium text-gray-700 mb-2">Сайт</label>
            <input 
              type="url" 
              id="website" 
              name="website" 
              value="<?php echo e($_POST['website'] ?? ''); ?>"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 <?php echo isset($errors['website']) ? 'border-red-500' : ''; ?>"
            >
            <?php if (isset($errors['website'])): ?>
              <p class="mt-1 text-sm text-red-600"><?php echo $errors['website']; ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Стоимость услуг -->
          <div>
            <label for="service_cost" class="block text-sm font-medium text-gray-700 mb-2">Стоимость услуг (руб.)</label>
            <input 
              type="number" 
              id="service_cost" 
              name="service_cost" 
              step="0.01"
              min="0"
              value="<?php echo e($_POST['service_cost'] ?? ''); ?>"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
            >
          </div>
          
          <!-- Активность -->
          <div class="flex items-center pt-6">
            <input 
              type="checkbox" 
              id="is_active" 
              name="is_active" 
              <?php echo (!isset($_POST['is_active']) || $_POST['is_active']) ? 'checked' : ''; ?>
              class="h-5 w-5 text-[#118568] rounded focus:ring-[#118568] border-gray-300"
            >
            <label for="is_active" class="ml-2 block text-sm text-gray-700">Активен</label>
          </div>
        </div>
        
        <!-- Адрес -->
        <div>
          <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Адрес</label>
          <textarea 
            id="address" 
            name="address" 
            rows="3"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
          ><?php echo e($_POST['address'] ?? ''); ?></textarea>
        </div>
        
        <!-- Условия оплаты -->
        <div>
          <label for="payment_terms" class="block text-sm font-medium text-gray-700 mb-2">Условия оплаты</label>
          <textarea 
            id="payment_terms" 
            name="payment_terms" 
            rows="3"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
          ><?php echo e($_POST['payment_terms'] ?? ''); ?></textarea>
        </div>
        
        <!-- Условия доставки -->
        <div>
          <label for="delivery_terms" class="block text-sm font-medium text-gray-700 mb-2">Условия доставки</label>
          <textarea 
            id="delivery_terms" 
            name="delivery_terms" 
            rows="3"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
          ><?php echo e($_POST['delivery_terms'] ?? ''); ?></textarea>
        </div>
        
        <!-- Примечания -->
        <div>
          <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Примечания</label>
          <textarea 
            id="notes" 
            name="notes" 
            rows="3"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
          ><?php echo e($_POST['notes'] ?? ''); ?></textarea>
        </div>
        
        <!-- Кнопки -->
        <div class="flex justify-end space-x-4 pt-6">
          <a href="/admin/suppliers" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-300 font-medium">
            Отмена
          </a>
          <button type="submit" class="px-6 py-3 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-lg hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 font-medium">
            Добавить поставщика
          </button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include_once('../../includes/footer.php'); ?>