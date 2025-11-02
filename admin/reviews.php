<?php
include_once('../includes/session.php');
include_once('../includes/db.php');

// Простейшая защита: только для админа (замени по своей логике)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

// --- Пагинация и фильтрация ---
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9; // количество отзывов на странице
$offset = ($page - 1) * $perPage;

// Фильтр по статусу
$statusFilter = $_GET['status'] ?? ''; // 'pending', 'approved', 'rejected', или пусто (все)
$allowedStatuses = ['pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// Формирование WHERE части запроса
$whereClause = "WHERE 1=1";
$params = [];
if ($statusFilter) {
    $whereClause .= " AND r.status = :status";
    $params[':status'] = $statusFilter;
}

// Обработка действий approve/reject/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
  $id = (int)$_POST['id'];
  $action = $_POST['action'];

  if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?");
    $stmt->execute([$id]);
  } elseif ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$id]);
  } elseif ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([$id]);
  }

  header("Location: " . $_SERVER['REQUEST_URI']);
  exit;
}

// --- Загружаем отзывы (с пагинацией и фильтрацией) ---
$sql = "SELECT r.*, u.name AS user_name
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        $whereClause
        ORDER BY 
          FIELD(status, 'pending', 'approved', 'rejected'),
          created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Общее количество отзывов (для пагинации) ---
$countSql = "SELECT COUNT(*) FROM reviews r $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v);
}
$countStmt->execute();
$totalReviews = $countStmt->fetchColumn();
$totalPages = (int) ceil($totalReviews / $perPage);

$pageTitle = "Модерация отзывов";
?>
<?php include_once('../includes/header.php'); ?>
<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div><?php echo generateBreadcrumbs($pageTitle ?? ''); ?></div>
      <div><?php echo backButton(); ?></div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
      <h1 class="text-3xl font-bold text-gray-800">Модерация отзывов</h1>
      <div class="flex flex-wrap gap-3">
        <a href="?status=" class="px-4 py-2 rounded-lg bg-white shadow-sm text-sm font-medium border <?php echo $statusFilter === '' ? 'border-[#118568] text-[#118568] bg-[#F6FFFA]' : 'border-gray-200 text-gray-700 hover:bg-gray-50'; ?>">Все</a>
        <a href="?status=pending" class="px-4 py-2 rounded-lg bg-white shadow-sm text-sm font-medium border <?php echo $statusFilter === 'pending' ? 'border-yellow-500 text-yellow-600 bg-yellow-50' : 'border-gray-200 text-gray-700 hover:bg-gray-50'; ?>">Ожидают</a>
        <a href="?status=approved" class="px-4 py-2 rounded-lg bg-white shadow-sm text-sm font-medium border <?php echo $statusFilter === 'approved' ? 'border-green-500 text-green-600 bg-green-50' : 'border-gray-200 text-gray-700 hover:bg-gray-50'; ?>">Одобренные</a>
        <a href="?status=rejected" class="px-4 py-2 rounded-lg bg-white shadow-sm text-sm font-medium border <?php echo $statusFilter === 'rejected' ? 'border-red-500 text-red-600 bg-red-50' : 'border-gray-200 text-gray-700 hover:bg-gray-50'; ?>">Отклонённые</a>
      </div>
    </div>

    <?php if (empty($reviews)): ?>
      <div class="bg-white rounded-2xl shadow-md p-12 text-center">
        <div class="text-6xl mb-4">📋</div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Отзывов нет</h3>
        <p class="text-gray-600 mb-6"><?php echo $statusFilter ? 'Нет отзывов с выбранным статусом.' : 'Пока нет отзывов для модерации.'; ?></p>
        <a href="?status=" class="inline-block px-6 py-3 bg-[#118568] text-white rounded-lg font-medium hover:bg-[#0f755a] transition">Сбросить фильтр</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <?php foreach ($reviews as $r): ?>
          <div class="bg-white rounded-2xl shadow-md overflow-hidden flex flex-col justify-between border-t-4
            <?php
              echo $r['status'] === 'pending' ? 'border-yellow-400' :
                   ($r['status'] === 'approved' ? 'border-green-500' : 'border-red-500');
            ?> transform transition-all duration-200 hover:shadow-lg hover:-translate-y-1">
            <div class="p-6">
              <div class="flex items-center justify-between mb-3">
                <div class="font-bold text-gray-800 truncate">
                  <?php echo htmlspecialchars($r['name'] ?? $r['user_name'] ?? 'Пользователь'); ?>
                </div>
                <div class="text-xs text-gray-500"><?php echo date('d.m.Y', strtotime($r['created_at'])); ?></div>
              </div>

              <div class="text-gray-700 mb-4 whitespace-pre-line line-clamp-4"><?php echo htmlspecialchars($r['review_text']); ?></div>
              
              <div class="flex items-center gap-2 mb-3">
                <div class="text-sm text-yellow-600 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                  <?php echo (int)$r['rating']; ?>/5
                </div>
              </div>
              
              <div class="text-xs uppercase font-semibold px-3 py-1 rounded-full w-fit
                <?php
                  echo $r['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                       ($r['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
                ?>">
                <?php echo $r['status'] === 'pending' ? 'Ожидает' : ($r['status'] === 'approved' ? 'Одобрен' : 'Отклонён'); ?>
              </div>
            </div>

            <div class="bg-gray-50 p-4 border-t border-gray-100">
              <form method="POST" class="flex gap-2">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button name="action" value="approve" class="flex-1 bg-green-600 text-white rounded-lg py-2 hover:bg-green-700 transition flex items-center justify-center gap-1" title="Одобрить">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>Одобрить</span>
                </button>
                <button name="action" value="reject" class="flex-1 bg-yellow-500 text-white rounded-lg py-2 hover:bg-yellow-600 transition flex items-center justify-center gap-1" title="Отклонить">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  <span>Отклонить</span>
                </button>
                <button type="button" onclick="confirmDelete(<?php echo $r['id']; ?>)" class="flex-1 bg-red-600 text-white rounded-lg py-2 hover:bg-red-700 transition flex items-center justify-center gap-1" title="Удалить">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Пагинация -->
      <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-8">
          <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
              <a href="?page=<?php echo $page - 1; ?>&status=<?php echo htmlspecialchars($statusFilter); ?>" class="px-4 py-2 rounded-lg bg-white shadow-sm border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                &larr; Назад
              </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
              <a href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars($statusFilter); ?>" class="px-4 py-2 rounded-lg <?php echo $i == $page ? 'bg-[#118568] text-white' : 'bg-white shadow-sm border border-gray-200 text-gray-700 hover:bg-gray-50'; ?> transition">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <a href="?page=<?php echo $page + 1; ?>&status=<?php echo htmlspecialchars($statusFilter); ?>" class="px-4 py-2 rounded-lg bg-white shadow-sm border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                Вперёд &rarr;
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<script>
function confirmDelete(id) {
    if (confirm('Вы уверены, что хотите удалить этот отзыв?')) {
        // Создаём форму и отправляем POST-запрос
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        document.body.appendChild(form);

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);

        form.submit();
    }
}
</script>

<?php include_once('../includes/footer.php'); ?>