<?php
session_start();
$pageTitle = "Добавить общий расход";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

include_once('../../includes/db.php');
require_once '../../includes/security.php';

// Инициализируем массив ошибок
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // 1. Валидация category_id
    $category_id = intval($_POST['category_id'] ?? 0);
    if ($category_id <= 0) {
        $errors[] = 'Выберите корректную категорию.';
    } else {
        // Проверим, существует ли категория в БД
        $stmt_check_cat = $pdo->prepare("SELECT id FROM expenses_categories WHERE id = ?");
        $stmt_check_cat->execute([$category_id]);
        if (!$stmt_check_cat->fetch()) {
             $errors[] = 'Выбранная категория не существует.';
             $category_id = 0; // Сбросим ID, если не найдена
        }
    }

    // 2. Валидация amount
    $amount_input = $_POST['amount'] ?? '';
    $amount = floatval($amount_input);
    if ($amount <= 0) {
        $errors[] = 'Введите корректную сумму (больше 0).';
        $amount = 0; // Сбросим значение, если некорректно
    }
    // (Опционально) Проверить, что введено именно число (а не строка с буквами)
    if (!is_numeric($amount_input)) {
        $errors[] = 'Поле суммы должно содержать число.';
        $amount = 0;
    }

    // 3. Валидация description (если есть)
    $description = trim($_POST['description'] ?? '');
    // Можно добавить ограничение на длину, например, 500 символов
    if (strlen($description) > 500) {
        $errors[] = 'Описание не должно превышать 500 символов.';
        $description = substr($description, 0, 500); // Обрежем, если длиннее
    }

    // 4. Валидация expense_date
    $expense_date_input = $_POST['expense_date'] ?? '';
    $expense_date = null;
    if ($expense_date_input === '') {
        // Если дата не указана, используем текущее время
        $expense_date = date('Y-m-d H:i:s');
    } else {
        // Проверим формат и корректность даты/времени
        $date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $expense_date_input);
        if ($date_obj && $date_obj->format('Y-m-d\TH:i') === $expense_date_input) {
            // Если формат правильный, установим правильный формат для БД
            $expense_date = $date_obj->format('Y-m-d H:i:s');
        } else {
            $errors[] = 'Введите корректную дату и время (ГГГГ-ММ-ДД ЧЧ:ММ).';
            // Если дата неверна, используем текущее время как fallback
            $expense_date = date('Y-m-d H:i:s');
        }
    }

    // Если ошибок нет, пытаемся вставить в БД
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO general_expenses (category_id, amount, description, expense_date)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([$category_id, $amount, $description, $expense_date]);

            if ($result) {
                $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Расход добавлен.'];
                header("Location: index.php");
                exit();
            } else {
                // Ошибка выполнения запроса
                $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при добавлении расхода в базу данных.'];
            }
        } catch (PDOException $e) {
            // Ошибка базы данных (например, ошибка связности, ограничения)
            error_log("Ошибка добавления общего расхода: " . $e->getMessage());
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Произошла ошибка при добавлении расхода.'];
        }
    } else {
        // Если есть ошибки, добавляем их в сессию
        foreach ($errors as $error) {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => $error];
        }
        // Перезагрузим страницу, чтобы показать ошибки (и сохранить введённые данные)
        // Это можно сделать через header, но лучше сразу отобразить ошибки на той же странице
        // Для этого убираем header и позволяем коду ниже отрисовать форму снова.
        // header("Location: " . $_SERVER['REQUEST_URI']);
        // exit();
        // Вместо этого, мы просто не делаем редирект, и форма отрисовывается снова с ошибками.
    }
}

// Загружаем категории для формы (делаем это всегда)
$categories = $pdo->query("SELECT * FROM expenses_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-3xl">
    <div class="bg-white rounded-3xl shadow-2xl p-8">
      <h1 class="text-3xl font-bold text-gray-800 mb-6">Добавить общий расход</h1>

      <form method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        <div>
          <label class="block text-gray-700 mb-2">Категория</label>
          <select name="category_id" class="w-full border rounded-lg px-3 py-2">
            <option value="0" <?php echo (!isset($category_id) || $category_id <= 0) ? 'selected' : ''; ?>>Выберите категорию</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo (int)$cat['id']; ?>" <?php echo (isset($category_id) && $category_id == $cat['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-gray-700 mb-2">Сумма (₽)</label>
          <input type="number" step="0.01" name="amount" value="<?php echo isset($amount) ? htmlspecialchars($amount) : ''; ?>" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-gray-700 mb-2">Дата расхода</label>
          <input type="datetime-local" name="expense_date" value="<?php echo isset($expense_date_input) ? htmlspecialchars($expense_date_input) : ''; ?>" class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-gray-700 mb-2">Описание</label>
          <textarea name="description" class="w-full border rounded-lg px-3 py-2"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
        </div>
        <button type="submit" class="w-full bg-[#118568] text-white py-3 rounded-xl hover:bg-[#0f755a]">
          Добавить
        </button>
      </form>
    </div>
  </div>
</main>
<?php include_once('../../includes/footer.php'); ?>