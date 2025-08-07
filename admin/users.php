<?php
session_start();
$pageTitle = "Управление пользователями";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

// Обработчик изменения роли пользователя
if (isset($_GET['change_role'])) {
    $id = intval($_GET['user_id']);
    $role = htmlspecialchars($_GET['change_role']);
    
    // Защита от изменения роли самого администратора
    if ($id == $_SESSION['user_id'] && $role !== 'admin') {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Вы не можете изменить свою собственную роль!'];
        header("Location: /admin/users");
        exit();
    }

    // Проверяем, что ID пользователя существует
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Обновляем роль пользователя
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $success = $stmt->execute([$role, $id]);

        if ($success) {
            $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Роль пользователя успешно обновлена!'];
        } else {
            $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при обновлении роли пользователя.'];
        }
    } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пользователь не найден.'];
    }

    header("Location: /admin/users");
    exit();
}

// Получение списка пользователей с пагинацией
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Получение общего количества пользователей
$total_users_stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = $total_users_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Получение пользователей для текущей страницы
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики пользователей
$admins_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$managers_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$users_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Админ-панель | Управление пользователями</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] min-h-screen">

  <!-- Шапка -->
  <?php include_once('../includes/header.php'); ?>

  <!-- Управление пользователями -->
  <main class="container mx-auto px-4 py-8 max-w-7xl">
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
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление пользователями</h1>
      <p class="text-xl text-gray-700">Всего пользователей: <?php echo $total_users; ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Статистика пользователей -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo $admins_count; ?></div>
        <div class="text-gray-600">Администраторов</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#17B890] mb-2"><?php echo $managers_count; ?></div>
        <div class="text-gray-600">Менеджеров</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#5E807F] mb-2"><?php echo $users_count; ?></div>
        <div class="text-gray-600">Клиентов</div>
      </div>
    </div>

    <!-- Таблица пользователей -->
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="p-6 border-b border-[#DEE5E5]">
        <h2 class="text-2xl font-bold text-gray-800">Список пользователей</h2>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-[#118568] text-white">
            <tr>
              <th class="py-4 px-6 text-left">ID</th>
              <th class="py-4 px-6 text-left">Имя</th>
              <th class="py-4 px-6 text-left">Email</th>
              <th class="py-4 px-6 text-left">Телефон</th>
              <th class="py-4 px-6 text-left">Роль</th>
              <th class="py-4 px-6 text-left">Дата регистрации</th>
              <th class="py-4 px-6 text-left">Действия</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#DEE5E5]">
            <?php foreach ($users as $user): ?>
            <tr class="hover:bg-[#f8fafa] transition-colors duration-300">
              <td class="py-4 px-6 font-medium"><?php echo htmlspecialchars($user['id']); ?></td>
              <td class="py-4 px-6">
                <div class="font-medium"><?php echo htmlspecialchars($user['name']); ?></div>
              </td>
              <td class="py-4 px-6 text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
              <td class="py-4 px-6 text-gray-600"><?php echo htmlspecialchars($user['phone'] ?? 'Не указан'); ?></td>
              <td class="py-4 px-6">
                <?php
                $role_colors = [
                    'admin' => 'bg-red-100 text-red-800',
                    'manager' => 'bg-purple-100 text-purple-800',
                    'user' => 'bg-blue-100 text-blue-800'
                ];
                $role_names = [
                    'admin' => 'Администратор',
                    'manager' => 'Менеджер',
                    'user' => 'Клиент'
                ];
                ?>
                <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $role_colors[$user['role']]; ?>">
                  <?php echo $role_names[$user['role']]; ?>
                </span>
              </td>
              <td class="py-4 px-6 text-gray-600"><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
              <td class="py-4 px-6">
                <div class="flex flex-wrap gap-2">
                  <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <a href="?change_role=user&user_id=<?php echo $user['id']; ?>" 
                       class="px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 transition-colors duration-300 <?php echo $user['role'] === 'user' ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?php echo $user['role'] === 'user' ? 'onclick="return false;"' : ''; ?>>
                      Клиент
                    </a>
                    <a href="?change_role=manager&user_id=<?php echo $user['id']; ?>" 
                       class="px-3 py-1 bg-purple-500 text-white text-xs rounded hover:bg-purple-600 transition-colors duration-300 <?php echo $user['role'] === 'manager' ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?php echo $user['role'] === 'manager' ? 'onclick="return false;"' : ''; ?>>
                      Менеджер
                    </a>
                    <a href="?change_role=admin&user_id=<?php echo $user['id']; ?>" 
                       class="px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600 transition-colors duration-300 <?php echo $user['role'] === 'admin' ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?php echo $user['role'] === 'admin' ? 'onclick="return false;"' : ''; ?>>
                      Админ
                    </a>
                  <?php else: ?>
                    <span class="px-3 py-1 bg-gray-200 text-gray-500 text-xs rounded">Текущий пользователь</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Пагинация -->
    <?php if ($total_pages > 1): ?>
      <div class="flex justify-center mt-8">
        <div class="bg-white rounded-2xl shadow-lg px-6 py-4 flex items-center space-x-2">
          <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" 
               class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5] transition-colors duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </a>
          <?php endif; ?>

          <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>" 
               class="w-10 h-10 flex items-center justify-center rounded-lg <?php echo $i == $page ? 'bg-[#118568] text-white' : 'hover:bg-[#DEE5E5]'; ?> transition-colors duration-300">
              <?php echo $i; ?>
            </a>
          <?php endfor; ?>

          <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>" 
               class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5] transition-colors duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <!-- Футер -->
  <?php include_once('../includes/footer.php'); ?>

