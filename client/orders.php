<?php
session_start();
$pageTitle = "История заказов";
include_once('../includes/header.php');

// Подключение к базе данных
include_once('../includes/db.php');

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT o.id, o.total_price, o.status, o.created_at 
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Массив для перевода статусов
$statuses = [
  'pending' => 'В ожидании',
  'processing' => 'В обработке',
  'shipped' => 'Отправлен',
  'delivered' => 'Доставлен',
  'cancelled' => 'Отменен',
  'completed' => 'Полностью готов'
];
?>

<main class="container mx-auto px-4 py-8">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
<div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <!-- Breadcrumbs -->
    <div>
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
    </div>

    <!-- Кнопка "Назад" -->
    <div>
        <?php echo backButton(); ?>
    </div>
</div>
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">История заказов</h1>

  <?php if (empty($orders)): ?>
    <p class="text-center text-gray-600">У вас пока нет заказов.</p>
  <?php else: ?>
    <div class="grid grid-cols-1 gap-6">
      <?php foreach ($orders as $order): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
          <h2 class="text-xl font-bold text-gray-800">Заказ #<?php echo htmlspecialchars($order['id']); ?></h2>
          <p class="text-gray-600">Дата создания: <?php echo htmlspecialchars($order['created_at']); ?></p>
          <p class="text-gray-600">Общая стоимость: <?php echo htmlspecialchars($order['total_price']); ?> руб.</p>
          <p class="text-gray-600">Статус: <?php echo htmlspecialchars($statuses[$order['status']] ?? 'Неизвестно'); ?></p>
          <!--<a href="/client/order/details?id=<//?php echo $order['id']; ?>" class="block mt-4 text-blue-600 hover:text-blue-800">Подробнее</a>-->
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php include_once('../includes/footer.php'); ?>