<?php
session_start();
$pageTitle = "Управление скидками";
include_once('../includes/header.php');

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_discount') {
        $product_id = $_POST['product_id'];
        $discount_value = $_POST['discount_value'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        if ($product_id && is_numeric($discount_value) && $discount_value > 0 && $discount_value <= 100) {
            $stmt = $pdo->prepare("INSERT INTO discounts (product_id, discount_value, start_date, end_date) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$product_id, $discount_value, $start_date, $end_date]);
            
            if ($result) {
                $_SESSION['notifications'][] = [
                    'type' => 'success',
                    'message' => 'Скидка успешно добавлена.'
                ];
            } else {
                $_SESSION['notifications'][] = [
                    'type' => 'error',
                    'message' => 'Ошибка при добавлении скидки.'
                ];
            }
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Пожалуйста, заполните все поля корректно.'
            ];
        }
        
        header("Location: /admin/discounts");
        exit();
        
    } elseif ($action === 'add_promocode') {
        $code = trim($_POST['code']);
        $discount_type = $_POST['discount_type'];
        $discount_value = $_POST['discount_value'];
        $usage_limit = !empty($_POST['usage_limit']) ? $_POST['usage_limit'] : null;
        $start_date = !empty($_POST['promocode_start_date']) ? $_POST['promocode_start_date'] : null;
        $end_date = !empty($_POST['promocode_end_date']) ? $_POST['promocode_end_date'] : null;
        
        if (!empty($code) && is_numeric($discount_value) && $discount_value > 0) {
            // Проверка уникальности кода
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM promocodes WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['notifications'][] = [
                    'type' => 'error',
                    'message' => 'Промокод с таким кодом уже существует.'
                ];
            } else {
                $stmt = $pdo->prepare("INSERT INTO promocodes (code, discount_type, discount_value, usage_limit, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$code, $discount_type, $discount_value, $usage_limit, $start_date, $end_date]);
                
                if ($result) {
                    $_SESSION['notifications'][] = [
                        'type' => 'success',
                        'message' => 'Промокод успешно добавлен.'
                    ];
                } else {
                    $_SESSION['notifications'][] = [
                        'type' => 'error',
                        'message' => 'Ошибка при добавлении промокода.'
                    ];
                }
            }
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Пожалуйста, заполните все обязательные поля корректно.'
            ];
        }
        
        header("Location: /admin/discounts");
        exit();
        
    } elseif ($action === 'delete_discount') {
        $discount_id = $_POST['discount_id'];
        
        $stmt = $pdo->prepare("DELETE FROM discounts WHERE id = ?");
        $result = $stmt->execute([$discount_id]);
        
        if ($result) {
            $_SESSION['notifications'][] = [
                'type' => 'success',
                'message' => 'Скидка успешно удалена.'
            ];
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Ошибка при удалении скидки.'
            ];
        }
        
        header("Location: /admin/discounts");
        exit();
        
    } elseif ($action === 'delete_promocode') {
        $promocode_id = $_POST['promocode_id'];
        
        $stmt = $pdo->prepare("DELETE FROM promocodes WHERE id = ?");
        $result = $stmt->execute([$promocode_id]);
        
        if ($result) {
            $_SESSION['notifications'][] = [
                'type' => 'success',
                'message' => 'Промокод успешно удален.'
            ];
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Ошибка при удалении промокода.'
            ];
        }
        
        header("Location: /admin/discounts");
        exit();
        
    } elseif ($action === 'toggle_promocode') {
        $promocode_id = $_POST['promocode_id'];
        $is_active = $_POST['is_active'];
        
        $stmt = $pdo->prepare("UPDATE promocodes SET is_active = ? WHERE id = ?");
        $result = $stmt->execute([$is_active, $promocode_id]);
        
        if ($result) {
            $_SESSION['notifications'][] = [
                'type' => 'success',
                'message' => 'Статус промокода успешно изменен.'
            ];
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Ошибка при изменении статуса промокода.'
            ];
        }
        
        header("Location: /admin/discounts");
        exit();
    }
}

// Получение скидок
$stmt = $pdo->prepare("SELECT d.*, p.name AS product_name FROM discounts d JOIN products p ON d.product_id = p.id ORDER BY d.start_date DESC");
$stmt->execute();
$discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение промокодов
$stmt = $pdo->query("SELECT * FROM promocodes ORDER BY created_at DESC");
$promocodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение товаров для скидок
$stmt = $pdo->query("SELECT id, name FROM products ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$total_discounts = count($discounts);
$total_promocodes = count($promocodes);
$active_promocodes = array_filter($promocodes, function($p) { return $p['is_active'] == 1; });
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <?php echo backButton(); ?>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление скидками</h1>
      <p class="text-xl text-gray-700">Скидки на товары и промокоды</p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo $total_discounts; ?></div>
        <div class="text-gray-600">Скидок на товары</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#17B890] mb-2"><?php echo $total_promocodes; ?></div>
        <div class="text-gray-600">Промокодов</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#5E807F] mb-2"><?php echo count($active_promocodes); ?></div>
        <div class="text-gray-600">Активных</div>
      </div>
    </div>

    <!-- Форма добавления скидки -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Добавить скидку на товар</h2>
      
      <form action="" method="POST" class="space-y-6">
        <input type="hidden" name="action" value="add_discount">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <label for="product_id" class="block text-gray-700 font-medium mb-2">Товар *</label>
            <select id="product_id" name="product_id" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    required>
              <option value="">Выберите товар</option>
              <?php foreach ($products as $product): ?>
                <option value="<?php echo $product['id']; ?>">
                  <?php echo htmlspecialchars($product['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div>
            <label for="discount_value" class="block text-gray-700 font-medium mb-2">Размер скидки (%) *</label>
            <input type="number" step="0.01" id="discount_value" name="discount_value" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="0.00" min="0" max="100" required>
          </div>
          
          <div>
            <label for="start_date" class="block text-gray-700 font-medium mb-2">Дата начала *</label>
            <input type="date" id="start_date" name="start_date" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   required>
          </div>
          
          <div>
            <label for="end_date" class="block text-gray-700 font-medium mb-2">Дата окончания *</label>
            <input type="date" id="end_date" name="end_date" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   required>
          </div>
        </div>
        
        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Добавить скидку
        </button>
      </form>
    </div>

    <!-- Форма добавления промокода -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Создать промокод</h2>
      
      <form action="" method="POST" class="space-y-6">
        <input type="hidden" name="action" value="add_promocode">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <label for="code" class="block text-gray-700 font-medium mb-2">Код промокода *</label>
            <input type="text" id="code" name="code" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="Введите уникальный код" required>
          </div>
          
          <div>
            <label for="discount_type" class="block text-gray-700 font-medium mb-2">Тип скидки *</label>
            <select id="discount_type" name="discount_type" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    required>
              <option value="percentage">Процент</option>
              <option value="fixed">Фиксированная сумма</option>
            </select>
          </div>
          
          <div>
            <label for="discount_value" class="block text-gray-700 font-medium mb-2">Размер скидки *</label>
            <input type="number" step="0.01" id="discount_value" name="discount_value" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="0.00" min="0" required>
          </div>
          
          <div>
            <label for="usage_limit" class="block text-gray-700 font-medium mb-2">Лимит использований</label>
            <input type="number" id="usage_limit" name="usage_limit" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="Без ограничений" min="1">
          </div>
          
          <div>
            <label for="promocode_start_date" class="block text-gray-700 font-medium mb-2">Дата начала</label>
            <input type="datetime-local" id="promocode_start_date" name="promocode_start_date" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
          </div>
          
          <div>
            <label for="promocode_end_date" class="block text-gray-700 font-medium mb-2">Дата окончания</label>
            <input type="datetime-local" id="promocode_end_date" name="promocode_end_date" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
          </div>
        </div>
        
        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#17B890] to-[#118568] text-white py-4 rounded-xl hover:from-[#14a380] hover:to-[#0f755a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Создать промокод
        </button>
      </form>
    </div>

    <!-- Список скидок -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
      <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Скидки на товары</h2>
        <div class="text-gray-600">
          Найдено: <?php echo count($discounts); ?> <?php echo count($discounts) == 1 ? 'скидка' : (count($discounts) < 5 ? 'скидки' : 'скидок'); ?>
        </div>
      </div>
      
      <?php if (empty($discounts)): ?>
        <div class="text-center py-12">
          <div class="w-16 h-16 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <p class="text-gray-600">Скидки не найдены</p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($discounts as $discount): ?>
            <div class="bg-gray-50 rounded-2xl p-6 hover:bg-white hover:shadow-xl transition-all duration-300">
              <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                <div class="flex-grow">
                  <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($discount['product_name']); ?></h3>
                  <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center">
                      <span class="text-2xl font-bold text-[#118568]"><?php echo number_format($discount['discount_value'], 2, '.', ''); ?>%</span>
                    </div>
                    <div class="text-gray-600">
                      <span class="font-medium">Период:</span> 
                      <?php echo date('d.m.Y', strtotime($discount['start_date'])); ?> - 
                      <?php echo date('d.m.Y', strtotime($discount['end_date'])); ?>
                    </div>
                  </div>
                </div>
                
                <form action="" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить эту скидку?')" class="m-0">
                  <input type="hidden" name="action" value="delete_discount">
                  <input type="hidden" name="discount_id" value="<?php echo $discount['id']; ?>">
                  <button type="submit" 
                          class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 font-medium flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Удалить
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Список промокодов -->
    <div class="bg-white rounded-3xl shadow-2xl p-6">
      <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Промокоды</h2>
        <div class="text-gray-600">
          Найдено: <?php echo count($promocodes); ?> <?php echo count($promocodes) == 1 ? 'промокод' : (count($promocodes) < 5 ? 'промокода' : 'промокодов'); ?>
        </div>
      </div>
      
      <?php if (empty($promocodes)): ?>
        <div class="text-center py-12">
          <div class="w-16 h-16 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </div>
          <p class="text-gray-600">Промокоды не найдены</p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($promocodes as $promocode): ?>
            <div class="bg-gray-50 rounded-2xl p-6 hover:bg-white hover:shadow-xl transition-all duration-300">
              <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                <div class="flex-grow">
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($promocode['code']); ?></h3>
                    <span class="px-2 py-1 bg-<?php echo $promocode['is_active'] ? '[#17B890]' : '[#9DC5BB]'; ?> text-white text-xs rounded-full">
                      <?php echo $promocode['is_active'] ? 'Активен' : 'Неактивен'; ?>
                    </span>
                  </div>
                  
                  <div class="flex flex-wrap items-center gap-4 mb-3">
                    <div class="flex items-center">
                      <span class="text-2xl font-bold text-[#118568]">
                        <?php echo number_format($promocode['discount_value'], 2, '.', ''); ?>
                        <?php echo $promocode['discount_type'] === 'percentage' ? '%' : '₽'; ?>
                      </span>
                    </div>
                    
                    <?php if ($promocode['usage_limit']): ?>
                      <div class="text-gray-600">
                        <span class="font-medium">Использовано:</span> 
                        <?php echo $promocode['used_count']; ?> / <?php echo $promocode['usage_limit']; ?>
                      </div>
                    <?php endif; ?>
                    
                    <?php if ($promocode['start_date'] || $promocode['end_date']): ?>
                      <div class="text-gray-600">
                        <span class="font-medium">Период:</span> 
                        <?php echo $promocode['start_date'] ? date('d.m.Y H:i', strtotime($promocode['start_date'])) : '∞'; ?> - 
                        <?php echo $promocode['end_date'] ? date('d.m.Y H:i', strtotime($promocode['end_date'])) : '∞'; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-2">
                  <form action="" method="POST" class="m-0">
                    <input type="hidden" name="action" value="toggle_promocode">
                    <input type="hidden" name="promocode_id" value="<?php echo $promocode['id']; ?>">
                    <input type="hidden" name="is_active" value="<?php echo $promocode['is_active'] ? '0' : '1'; ?>">
                    <button type="submit" 
                            class="px-4 py-2 bg-<?php echo $promocode['is_active'] ? '[#9DC5BB]' : '[#17B890]'; ?> text-white rounded-lg hover:opacity-90 transition-opacity duration-300 font-medium text-sm">
                      <?php echo $promocode['is_active'] ? 'Деактивировать' : 'Активировать'; ?>
                    </button>
                  </form>
                  
                  <form action="" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить этот промокод?')" class="m-0">
                    <input type="hidden" name="action" value="delete_promocode">
                    <input type="hidden" name="promocode_id" value="<?php echo $promocode['id']; ?>">
                    <button type="submit" 
                            class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 font-medium text-sm flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                      Удалить
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>