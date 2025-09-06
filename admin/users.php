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

// Блокировка / разблокировка
if (isset($_GET['toggle_block'])) {
    $id = intval($_GET['user_id']);
    if ($id == $_SESSION['user_id']) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Нельзя заблокировать себя!'];
    } else {
        $stmt = $pdo->prepare("UPDATE users SET is_blocked = 1 - is_blocked WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Статус блокировки изменён!'];
    }
    header("Location: /admin/users");
    exit();
}

// Изменение роли
if (isset($_GET['change_role'])) {
    $id = intval($_GET['user_id']);
    $role = htmlspecialchars($_GET['change_role']);

    if ($id == $_SESSION['user_id'] && $role !== 'admin') {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Вы не можете изменить свою собственную роль!'];
        header("Location: /admin/users");
        exit();
    }

    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$role, $id]);
    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Роль обновлена!'];
    header("Location: /admin/users");
    exit();
}

// Фильтры
$filter_role = $_GET['role'] ?? 'all';
$filter_blocked = $_GET['blocked'] ?? 'all';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($filter_role !== 'all') {
    $query .= " AND role = ?";
    $params[] = $filter_role;
}

if ($filter_blocked === 'blocked') {
    $query .= " AND is_blocked = 1";
} elseif ($filter_blocked === 'active') {
    $query .= " AND is_blocked = 0";
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$admins_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$managers_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$clients_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$blocked_count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_blocked = 1")->fetchColumn();
?>


<?php include_once('../includes/header.php'); ?>

<main class="container mx-auto px-4 py-8 max-w-7xl">
  <!-- Breadcrumbs -->
  <div class="flex justify-between items-center mb-8">
    <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
    <?php echo backButton(); ?>
  </div>

  <div class="text-center mb-12">
    <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление пользователями</h1>
    <p class="text-lg text-gray-600">Всего: <?php echo $total_users; ?> | Заблокировано: <?php echo $blocked_count; ?></p>
    <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] mx-auto mt-4"></div>
  </div>

  <!-- Уведомления -->
  <?php foreach ($notifications as $n): ?>
    <div class="mb-6 p-4 rounded-xl <?php echo $n['type']==='success'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>">
      <?php echo htmlspecialchars($n['message']); ?>
    </div>
  <?php endforeach; ?>

  <!-- Фильтры -->
  <form method="get" class="flex flex-wrap gap-4 bg-white p-4 rounded-xl shadow mb-8">
    <select name="role" class="px-4 py-2 border rounded-lg">
      <option value="all">Все роли</option>
      <option value="admin" <?php if($filter_role==='admin') echo 'selected'; ?>>Админ</option>
      <option value="manager" <?php if($filter_role==='manager') echo 'selected'; ?>>Менеджер</option>
      <option value="user" <?php if($filter_role==='user') echo 'selected'; ?>>Клиент</option>
    </select>
    <select name="blocked" class="px-4 py-2 border rounded-lg">
      <option value="all">Все</option>
      <option value="active" <?php if($filter_blocked==='active') echo 'selected'; ?>>Активные</option>
      <option value="blocked" <?php if($filter_blocked==='blocked') echo 'selected'; ?>>Заблокированные</option>
    </select>
    <button type="submit" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a]">Фильтровать</button>
  </form>

  <!-- Список пользователей (карточки) -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($users as $u): ?>
      <div class="bg-white rounded-2xl shadow-xl p-6 relative">
        <?php if ($u['is_blocked']): ?>
          <div class="absolute top-3 right-3 bg-red-500 text-white text-xs px-2 py-1 rounded">Заблокирован</div>
        <?php endif; ?>

        <h2 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($u['name']); ?></h2>
        <p class="text-gray-600 mb-1">Email: <?php echo htmlspecialchars($u['email']); ?></p>
        <p class="text-gray-600 mb-1">Телефон: <?php echo htmlspecialchars($u['phone'] ?? '—'); ?></p>
        <p class="text-gray-600 mb-4">Дата: <?php echo date('d.m.Y', strtotime($u['created_at'])); ?></p>

        <div class="flex flex-wrap gap-2">
          <!-- Роли -->
          <?php if ($u['id'] != $_SESSION['user_id']): ?>
            <a href="?change_role=user&user_id=<?php echo $u['id']; ?>" class="px-3 py-1 rounded text-xs <?php echo $u['role']==='user'?'bg-blue-200 text-blue-800':'bg-blue-500 text-white hover:bg-blue-600'; ?>">Клиент</a>
            <a href="?change_role=manager&user_id=<?php echo $u['id']; ?>" class="px-3 py-1 rounded text-xs <?php echo $u['role']==='manager'?'bg-purple-200 text-purple-800':'bg-purple-500 text-white hover:bg-purple-600'; ?>">Менеджер</a>
            <a href="?change_role=admin&user_id=<?php echo $u['id']; ?>" class="px-3 py-1 rounded text-xs <?php echo $u['role']==='admin'?'bg-red-200 text-red-800':'bg-red-500 text-white hover:bg-red-600'; ?>">Админ</a>
          <?php else: ?>
            <span class="px-3 py-1 bg-gray-200 text-gray-500 text-xs rounded">Вы</span>
          <?php endif; ?>

          <!-- Блокировка -->
          <?php if ($u['id'] != $_SESSION['user_id']): ?>
            <a href="?toggle_block=1&user_id=<?php echo $u['id']; ?>" class="px-3 py-1 rounded text-xs <?php echo $u['is_blocked']?'bg-green-500 hover:bg-green-600 text-white':'bg-red-500 hover:bg-red-600 text-white'; ?>">
              <?php echo $u['is_blocked'] ? 'Разблокировать' : 'Заблокировать'; ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>

