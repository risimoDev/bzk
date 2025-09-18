<?php
// Включаем отладку
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$pageTitle = "Добавить внешний заказ";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';
require_once '../buhgalt/functions.php';

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = trim($_POST['client_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $description = trim($_POST['description']);
    $status = $_POST['status'] ?? 'unpaid';

    $products = $_POST['products'] ?? []; // [product_id => quantity]
    $custom_items = $_POST['custom_items'] ?? []; // Custom positions
    $income = floatval($_POST['income']);

    if (!empty($client_name) && $income > 0) {
        try {
            $pdo->beginTransaction();

            // 1. Создаём заказ в external_orders
            $stmt = $pdo->prepare("INSERT INTO external_orders (client_name, email, phone, address, description, status, total_price) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$client_name, $email, $phone, $address, $description, $status, $income]);
            $external_order_id = $pdo->lastInsertId();

            // 2. Товары из каталога
            foreach ($products as $product_id => $quantity) {
                if ($quantity > 0) {
                    $stmt = $pdo->prepare("SELECT base_price FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $price = $stmt->fetchColumn() ?: 0;

                    $stmt = $pdo->prepare("INSERT INTO external_order_items (external_order_id, product_id, is_custom, quantity, price, expense_amount) 
                                           VALUES (?, ?, 0, ?, ?, 0)");
                    $stmt->execute([$external_order_id, $product_id, $quantity, $price * $quantity]);
                }
            }

            // 3. Пользовательские позиции
            foreach ($custom_items as $index => $item) {
                $name = trim($item['name'] ?? '');
                $description = trim($item['description'] ?? '');
                $quantity = intval($item['quantity'] ?? 0);
                $unit_price = floatval($item['unit_price'] ?? 0);
                $expense = floatval($item['expense'] ?? 0);
                
                if (!empty($name) && $quantity > 0 && $unit_price > 0) {
                    $total_price = $quantity * $unit_price;
                    $total_expense = $quantity * $expense;
                    
                    $stmt = $pdo->prepare("INSERT INTO external_order_items 
                        (external_order_id, product_id, is_custom, item_name, item_description, quantity, price, expense_amount) 
                        VALUES (?, NULL, 1, ?, ?, ?, ?, ?)");
                    $stmt->execute([$external_order_id, $name, $description, $quantity, $total_price, $total_expense]);
                }
            }

            // 4. Бухгалтерия
            $stmt = $pdo->prepare("
                INSERT INTO orders_accounting 
                (source, external_order_id, client_name, income, total_expense, estimated_expense, status, tax_amount)
                VALUES ('external', ?, ?, ?, 0, 0, ?, 0)
            ");
            $stmt->execute([$external_order_id, $client_name, $income, $status]);
            $order_accounting_id = $pdo->lastInsertId();

            // 5. Автоматический расчет расходов для товаров из каталога
            $estimated_expense = calculate_estimated_expense($pdo, $external_order_id, 'external');
            
            // Добавляем расходы от пользовательских позиций
            $stmt = $pdo->prepare("SELECT SUM(expense_amount) FROM external_order_items WHERE external_order_id = ? AND is_custom = 1");
            $stmt->execute([$external_order_id]);
            $custom_expenses = floatval($stmt->fetchColumn());
            $estimated_expense += $custom_expenses;
            $pdo->prepare("UPDATE orders_accounting SET estimated_expense = ? WHERE id = ?")
                ->execute([$estimated_expense, $order_accounting_id]);
            create_automatic_expense_record($pdo, $order_accounting_id, $estimated_expense);

            // 6. Налог
            calculate_and_save_tax($pdo, $order_accounting_id, $income);

            $pdo->commit();

            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Внешний заказ успешно создан.'];
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<pre style='color:red; font-weight:bold;'>Ошибка SQL: " . $e->getMessage() . "</pre>";
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при создании заказа: ' . $e->getMessage()];
            // Оставляем на этой же странице без редиректа, чтобы видеть ошибку
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заполните обязательные поля.'];
    }
}

// Получаем список товаров
$stmt = $pdo->query("SELECT id, name, base_price FROM products ORDER BY name");
$products_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include_once('../../includes/header.php');?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-10">
  <div class="container mx-auto px-6 max-w-7xl">

    <!-- Заголовок -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
      <div>
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="flex gap-3">
        <?php echo backButton(); ?>
        <a href="/admin/buhgalt/" class="px-5 py-2.5 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition text-sm font-medium">
          Бухгалтерия
        </a>
      </div>
    </div>

    <!-- Заголовок страницы -->
    <div class="text-center mb-8">
      <h1 class="text-4xl font-extrabold text-gray-900 mb-3">Создание внешнего заказа</h1>
      <p class="text-lg text-gray-700">Занесите заказ вручную для учёта в системе</p>
      <div class="w-20 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $n): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $n['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($n['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Форма -->
    <div class="bg-white rounded-3xl shadow-xl p-8">
      <form method="POST" class="space-y-10">
        <!-- Клиент -->
        <section>
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Информация о клиенте</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-gray-700 font-medium mb-2">Имя клиента *</label>
              <input type="text" name="client_name" required
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
            </div>
            <div>
              <label class="block text-gray-700 font-medium mb-2">Email</label>
              <input type="email" name="email"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
            </div>
            <div>
              <label class="block text-gray-700 font-medium mb-2">Телефон</label>
              <input type="text" name="phone"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
            </div>
            <div>
              <label class="block text-gray-700 font-medium mb-2">Адрес</label>
              <input type="text" name="address"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
            </div>
            <div class="md:col-span-2">
              <label class="block text-gray-700 font-medium mb-2">Описание</label>
              <textarea name="description" rows="3"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]"></textarea>
            </div>
          </div>
        </section>

        <!-- Финансы -->
        <section>
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Финансы</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-gray-700 font-medium mb-2">Доход (руб.) *</label>
              <input type="number" step="0.01" name="income" required id="income-input"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
            </div>
            <div>
              <label class="block text-gray-700 font-medium mb-2">Статус оплаты</label>
              <select name="status"
                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB]">
                <option value="unpaid">Не оплачен</option>
                <option value="partial">Частично</option>
                <option value="paid">Оплачен</option>
              </select>
            </div>
          </div>
        </section>

        <!-- Товары из каталога -->
        <section>
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Товары в заказе</h2>
          <?php if (empty($products_list)): ?>
            <div class="text-center p-8 bg-gray-50 rounded-2xl">
              <p class="text-gray-600">Нет товаров в каталоге</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <?php foreach ($products_list as $p): ?>
                <div class="bg-white border-2 border-gray-200 rounded-2xl p-5 hover:shadow-lg transition">
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($p['name']); ?></h3>
                    <span class="text-[#118568] font-semibold"><?php echo number_format($p['base_price'], 0, '', ' '); ?> ₽</span>
                  </div>
                  <div class="flex items-center">
                    <button type="button" class="w-10 h-10 bg-[#118568] text-white rounded-l-lg decrease-quantity" data-product-id="<?php echo $p['id']; ?>">-</button>
                    <input type="number" name="products[<?php echo $p['id']; ?>]" value="0" min="0"
                           class="w-16 h-10 text-center border-y border-gray-200 font-bold product-quantity-input"
                           data-product-id="<?php echo $p['id']; ?>"
                           data-price="<?php echo $p['base_price']; ?>">
                    <button type="button" class="w-10 h-10 bg-[#118568] text-white rounded-r-lg increase-quantity" data-product-id="<?php echo $p['id']; ?>">+</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <!-- Пользовательские позиции -->
        <section>
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Пользовательские позиции</h2>
          <div id="custom-items-container">
            <div class="custom-item bg-gray-50 rounded-2xl p-6 mb-4">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                  <label class="block text-gray-700 font-medium mb-2">Название</label>
                  <input type="text" name="custom_items[0][name]" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg">
                </div>
                <div>
                  <label class="block text-gray-700 font-medium mb-2">Описание</label>
                  <input type="text" name="custom_items[0][description]" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg">
                </div>
                <div>
                  <label class="block text-gray-700 font-medium mb-2">Количество</label>
                  <input type="number" name="custom_items[0][quantity]" min="0" value="0" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg custom-quantity">
                </div>
                <div>
                  <label class="block text-gray-700 font-medium mb-2">Цена за ед. (₽)</label>
                  <input type="number" step="0.01" name="custom_items[0][unit_price]" min="0" value="0" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg custom-price">
                </div>
                <div>
                  <label class="block text-gray-700 font-medium mb-2">Расход за ед. (₽)</label>
                  <input type="number" step="0.01" name="custom_items[0][expense]" min="0" value="0" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg custom-expense">
                </div>
              </div>
              <div class="mt-4 flex justify-between items-center">
                <div class="text-sm text-gray-600">
                  Сумма: <span class="font-bold custom-total-price">0.00 ₽</span> | 
                  Общий расход: <span class="font-bold custom-total-expense">0.00 ₽</span>
                </div>
                <button type="button" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 remove-custom-item" style="display: none;">Удалить</button>
              </div>
            </div>
          </div>
          <button type="button" id="add-custom-item" class="w-full py-3 bg-[#118568] text-white rounded-xl hover:bg-[#0f755a] transition font-medium">
            + Добавить позицию
          </button>
        </section>

        <!-- Кнопки -->
        <div class="flex gap-4 pt-6">
          <button type="submit" class="flex-1 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:scale-105 transition font-bold text-lg shadow">
            Создать заказ
          </button>
          <a href="/admin/buhgalt/" class="flex-1 px-4 py-4 bg-gray-200 text-gray-700 text-center rounded-xl hover:bg-gray-300 transition font-bold text-lg">
            Отмена
          </a>
        </div>
      </form>
    </div>
        <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <p class="text-sm text-gray-600">Всего товаров</p>
        <p id="stats-products" class="text-2xl font-bold text-[#118568]">0</p>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <p class="text-sm text-gray-600">Общее количество</p>
        <p id="stats-quantity" class="text-2xl font-bold text-[#118568]">0</p>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <p class="text-sm text-gray-600">Сумма заказа</p>
        <p id="stats-sum" class="text-2xl font-bold text-[#118568]">0.00 ₽</p>
      </div>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const decBtns = document.querySelectorAll('.decrease-quantity');
  const incBtns = document.querySelectorAll('.increase-quantity');
  const inputs = document.querySelectorAll('.product-quantity-input');
  const statsProducts = document.getElementById('stats-products');
  const statsQuantity = document.getElementById('stats-quantity');
  const statsSum = document.getElementById('stats-sum');
  const incomeInput = document.getElementById('income-input');
  const customItemsContainer = document.getElementById('custom-items-container');
  const addCustomItemBtn = document.getElementById('add-custom-item');
  let customItemIndex = 1;

  function updateStats() {
    let products = 0, quantity = 0, sum = 0;
    
    // Каталожные товары
    inputs.forEach(inp => {
      let q = parseInt(inp.value) || 0;
      let price = parseFloat(inp.dataset.price) || 0;
      if (q > 0) {
        products++;
        quantity += q;
        sum += q * price;
      }
    });
    
    // Пользовательские позиции
    document.querySelectorAll('.custom-item').forEach(item => {
      const quantity = parseFloat(item.querySelector('.custom-quantity').value) || 0;
      const price = parseFloat(item.querySelector('.custom-price').value) || 0;
      const name = item.querySelector('input[name*="[name]"]').value.trim();
      
      if (quantity > 0 && price > 0 && name) {
        products++;
        sum += quantity * price;
      }
    });
    
    if (incomeInput && incomeInput.value) {
      let manual = parseFloat(incomeInput.value) || 0;
      if (manual > 0) sum = manual; // приоритет ручному вводу
    }
    
    statsProducts.textContent = products;
    statsQuantity.textContent = quantity;
    statsSum.textContent = sum.toFixed(2) + ' ₽';
  }

  function updateCustomItemTotals(item) {
    const quantity = parseFloat(item.querySelector('.custom-quantity').value) || 0;
    const price = parseFloat(item.querySelector('.custom-price').value) || 0;
    const expense = parseFloat(item.querySelector('.custom-expense').value) || 0;
    
    const totalPrice = quantity * price;
    const totalExpense = quantity * expense;
    
    item.querySelector('.custom-total-price').textContent = totalPrice.toFixed(2) + ' ₽';
    item.querySelector('.custom-total-expense').textContent = totalExpense.toFixed(2) + ' ₽';
    
    updateStats();
  }

  function addCustomItemHandlers(item) {
    const inputs = item.querySelectorAll('.custom-quantity, .custom-price, .custom-expense');
    inputs.forEach(input => {
      input.addEventListener('input', () => updateCustomItemTotals(item));
    });
    
    const removeBtn = item.querySelector('.remove-custom-item');
    removeBtn.addEventListener('click', () => {
      item.remove();
      updateStats();
      
      // Показываем кнопки удаления для остальных элементов
      const remainingItems = document.querySelectorAll('.custom-item');
      remainingItems.forEach((item, index) => {
        const btn = item.querySelector('.remove-custom-item');
        btn.style.display = remainingItems.length > 1 ? 'block' : 'none';
      });
    });
  }

  // Обработчики для каталожных товаров
  decBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.productId;
      const inp = document.querySelector(`input[data-product-id="${id}"]`);
      if (inp) {
        let val = parseInt(inp.value) || 0;
        if (val > 0) inp.value = val - 1;
        updateStats();
      }
    });
  });
  
  incBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.productId;
      const inp = document.querySelector(`input[data-product-id="${id}"]`);
      if (inp) {
        let val = parseInt(inp.value) || 0;
        inp.value = val + 1;
        updateStats();
      }
    });
  });
  
  inputs.forEach(inp => inp.addEventListener('input', updateStats));
  if (incomeInput) incomeInput.addEventListener('input', updateStats);

  // Обработчик для первого пользовательского элемента
  addCustomItemHandlers(document.querySelector('.custom-item'));

  // Добавление новых пользовательских позиций
  addCustomItemBtn.addEventListener('click', () => {
    const newItem = document.createElement('div');
    newItem.className = 'custom-item bg-gray-50 rounded-2xl p-6 mb-4';
    newItem.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div>
          <label class="block text-gray-700 font-medium mb-2">Название</label>
          <input type="text" name="custom_items[${customItemIndex}][name]" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-2">Описание</label>
          <input type="text" name="custom_items[${customItemIndex}][description]" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-2">Количество</label>
          <input type="number" name="custom_items[${customItemIndex}][quantity]" min="0" value="0" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg custom-quantity">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-2">Цена за ед. (₽)</label>
          <input type="number" step="0.01" name="custom_items[${customItemIndex}][unit_price]" min="0" value="0" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg custom-price">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-2">Расход за ед. (₽)</label>
          <input type="number" step="0.01" name="custom_items[${customItemIndex}][expense]" min="0" value="0" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg custom-expense">
        </div>
      </div>
      <div class="mt-4 flex justify-between items-center">
        <div class="text-sm text-gray-600">
          Сумма: <span class="font-bold custom-total-price">0.00 ₽</span> | 
          Общий расход: <span class="font-bold custom-total-expense">0.00 ₽</span>
        </div>
        <button type="button" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 remove-custom-item">Удалить</button>
      </div>
    `;
    
    customItemsContainer.appendChild(newItem);
    addCustomItemHandlers(newItem);
    customItemIndex++;
    
    // Показываем кнопки удаления
    document.querySelectorAll('.remove-custom-item').forEach(btn => {
      btn.style.display = 'block';
    });
  });

  updateStats();
});
</script>

<?php include_once('../../includes/footer.php'); ?>
