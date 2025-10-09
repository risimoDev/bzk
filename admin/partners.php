<?php
session_start();
$pageTitle = "Управление партнерами";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  if (isset($_FILES['logo']) && isset($_POST['name'])) {
    $name = trim($_POST['name']);
    $logo = $_FILES['logo'];

    if (!empty($name)) {
      // Проверка загрузки файла
      if ($logo['error'] === UPLOAD_ERR_OK) {
        // Проверка типа файла
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($logo['tmp_name']);

        if (in_array($file_type, $allowed_types)) {
          $uploadDir = __DIR__ . '/../uploads/';
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
          }

          // Генерация уникального имени файла
          $file_extension = pathinfo($logo['name'], PATHINFO_EXTENSION);
          $fileName = 'partner_' . uniqid() . '_' . time() . '.' . $file_extension;
          $filePath = $uploadDir . $fileName;

          if (move_uploaded_file($logo['tmp_name'], $filePath)) {
            $logoUrl = '/uploads/' . $fileName;

            // Сохранение логотипа партнера в базу данных
            $stmt = $pdo->prepare("INSERT INTO partners (name, logo_url) VALUES (?, ?)");
            $result = $stmt->execute([$name, $logoUrl]);

            if ($result) {
              $_SESSION['notifications'][] = [
                'type' => 'success',
                'message' => 'Партнер успешно добавлен.'
              ];
            } else {
              $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Ошибка при сохранении партнера в базу данных.'
              ];
              // Удаляем файл в случае ошибки базы данных
              unlink($filePath);
            }
          } else {
            $_SESSION['notifications'][] = [
              'type' => 'error',
              'message' => 'Ошибка при перемещении файла.'
            ];
          }
        } else {
          $_SESSION['notifications'][] = [
            'type' => 'error',
            'message' => 'Недопустимый формат файла. Разрешены только JPG, PNG, GIF, WEBP.'
          ];
        }
      } else {
        $_SESSION['notifications'][] = [
          'type' => 'error',
          'message' => 'Ошибка загрузки файла.'
        ];
      }
    } else {
      $_SESSION['notifications'][] = [
        'type' => 'error',
        'message' => 'Пожалуйста, заполните название партнера.'
      ];
    }

    header("Location: /admin/partners");
    exit();

  } elseif (isset($_POST['delete_partner_id'])) {
    $partner_id = $_POST['delete_partner_id'];

    // Удаление логотипа из файловой системы
    $stmt = $pdo->prepare("SELECT logo_url FROM partners WHERE id = ?");
    $stmt->execute([$partner_id]);
    $logoUrl = $stmt->fetchColumn();

    // Удаление записи из базы данных
    $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
    $result = $stmt->execute([$partner_id]);

    if ($result) {
      if ($logoUrl && file_exists(__DIR__ . '/../' . $logoUrl)) {
        unlink(__DIR__ . '/../' . $logoUrl);
      }

      $_SESSION['notifications'][] = [
        'type' => 'success',
        'message' => 'Партнер успешно удален.'
      ];
    } else {
      $_SESSION['notifications'][] = [
        'type' => 'error',
        'message' => 'Ошибка при удалении партнера.'
      ];
    }

    header("Location: /admin/partners");
    exit();
  }
}

// Получение списка партнеров
$stmt = $pdo->query("SELECT * FROM partners ORDER BY id DESC");
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$total_partners = count($partners);
?>



<!-- Шапка -->
<?php include_once('../includes/header.php'); ?>

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
    <h1 class="text-4xl font-bold text-gray-800 mb-4">Управление партнерами</h1>
    <p class="text-xl text-gray-700">Всего партнеров: <?php echo $total_partners; ?></p>
    <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
  </div>

  <!-- Уведомления -->
  <?php foreach ($notifications as $notification): ?>
    <div
      class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
      <?php echo htmlspecialchars($notification['message']); ?>
    </div>
  <?php endforeach; ?>

  <!-- Статистика -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
      <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
          stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </div>
      <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo $total_partners; ?></div>
      <div class="text-gray-600">Всего партнеров</div>
    </div>

    <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
      <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
          stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
      </div>
      <div class="text-3xl font-bold text-[#17B890] mb-2">12</div>
      <div class="text-gray-600">Колонок на главной</div>
    </div>

    <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
      <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
          stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
      <div class="text-3xl font-bold text-[#5E807F] mb-2">200x100</div>
      <div class="text-gray-600">Рекомендуемый размер</div>
    </div>
  </div>

  <!-- Форма добавления партнера -->
  <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Добавить нового партнера</h2>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
      <?php echo csrf_field(); ?>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>
          <label for="name" class="block text-gray-700 font-medium mb-2">Название партнера *</label>
          <input type="text" id="name" name="name"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
            placeholder="Введите название компании" required>
        </div>

        <div>
          <label class="block text-gray-700 font-medium mb-2">Логотип партнера *</label>
          <div
            class="border-2 border-dashed border-[#118568] rounded-2xl p-8 text-center hover:bg-[#f8fafa] transition-colors duration-300">
            <div class="w-16 h-16 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#118568]" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <p class="text-gray-600 mb-4">Перетащите изображение сюда или нажмите для выбора</p>
            <input type="file" name="logo" accept="image/*" class="hidden" id="logo-upload" required>
            <label for="logo-upload"
              class="inline-block px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 cursor-pointer">
              Выбрать файл
            </label>
            <p class="text-sm text-gray-500 mt-2">Поддерживаемые форматы: JPG, PNG, GIF, WEBP</p>
          </div>
        </div>
      </div>

      <button type="submit"
        class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
        Добавить партнера
      </button>
    </form>
  </div>

  <!-- Список партнеров -->
  <?php if (empty($partners)): ?>
    <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
      <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#5E807F]" fill="none" viewBox="0 0 24 24"
          stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </div>
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Партнеры не найдены</h2>
      <p class="text-gray-600 mb-8">Добавьте первого партнера, используя форму выше</p>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-3xl shadow-2xl p-6">
      <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Список партнеров</h2>
        <div class="text-gray-600">
          Найдено: <?php echo count($partners); ?>
          <?php echo count($partners) == 1 ? 'партнер' : (count($partners) < 5 ? 'партнера' : 'партнеров'); ?>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($partners as $partner): ?>
          <div
            class="bg-gray-50 rounded-2xl overflow-hidden hover:bg-white hover:shadow-xl transition-all duration-300 group">
            <div class="relative">
              <img src="<?php echo htmlspecialchars($partner['logo_url']); ?>"
                alt="<?php echo htmlspecialchars($partner['name']); ?>" class="w-full h-40 object-contain bg-white p-4">

              <div
                class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <form action="" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить этого партнера?')"
                  class="m-0">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="delete_partner_id" value="<?php echo $partner['id']; ?>">
                  <button type="submit"
                    class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 flex items-center"
                    title="Удалить">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24"
                      stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Удалить
                  </button>
                </form>
              </div>
            </div>

            <div class="p-4">
              <h3
                class="text-lg font-bold text-gray-800 text-center group-hover:text-[#118568] transition-colors duration-300">
                <?php echo htmlspecialchars($partner['name']); ?>
              </h3>
              <div class="text-center text-sm text-gray-500 mt-2">
                ID: <?php echo $partner['id']; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</main>

<!-- Футер -->
<?php include_once('../includes/footer.php'); ?>

</body>

</html>