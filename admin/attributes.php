<?php
session_start();
$pageTitle = "Управление характеристиками";
include_once('../includes/header.php');

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    die("Товар не выбран.");
}

// Получение информации о товаре
$stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Товар не найден.");
}

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add_attribute') {
            $name = trim($_POST['name']);
            $type = $_POST['type'];
            
            if (!empty($name) && in_array($type, ['radio', 'select', 'text'])) {
                // Добавление характеристики
                $stmt = $pdo->prepare("INSERT INTO product_attributes (product_id, name, type) VALUES (?, ?, ?)");
                $result = $stmt->execute([$product_id, $name, $type]);
                
                if ($result) {
                    $attribute_id = $pdo->lastInsertId();
                    
                    // Добавление значений характеристики (если тип не текстовый)
                    if ($type !== 'text' && isset($_POST['values'])) {
                        foreach ($_POST['values'] as $value_data) {
                            $value = trim($value_data['value']);
                            $price_modifier = $value_data['price_modifier'] ?? 0;
                            
                            if (!empty($value)) {
                                $stmt = $pdo->prepare("INSERT INTO attribute_values (attribute_id, value, price_modifier) VALUES (?, ?, ?)");
                                $stmt->execute([$attribute_id, $value, $price_modifier]);
                            }
                        }
                    }
                    
                    $_SESSION['notifications'][] = [
                        'type' => 'success',
                        'message' => 'Характеристика успешно добавлена.'
                    ];
                } else {
                    $_SESSION['notifications'][] = [
                        'type' => 'error',
                        'message' => 'Ошибка при добавлении характеристики.'
                    ];
                }
            } else {
                $_SESSION['notifications'][] = [
                    'type' => 'error',
                    'message' => 'Пожалуйста, заполните все обязательные поля корректно.'
                ];
            }
            
            header("Location: /admin/attributes?product_id=$product_id");
            exit();
            
        } elseif ($action === 'delete_attribute') {
            $attribute_id = $_POST['attribute_id'];
            
            // Проверяем, принадлежит ли характеристика текущему товару
            $stmt = $pdo->prepare("SELECT product_id FROM product_attributes WHERE id = ?");
            $stmt->execute([$attribute_id]);
            $attr = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($attr && $attr['product_id'] == $product_id) {
                // Удаляем характеристику (каскадное удаление значений)
                $stmt = $pdo->prepare("DELETE FROM product_attributes WHERE id = ?");
                $result = $stmt->execute([$attribute_id]);
                
                if ($result) {
                    $_SESSION['notifications'][] = [
                        'type' => 'success',
                        'message' => 'Характеристика успешно удалена.'
                    ];
                } else {
                    $_SESSION['notifications'][] = [
                        'type' => 'error',
                        'message' => 'Ошибка при удалении характеристики.'
                    ];
                }
            } else {
                $_SESSION['notifications'][] = [
                    'type' => 'error',
                    'message' => 'Недостаточно прав для удаления этой характеристики.'
                ];
            }
            
            header("Location: /admin/attributes?product_id=$product_id");
            exit();
        }
    }
}

// Получение характеристик
$stmt = $pdo->prepare("
    SELECT pa.*, COUNT(av.id) as values_count 
    FROM product_attributes pa 
    LEFT JOIN attribute_values av ON pa.id = av.attribute_id 
    WHERE pa.product_id = ? 
    GROUP BY pa.id 
    ORDER BY pa.id
");
$stmt->execute([$product_id]);
$attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$total_attributes = count($attributes);
$text_attributes = array_filter($attributes, function($attr) { return $attr['type'] === 'text'; });
$choice_attributes = array_filter($attributes, function($attr) { return $attr['type'] !== 'text'; });
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin/products" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">
          Все товары
        </a>
      </div>
    </div>

    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Характеристики товара</h1>
      <p class="text-xl text-gray-700"><?php echo htmlspecialchars($product['name']); ?></p>
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo $total_attributes; ?></div>
        <div class="text-gray-600">Всего характеристик</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#17B890] mb-2"><?php echo count($choice_attributes); ?></div>
        <div class="text-gray-600">Выборочные</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#5E807F] mb-2"><?php echo count($text_attributes); ?></div>
        <div class="text-gray-600">Текстовые</div>
      </div>
    </div>

    <!-- Форма добавления характеристики -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Добавить новую характеристику</h2>
      
      <form action="" method="POST" class="space-y-6">
        <input type="hidden" name="action" value="add_attribute">
        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <label for="name" class="block text-gray-700 font-medium mb-2">Название характеристики *</label>
            <input type="text" id="name" name="name" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="Введите название характеристики" required>
          </div>
          
          <div>
            <label for="type" class="block text-gray-700 font-medium mb-2">Тип характеристики *</label>
            <select id="type" name="type" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    onchange="toggleValuesSection(this.value)">
              <option value="radio">Radio кнопки</option>
              <option value="select">Выпадающий список</option>
              <option value="text">Текстовое поле</option>
            </select>
          </div>
        </div>
        
        <!-- Контейнер для значений -->
        <div id="values-section">
          <label class="block text-gray-700 font-medium mb-4">Значения характеристики:</label>
          
          <div id="values-container" class="space-y-4">
            <div class="value-item bg-gray-50 rounded-xl p-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-gray-600 text-sm mb-1">Значение *</label>
                  <input type="text" name="values[0][value]" 
                         class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568]"
                         placeholder="Введите значение" required>
                </div>
                <div>
                  <label class="block text-gray-600 text-sm mb-1">Модификатор цены (руб.)</label>
                  <input type="number" step="0.01" name="values[0][price_modifier]" 
                         class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568]"
                         placeholder="0.00" value="0">
                </div>
              </div>
            </div>
          </div>
          
          <button type="button" id="add-value" 
                  class="mt-4 px-4 py-2 bg-[#DEE5E5] text-[#118568] rounded-lg hover:bg-[#9DC5BB] transition-colors duration-300 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Добавить значение
          </button>
        </div>
        
        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Добавить характеристику
        </button>
      </form>
    </div>

    <!-- Список характеристик -->
    <?php if (empty($attributes)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Характеристики не найдены</h2>
        <p class="text-gray-600 mb-8">Добавьте первую характеристику, используя форму выше</p>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-3xl shadow-2xl p-6">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
          <h2 class="text-2xl font-bold text-gray-800">Список характеристик</h2>
          <div class="text-gray-600">
            Найдено: <?php echo count($attributes); ?> <?php echo count($attributes) == 1 ? 'характеристика' : (count($attributes) < 5 ? 'характеристики' : 'характеристик'); ?>
          </div>
        </div>
        
        <div class="space-y-4">
          <?php foreach ($attributes as $attribute): ?>
            <div class="bg-gray-50 rounded-2xl p-6 hover:bg-white hover:shadow-xl transition-all duration-300">
              <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                <div class="flex-grow">
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($attribute['name']); ?></h3>
                    <span class="px-2 py-1 bg-[#DEE5E5] text-gray-700 text-xs rounded-full capitalize">
                      <?php 
                      $type_names = [
                        'radio' => 'Radio',
                        'select' => 'Выпадающий список',
                        'text' => 'Текст'
                      ];
                      echo $type_names[$attribute['type']] ?? $attribute['type'];
                      ?>
                    </span>
                  </div>
                  
                  <?php if ($attribute['type'] !== 'text'): ?>
                    <div class="mt-3">
                      <div class="text-sm text-gray-600 mb-2">Значения (<?php echo $attribute['values_count']; ?>):</div>
                      <div class="flex flex-wrap gap-2">
                        <?php
                        $stmt_values = $pdo->prepare("SELECT value, price_modifier FROM attribute_values WHERE attribute_id = ? ORDER BY id");
                        $stmt_values->execute([$attribute['id']]);
                        $values = $stmt_values->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($values as $value): ?>
                          <span class="px-3 py-1 bg-white border border-[#17B890] text-[#17B890] text-sm rounded-full">
                            <?php echo htmlspecialchars($value['value']); ?>
                            <?php if ($value['price_modifier'] != 0): ?>
                              <span class="text-xs">
                                (<?php echo $value['price_modifier'] > 0 ? '+' : ''; ?><?php echo number_format($value['price_modifier'], 2, '.', ''); ?> руб.)
                              </span>
                            <?php endif; ?>
                          </span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="mt-3">
                      <div class="text-sm text-gray-600">Текстовое поле для ввода произвольного значения</div>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="flex gap-2">
                  <a href="/admin/attribute/edit?id=<?php echo $attribute['id']; ?>" 
                     class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm font-medium flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Редактировать
                  </a>
                  
                  <form action="" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить эту характеристику?')" class="m-0">
                    <input type="hidden" name="action" value="delete_attribute">
                    <input type="hidden" name="attribute_id" value="<?php echo $attribute['id']; ?>">
                    <button type="submit" 
                            class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 text-sm font-medium flex items-center">
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
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
  // Переключение видимости секции значений
  function toggleValuesSection(type) {
    const valuesSection = document.getElementById('values-section');
    if (type === 'text') {
      valuesSection.style.display = 'none';
    } else {
      valuesSection.style.display = 'block';
    }
  }
  
  // Добавление новых значений
  document.getElementById('add-value').addEventListener('click', function () {
    const container = document.getElementById('values-container');
    const index = container.children.length;
    
    const div = document.createElement('div');
    div.className = 'value-item bg-gray-50 rounded-xl p-4';
    div.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-gray-600 text-sm mb-1">Значение *</label>
          <input type="text" name="values[${index}][value]" 
                 class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568]"
                 placeholder="Введите значение" required>
        </div>
        <div>
          <label class="block text-gray-600 text-sm mb-1">Модификатор цены (руб.)</label>
          <input type="number" step="0.01" name="values[${index}][price_modifier]" 
                 class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568]"
                 placeholder="0.00" value="0">
        </div>
      </div>
      <button type="button" class="mt-2 text-red-500 hover:text-red-700 text-sm remove-value flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Удалить значение
      </button>
    `;
    container.appendChild(div);
    
    // Добавляем обработчик удаления для новой кнопки
    div.querySelector('.remove-value').addEventListener('click', function() {
      div.remove();
    });
  });
  
  // Добавляем обработчики удаления для существующих кнопок
  document.querySelectorAll('.remove-value').forEach(button => {
    button.addEventListener('click', function() {
      this.closest('.value-item').remove();
    });
  });
  
  // Инициализация при загрузке страницы
  document.addEventListener('DOMContentLoaded', function() {
    toggleValuesSection(document.getElementById('type').value);
  });
</script>

<?php include_once('../includes/footer.php'); ?>