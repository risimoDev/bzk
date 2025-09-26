<?php
session_start();
$pageTitle = "Управление поставщиками";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');
include_once('../../includes/security.php');
include_once('../../includes/common.php');

// ---------- Пагинация ----------
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// ---------- Получение поставщиков ----------
$stmt = $pdo->prepare("SELECT * FROM suppliers ORDER BY name ASC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$suppliers = $stmt->fetchAll();

// ---------- Общее количество поставщиков ----------
$total_suppliers_stmt = $pdo->query("SELECT COUNT(*) FROM suppliers");
$total_suppliers = $total_suppliers_stmt->fetchColumn();
$total_pages = ceil($total_suppliers / $limit);
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- breadcrumbs + назад -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div><?php echo generateBreadcrumbs($pageTitle ?? ''); ?></div>
      <div class="flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin/suppliers/add" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 font-medium flex items-center">
          <i class="fas fa-plus mr-2"></i>Добавить поставщика
        </a>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление поставщиками</h1>
      <p class="text-xl text-gray-700">Всего поставщиков: <?php echo $total_suppliers; ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- уведомления -->
    <?php echo display_notifications(); ?>

    <!-- список поставщиков -->
    <?php if (empty($suppliers)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <i class="fas fa-truck text-4xl text-[#5E807F]"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Поставщики не найдены</h2>
        <p class="text-gray-600 mb-8">Добавьте первого поставщика, чтобы начать работу</p>
        <a href="/admin/suppliers/add" class="px-6 py-3 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Добавить поставщика
        </a>
      </div>
    <?php else: ?>
      <!-- Список поставщиков в виде карточек -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
        <?php foreach ($suppliers as $supplier): ?>
          <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 flex flex-col h-full">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-lg font-bold text-gray-800"><?php echo e($supplier['name']); ?></h3>
                <?php if (!empty($supplier['contact_person'])): ?>
                  <p class="text-sm text-gray-600 mt-1"><?php echo e($supplier['contact_person']); ?></p>
                <?php endif; ?>
              </div>
              <span class="px-2 py-1 text-xs rounded-full <?php echo $supplier['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $supplier['is_active'] ? 'Активен' : 'Неактивен'; ?>
              </span>
            </div>
            
            <div class="space-y-3 flex-grow">
              <?php if (!empty($supplier['phone'])): ?>
                <div class="flex items-center text-sm">
                  <i class="fas fa-phone mr-2 text-[#118568]"></i>
                  <span><?php echo e($supplier['phone']); ?></span>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($supplier['email'])): ?>
                <div class="flex items-center text-sm">
                  <i class="fas fa-envelope mr-2 text-[#118568]"></i>
                  <span><?php echo e($supplier['email']); ?></span>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($supplier['website'])): ?>
                <div class="flex items-center text-sm">
                  <i class="fas fa-globe mr-2 text-[#118568]"></i>
                  <span class="text-[#118568] hover:underline truncate"><?php echo e($supplier['website']); ?></span>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($supplier['service_cost'])): ?>
                <div class="flex items-center text-sm">
                  <i class="fas fa-ruble-sign mr-2 text-[#118568]"></i>
                  <span>Стоимость услуг: <?php echo number_format($supplier['service_cost'], 2, '.', ' '); ?> руб.</span>
                </div>
              <?php endif; ?>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-100 flex space-x-2">
              <a href="/admin/suppliers/details?id=<?php echo $supplier['id']; ?>" class="flex-1 text-center px-3 py-2 bg-[#118568] text-white text-sm rounded-lg hover:bg-[#0f755a] transition">
                Подробнее
              </a>
              <a href="/admin/suppliers/edit?id=<?php echo $supplier['id']; ?>" class="flex-1 text-center px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 transition">
                Редактировать
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <!-- пагинация -->
      <?php if ($total_pages > 1): ?>
        <div class="flex justify-center">
          <div class="bg-white rounded-2xl shadow-lg px-6 py-4 flex items-center space-x-2">
            <?php if ($page > 1): ?>
              <a href="?page=<?php echo $page - 1; ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5]">
                ←
              </a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
              <a href="?page=<?php echo $i; ?>" class="w-10 h-10 flex items-center justify-center rounded-lg <?php echo $i == $page ? 'bg-[#118568] text-white' : 'hover:bg-[#DEE5E5]'; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
              <a href="?page=<?php echo $page + 1; ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-[#DEE5E5]">
                →
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<?php include_once('../../includes/footer.php'); ?>