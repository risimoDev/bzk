<?php
// admin/product/manage_expenses.php
session_start();
$pageTitle = "Управление расходниками";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

include_once('../../includes/header.php');
include_once('../../includes/db.php');

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Не указан товар.'];
    header("Location: /admin/products");
    exit;
}

// Получаем информацию о товаре
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Товар не найден.'];
    header("Location: /admin/products");
    exit;
}

// Обработка добавления/редактирования расходника
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_expense') {
        $material_name = trim($_POST['material_name'] ?? '');
        $quantity_per_unit = floatval($_POST['quantity_per_unit'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);
        
        if (!empty($material_name) && $quantity_per_unit > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO product_expenses (product_id, material_name, quantity_per_unit, unit, cost_per_unit) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$product_id, $material_name, $quantity_per_unit, $unit, $cost_per_unit ? $cost_per_unit : null]);
                
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Расходник успешно добавлен.'];
            } catch (Exception $e) {
                error_log("Ошибка при добавлении расходника: " . $e->getMessage());
                $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при добавлении расходника.'];
            }
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пожалуйста, заполните все обязательные поля корректно.'];
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } elseif ($action === 'edit_expense' && isset($_POST['expense_id'])) {
        $expense_id = intval($_POST['expense_id']);
        $material_name = trim($_POST['material_name'] ?? '');
        $quantity_per_unit = floatval($_POST['quantity_per_unit'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);
        
        // Проверяем, что расходник принадлежит этому товару
        $stmt = $pdo->prepare("SELECT id FROM product_expenses WHERE id = ? AND product_id = ?");
        $stmt->execute([$expense_id, $product_id]);
        if ($stmt->fetch()) {
            if (!empty($material_name) && $quantity_per_unit > 0) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE product_expenses 
                        SET material_name = ?, quantity_per_unit = ?, unit = ?, cost_per_unit = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$material_name, $quantity_per_unit, $unit, $cost_per_unit ? $cost_per_unit : null, $expense_id]);
                    
                    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Расходник успешно обновлен.'];
                } catch (Exception $e) {
                    error_log("Ошибка при обновлении расходника: " . $e->getMessage());
                    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при обновлении расходника.'];
                }
            } else {
                $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пожалуйста, заполните все обязательные поля корректно.'];
            }
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Расходник не найден или не принадлежит этому товару.'];
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } elseif ($action === 'delete_expense' && isset($_POST['expense_id'])) {
        $expense_id = intval($_POST['expense_id']);
        
        // Проверяем, что расходник принадлежит этому товару
        $stmt = $pdo->prepare("SELECT id FROM product_expenses WHERE id = ? AND product_id = ?");
        $stmt->execute([$expense_id, $product_id]);
        if ($stmt->fetch()) {
            try {
                $stmt = $pdo->prepare("DELETE FROM product_expenses WHERE id = ?");
                $stmt->execute([$expense_id]);
                
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Расходник успешно удален.'];
            } catch (Exception $e) {
                error_log("Ошибка при удалении расходника: " . $e->getMessage());
                $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при удалении расходника.'];
            }
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Расходник не найден или не принадлежит этому товару.'];
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Получаем список расходников для товара
$stmt = $pdo->prepare("SELECT * FROM product_expenses WHERE product_id = ? ORDER BY id");
$stmt->execute([$product_id]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Расходники для товара</h1>
      <p class="text-xl text-gray-700"><?php echo htmlspecialchars($product['name']); ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Форма добавления расходника -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Добавить новый расходник</h2>
      
      <form action="" method="POST" class="space-y-6">
        <input type="hidden" name="action" value="add_expense">
        
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <div>
            <label for="material_name" class="block text-gray-700 font-medium mb-2">Название расходника *</label>
            <input type="text" id="material_name" name="material_name" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="Например, Пленка ПВХ" required>
          </div>
          
          <div>
            <label for="quantity_per_unit" class="block text-gray-700 font-medium mb-2">Количество на единицу *</label>
            <input type="number" step="0.0001" id="quantity_per_unit" name="quantity_per_unit" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="0.0000" min="0" required>
          </div>
          
          <div>
            <label for="unit" class="block text-gray-700 font-medium mb-2">Единица измерения</label>
            <input type="text" id="unit" name="unit" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="Например, м2, шт, г">
          </div>
          
          <div>
            <label for="cost_per_unit" class="block text-gray-700 font-medium mb-2">Себестоимость единицы (руб.)</label>
            <input type="number" step="0.01" id="cost_per_unit" name="cost_per_unit" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                   placeholder="0.00" min="0">
          </div>
        </div>
        
        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Добавить расходник
        </button>
      </form>
    </div>

    <!-- Список расходников -->
    <?php if (empty($expenses)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Расходники не найдены</h2>
        <p class="text-gray-600 mb-8">Добавьте первый расходник, используя форму выше</p>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-3xl shadow-2xl p-6">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
          <h2 class="text-2xl font-bold text-gray-800">Список расходников</h2>
          <div class="text-gray-600">
            Найдено: <?php echo count($expenses); ?> <?php echo count($expenses) == 1 ? 'расходник' : (count($expenses) < 5 ? 'расходника' : 'расходников'); ?>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-[#118568] text-white">
              <tr>
                <th class="py-4 px-6 text-left">Название</th>
                <th class="py-4 px-6 text-left">Количество на ед.</th>
                <th class="py-4 px-6 text-left">Ед. измерения</th>
                <th class="py-4 px-6 text-left">Себестоимость ед.</th>
                <th class="py-4 px-6 text-left">Действия</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#DEE5E5]">
              <?php foreach ($expenses as $expense): ?>
                <tr class="hover:bg-[#f8fafa] transition-colors duration-300">
                  <td class="py-4 px-6 font-medium"><?php echo htmlspecialchars($expense['material_name']); ?></td>
                  <td class="py-4 px-6"><?php echo number_format($expense['quantity_per_unit'], 4, '.', ' '); ?></td>
                  <td class="py-4 px-6"><?php echo htmlspecialchars($expense['unit'] ?? 'Не указана'); ?></td>
                  <td class="py-4 px-6">
                    <?php if ($expense['cost_per_unit']): ?>
                      <?php echo number_format($expense['cost_per_unit'], 2, '.', ' '); ?> ₽
                    <?php else: ?>
                      <span class="text-gray-500">Не указана</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-6">
                    <div class="flex flex-wrap gap-2">
                      <!-- Форма редактирования (в модальном окне или на отдельной странице в реальном проекте) -->
                      <!-- Для простоты здесь будет форма с предзаполненными полями, которая появляется при клике -->
                      <button onclick="openEditForm(<?php echo $expense['id']; ?>)" 
                              class="px-3 py-1 bg-[#118568] text-white text-sm rounded hover:bg-[#0f755a] transition-colors duration-300">
                        Редактировать
                      </button>
                      
                      <form action="" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить этот расходник?')" class="m-0">
                        <input type="hidden" name="action" value="delete_expense">
                        <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                        <button type="submit" 
                                class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 transition-colors duration-300">
                          Удалить
                        </button>
                      </form>
                    </div>
                    
                    <!-- Скрытая форма редактирования -->
                    <div id="edit-form-<?php echo $expense['id']; ?>" class="mt-4 p-4 bg-gray-50 rounded-xl hidden">
                      <h3 class="font-bold text-gray-800 mb-3">Редактировать расходник</h3>
                      <form action="" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="edit_expense">
                        <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                          <div>
                            <label class="block text-gray-700 text-sm mb-1">Название *</label>
                            <input type="text" name="material_name" 
                                   value="<?php echo htmlspecialchars($expense['material_name']); ?>"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] text-sm"
                                   required>
                          </div>
                          
                          <div>
                            <label class="block text-gray-700 text-sm mb-1">Количество на ед. *</label>
                            <input type="number" step="0.0001" name="quantity_per_unit" 
                                   value="<?php echo $expense['quantity_per_unit']; ?>"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] text-sm"
                                   min="0" required>
                          </div>
                          
                          <div>
                            <label class="block text-gray-700 text-sm mb-1">Ед. измерения</label>
                            <input type="text" name="unit" 
                                   value="<?php echo htmlspecialchars($expense['unit'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] text-sm">
                          </div>
                          
                          <div>
                            <label class="block text-gray-700 text-sm mb-1">Себестоимость ед. (руб.)</label>
                            <input type="number" step="0.01" name="cost_per_unit" 
                                   value="<?php echo $expense['cost_per_unit'] ? $expense['cost_per_unit'] : ''; ?>"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] text-sm"
                                   min="0">
                          </div>
                        </div>
                        
                        <div class="flex gap-2">
                          <button type="submit" 
                                  class="px-4 py-2 bg-[#118568] text-white text-sm rounded hover:bg-[#0f755a] transition-colors duration-300">
                            Сохранить
                          </button>
                          <button type="button" onclick="closeEditForm(<?php echo $expense['id']; ?>)" 
                                  class="px-4 py-2 bg-gray-300 text-gray-700 text-sm rounded hover:bg-gray-400 transition-colors duration-300">
                            Отмена
                          </button>
                        </div>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
function openEditForm(id) {
    document.getElementById('edit-form-' + id).classList.remove('hidden');
}

function closeEditForm(id) {
    document.getElementById('edit-form-' + id).classList.add('hidden');
}
</script>

<?php include_once('../../includes/footer.php'); ?>