<?php
session_start();
require_once '../includes/security.php';
$pageTitle = "Мини-склад";

// Подключение к базе данных
include_once('../includes/db.php');
include_once('../includes/session_check.php');
include_once('../includes/mini_warehouse_notifications.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: /login");
  exit();
}

$user_id = $_SESSION['user_id'];

// Проверяем, включена ли функция мини-склада для пользователя
$stmt = $pdo->prepare("SELECT mini_warehouse_enabled FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['mini_warehouse_enabled']) {
  header("Location: /client/dashboard.php");
  exit();
}

// Обработка добавления нового товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
  verify_csrf();
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $quantity = intval($_POST['quantity']);

  if (!empty($name) && $quantity > 0) {
    $stmt = $pdo->prepare("INSERT INTO mini_warehouse_items (user_id, name, description, quantity) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $name, $description, $quantity]);

    // Отправляем уведомление
    $item_data = [
      'name' => $name,
      'description' => $description,
      'quantity' => $quantity
    ];
    sendMiniWarehouseItemAddedNotification($user_id, $item_data);

    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Товар успешно добавлен на склад.'];
    header("Location: mini_warehouse.php");
    exit();
  } else {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пожалуйста, заполните все обязательные поля.'];
  }
}

// Обработка удаления товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
  verify_csrf();
  $item_id = intval($_POST['item_id']);

  // Проверяем, что товар принадлежит текущему пользователю и получаем его данные
  $stmt = $pdo->prepare("SELECT * FROM mini_warehouse_items WHERE id = ? AND user_id = ?");
  $stmt->execute([$item_id, $user_id]);
  $item = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($item) {
    // Отправляем уведомление перед удалением
    sendMiniWarehouseItemRemovedNotification($user_id, $item);

    $stmt = $pdo->prepare("DELETE FROM mini_warehouse_items WHERE id = ?");
    $stmt->execute([$item_id]);

    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Товар успешно удален со склада.'];
  } else {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка удаления товара.'];
  }

  header("Location: mini_warehouse.php");
  exit();
}

// Получение всех товаров пользователя
$stmt = $pdo->prepare("SELECT * FROM mini_warehouse_items WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
  $notifications = $_SESSION['notifications'];
  unset($_SESSION['notifications']);
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?> - BZK Print</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-slide-left {
      animation: slideInLeft 0.6s ease-out forwards;
      opacity: 0;
    }

    .animate-slide-right {
      animation: slideInRight 0.6s ease-out forwards;
      opacity: 0;
    }

    .animate-fade-up {
      animation: fadeInUp 0.6s ease-out forwards;
      opacity: 0;
    }

    .hover-lift {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .hover-lift:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .form-floating {
      position: relative;
    }

    .form-floating input:focus+label,
    .form-floating input:not(:placeholder-shown)+label {
      transform: translateY(-1.5rem) scale(0.8);
      color: #118568;
    }

    .form-floating label {
      position: absolute;
      top: 0.75rem;
      left: 1rem;
      transition: all 0.2s ease;
      pointer-events: none;
      color: #6b7280;
    }
  </style>
</head>

<body class="font-sans bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] min-h-screen">

  <?php include_once('../includes/header.php'); ?>

  <main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
    <div class="container mx-auto px-4 max-w-6xl">
      <!-- Breadcrumbs и навигация с анимацией -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 animate-fade-up"
        style="animation-delay: 0.1s">
        <div class="w-full md:w-auto">
          <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
        </div>
        <div class="w-full md:w-auto">
          <div class="flex gap-2">
            <?php echo backButton(); ?>
            <a href="/client/dashboard.php"
              class="px-4 py-2 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 text-sm font-medium">
              <i class="fas fa-arrow-left mr-2"></i>Назад в кабинет
            </a>
          </div>
        </div>
      </div>

      <div class="text-center mb-12 animate-fade-up" style="animation-delay: 0.2s">
        <h1 class="text-4xl font-bold text-gray-800 mb-4">Мини-склад</h1>
        <p class="text-lg text-gray-600">Управление вашими футболками</p>
        <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] mx-auto mt-4"></div>
      </div>

      <!-- Уведомления -->
      <?php foreach ($notifications as $notification): ?>
        <div
          class="mb-6 p-4 rounded-xl animate-fade-up <?php echo $notification['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>"
          style="animation-delay: 0.3s">
          <?php echo htmlspecialchars($notification['message']); ?>
        </div>
      <?php endforeach; ?>

      <!-- Форма добавления нового товара -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8 animate-fade-up" style="animation-delay: 0.4s">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Добавить футболку</h2>
        <form method="post" class="space-y-4">
          <?php echo csrf_field(); ?>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                Название <span class="text-red-500">*</span>
              </label>
              <input type="text" id="name" name="name" required
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300"
                placeholder="Например: Футболка Nike белая">
            </div>

            <div>
              <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                Количество <span class="text-red-500">*</span>
              </label>
              <input type="number" id="quantity" name="quantity" min="1" value="1" required
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300">
            </div>
          </div>

          <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
              Описание
            </label>
            <textarea id="description" name="description" rows="3"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#118568] focus:border-transparent transition-colors duration-300"
              placeholder="Дополнительная информация о футболке..."></textarea>
          </div>

          <div class="pt-4">
            <button type="submit" name="add_item"
              class="px-6 py-3 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-lg hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-medium shadow-lg hover:shadow-xl">
              <i class="fas fa-plus mr-2"></i>Добавить на склад
            </button>
          </div>
        </form>
      </div>

      <!-- Список товаров -->
      <div class="bg-white rounded-2xl shadow-xl p-6 animate-fade-up" style="animation-delay: 0.5s">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Мои футболки</h2>

        <?php if (empty($items)): ?>
          <div class="text-center py-12">
            <i class="fas fa-box-open text-5xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-500 mb-2">Склад пуст</h3>
            <p class="text-gray-400">Добавьте первую футболку, используя форму выше</p>
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($items as $item): ?>
              <div class="border border-gray-200 rounded-xl p-5 hover-lift">
                <div class="flex justify-between items-start mb-3">
                  <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                  <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                    <?php echo $item['quantity']; ?> шт.
                  </span>
                </div>

                <?php if (!empty($item['description'])): ?>
                  <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($item['description']); ?></p>
                <?php endif; ?>

                <div class="flex justify-between items-center mt-4">
                  <span class="text-xs text-gray-500">
                    Добавлено: <?php echo date('d.m.Y', strtotime($item['created_at'])); ?>
                  </span>

                  <form method="post" class="inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                    <button type="submit" name="delete_item"
                      class="text-red-500 hover:text-red-700 transition-colors duration-300"
                      onclick="return confirm('Вы уверены, что хотите удалить этот товар со склада?')">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <?php include_once('../includes/footer.php'); ?>
</body>

</html>