<?php
session_start();
$pageTitle = "Управление скидками";
include_once('../includes/header.php');

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}
// Подключение к базе данных
include_once('../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $discount_value = $_POST['discount_value'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $stmt = $pdo->prepare("INSERT INTO discounts (product_id, discount_value, start_date, end_date) VALUES (?, ?, ?, ?)");
    $stmt->execute([$product_id, $discount_value, $start_date, $end_date]);
}

// Получение скидок
$stmt = $pdo->prepare("SELECT d.*, p.name AS product_name FROM discounts d JOIN products p ON d.product_id = p.id");
$stmt->execute();
$discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление скидками</h1>

  <!-- Форма добавления скидки -->
  <form action="" method="POST" class="mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <select name="product_id" class="w-full px-4 py-2 border rounded-lg" required>
        <option value="">Выберите товар</option>
        <?php
        $stmt = $pdo->query("SELECT id, name FROM products");
        while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<option value="' . $product['id'] . '">' . htmlspecialchars($product['name']) . '</option>';
        }
        ?>
      </select>
      <input type="number" step="0.01" name="discount_value" placeholder="Скидка (%)" class="w-full px-4 py-2 border rounded-lg" required>
      <p>Дата начала</p><p>Дата окончания</p>
      <input type="date" name="start_date" placeholder="Дата начала" class="w-full px-4 py-2 border rounded-lg" required>
      <input type="date" name="end_date" placeholder="Дата окончания" class="w-full px-4 py-2 border rounded-lg" required>
    </div>
    <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
      Добавить скидку
    </button>
  </form>

  <!-- Список скидок -->
  <div class="grid grid-cols-1 gap-4">
    <?php foreach ($discounts as $discount): ?>
      <div class="bg-white p-4 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($discount['product_name']); ?></h2>
        <p class="text-gray-600">Скидка: <?php echo htmlspecialchars($discount['discount_value']); ?>%</p>
        <p class="text-gray-600">Период: <?php echo htmlspecialchars($discount['start_date']); ?> - <?php echo htmlspecialchars($discount['end_date']); ?></p>
        <div class="flex justify-end mt-4">
          <form action="/admin/discount/delete" method="POST" onsubmit="return confirm('Вы уверены?')">
            <input type="hidden" name="discount_id" value="<?php echo $discount['id']; ?>">
            <button type="submit" class="text-red-600 hover:text-red-800">Удалить</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>