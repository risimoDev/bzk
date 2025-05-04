<?php
session_start();
$pageTitle = "Управление характеристиками | Админ-панель";
include_once('../includes/header.php');

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');


$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    die("Товар не выбран.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $values = $_POST['values'];

    // Добавление характеристики
    $stmt = $pdo->prepare("INSERT INTO product_attributes (product_id, name, type) VALUES (?, ?, ?)");
    $stmt->execute([$product_id, $name, $type]);

    $attribute_id = $pdo->lastInsertId();

    // Добавление значений характеристики
    foreach ($values as $value) {
        $stmt = $pdo->prepare("INSERT INTO attribute_values (attribute_id, value, price_modifier) VALUES (?, ?, ?)");
        $stmt->execute([$attribute_id, $value['value'], $value['price_modifier']]);
    }

    header("Location: /admin/attributes?product_id=$product_id");
    exit();
}

// Получение характеристик
$stmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ?");
$stmt->execute([$product_id]);
$attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление характеристиками</h1>

  <!-- Форма добавления характеристики -->
  <form action="" method="POST" class="mb-6">
    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="text" name="name" placeholder="Название характеристики" class="w-full px-4 py-2 border rounded-lg" required>
      <select name="type" class="w-full px-4 py-2 border rounded-lg">
        <option value="radio">Radio кнопки</option>
        <option value="select">Выпадающий список</option>
        <option value="text">Текстовое поле</option>
      </select>
    </div>

    <div id="values-container" class="mt-4">
      <label>Значения характеристики:</label>
      <div class="value-item flex space-x-2">
        <input type="text" name="values[0][value]" placeholder="Значение" class="w-full px-4 py-2 border rounded-lg" required>
        <input type="number" step="0.01" name="values[0][price_modifier]" placeholder="Модификатор цены" class="w-full px-4 py-2 border rounded-lg">
      </div>
    </div>

    <button type="button" id="add-value" class="mt-2 text-blue-600 hover:text-blue-800">Добавить значение</button>
    <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
      Добавить характеристику
    </button>
  </form>

  <!-- Список характеристик -->
  <div class="grid grid-cols-1 gap-4 mt-6">
    <?php foreach ($attributes as $attribute): ?>
      <div class="bg-white p-4 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($attribute['name']); ?></h2>
        <p class="text-gray-600">Тип: <?php echo htmlspecialchars($attribute['type']); ?></p>
        <div class="flex justify-end mt-4">
          <a href="/admin/attribute/edit?id=<?php echo $attribute['id']; ?>" class="text-blue-600 hover:text-blue-800">Редактировать</a>
          <form action="/admin/attribute/delete" method="POST" onsubmit="return confirm('Вы уверены?')" class="ml-4">
            <input type="hidden" name="attribute_id" value="<?php echo $attribute['id']; ?>">
            <button type="submit" class="text-red-600 hover:text-red-800">Удалить</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<script>
  document.getElementById('add-value').addEventListener('click', function () {
    const container = document.getElementById('values-container');
    const index = container.children.length;

    const div = document.createElement('div');
    div.className = 'value-item flex space-x-2';
    div.innerHTML = `
      <input type="text" name="values[${index}][value]" placeholder="Значение" class="w-full px-4 py-2 border rounded-lg" required>
      <input type="number" step="0.01" name="values[${index}][price_modifier]" placeholder="Модификатор цены" class="w-full px-4 py-2 border rounded-lg">
    `;
    container.appendChild(div);
  });
</script>

<?php include_once('../includes/footer.php'); ?>