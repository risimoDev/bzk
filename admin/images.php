<?php
session_start();
$pageTitle = "Управление изображениями | Админ-панель";
include_once('../includes/header.php');

// Подключение к базе данных
include_once('../includes/db.php');

$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    die("Товар не выбран.");
}

// Добавление изображения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $image = $_FILES['image'];

    // Проверка загрузки файла
    if ($image['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($image['name']);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($image['tmp_name'], $filePath)) {
            $imageUrl = '/uploads/' . $fileName;

            // Сохранение пути к изображению в базу данных
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
            $stmt->execute([$product_id, $imageUrl]);
        }
    }
}

// Получение изображений товара
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление изображениями</h1>

  <!-- Форма добавления изображения -->
  <form action="" method="POST" enctype="multipart/form-data" class="mb-6">
    <input type="file" name="image" accept="image/*" required>
    <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
      Загрузить изображение
    </button>
  </form>

  <!-- Список изображений -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($images as $image): ?>
      <div class="bg-white p-4 rounded-lg shadow-md">
        <img src="<?php echo htmlspecialchars($image['image_url']); ?>" alt="Изображение товара" class="w-full h-48 object-cover rounded-t-lg">
        <div class="flex justify-end mt-4">
          <form action="/admin/image/delete" method="POST" onsubmit="return confirm('Вы уверены?')">
            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
            <button type="submit" class="text-red-600 hover:text-red-800">Удалить</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>