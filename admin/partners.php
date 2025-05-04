<?php
session_start();
$pageTitle = "Управление партнерами | Админ-панель";
include_once('../includes/header.php');

// Подключение к базе данных
include_once('../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['logo']) && isset($_POST['name'])) {
        $name = $_POST['name'];
        $logo = $_FILES['logo'];

        // Проверка загрузки файла
        if ($logo['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid() . '_' . basename($logo['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($logo['tmp_name'], $filePath)) {
                $logoUrl = '/uploads/' . $fileName;

                // Сохранение логотипа партнера в базу данных
                $stmt = $pdo->prepare("INSERT INTO partners (name, logo_url) VALUES (?, ?)");
                $stmt->execute([$name, $logoUrl]);
            }
        }
    } elseif (isset($_POST['delete_partner_id'])) {
        $partner_id = $_POST['delete_partner_id'];

        // Удаление логотипа из файловой системы
        $stmt = $pdo->prepare("SELECT logo_url FROM partners WHERE id = ?");
        $stmt->execute([$partner_id]);
        $logoUrl = $stmt->fetchColumn();

        if ($logoUrl && file_exists(__DIR__ . '/../' . $logoUrl)) {
            unlink(__DIR__ . '/../' . $logoUrl);
        }

        // Удаление записи из базы данных
        $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
        $stmt->execute([$partner_id]);
    }
}

// Получение списка партнеров
$stmt = $pdo->query("SELECT * FROM partners");
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Управление партнерами</h1>

  <!-- Форма добавления партнера -->
  <form action="" method="POST" enctype="multipart/form-data" class="mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="text" name="name" placeholder="Название партнера" class="w-full px-4 py-2 border rounded-lg" required>
      <input type="file" name="logo" accept="image/*" required>
    </div>
    <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">
      Добавить партнера
    </button>
  </form>

  <!-- Список партнеров -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($partners as $partner): ?>
      <div class="bg-white p-4 rounded-lg shadow-md">
        <img src="<?php echo htmlspecialchars($partner['logo_url']); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>" class="w-full h-32 object-contain rounded-t-lg">
        <h2 class="text-xl font-bold text-gray-800 mt-4"><?php echo htmlspecialchars($partner['name']); ?></h2>
        <form action="" method="POST" onsubmit="return confirm('Вы уверены?')" class="mt-4">
          <input type="hidden" name="delete_partner_id" value="<?php echo $partner['id']; ?>">
          <button type="submit" class="text-red-600 hover:text-red-800">Удалить</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>