<?php
session_start();
require_once '../includes/security.php';
require_once '../includes/common.php'; // Подключаем common.php для вспомогательных функций, если нужно
$pageTitle = "Управление изображениями";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../includes/db.php');

$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    die("Товар не выбран.");
}

// Получение информации о товаре
$stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Товар не найден.");
}

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
}

// Добавление изображения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    verify_csrf();
    
    $action = $_POST['action'];
    
    if ($action === 'upload_image' && isset($_FILES['image'])) {
        $uploaded_files = $_FILES['image'];

        // Проверяем, был ли загружен хотя бы один файл
        if (empty($uploaded_files['name'][0])) {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Пожалуйста, выберите хотя бы одно изображение для загрузки.'
            ];
            header("Location: /admin/images?product_id=$product_id");
            exit();
        }

        $upload_errors = [];
        $upload_success_count = 0;
        $is_first_upload_overall = false; // Флаг для определения первого загруженного изображения (всего для товара)

        // Проверяем, есть ли уже *какие-либо* изображения для этого товара в БД перед началом цикла
        $stmt_check_any = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
        $stmt_check_any->execute([$product_id]);
        $has_any_images = $stmt_check_any->fetchColumn() > 0;

        // Проходим по каждому загруженному файлу
        $file_count = count($uploaded_files['name']);
        for ($i = 0; $i < $file_count; $i++) {
            // Создаем временный массив для текущего файла
            $current_file = [
                'name' => $uploaded_files['name'][$i],
                'type' => $uploaded_files['type'][$i],
                'tmp_name' => $uploaded_files['tmp_name'][$i],
                'error' => $uploaded_files['error'][$i],
                'size' => $uploaded_files['size'][$i],
            ];

            // Используем secure_file_upload для каждого файла
            $upload_result = secure_file_upload($current_file, [
                'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'max_size' => 10 * 1024 * 1024, // 10MB
                'upload_dir' => __DIR__ . '/../storage/uploads/',
                'public_dir' => '/storage/uploads/',
                'filename_prefix' => 'product_' . $product_id . '_'
            ]);

            if ($upload_result['success']) {
                $original_file_path = $upload_result['full_path'];
                $original_public_url = $upload_result['public_url'];

                // --- НОВОЕ: Конвертация в WebP ---
                $webp_file_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $original_file_path);
                $webp_public_url = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $original_public_url);

                $image_info = getimagesize($original_file_path);
                if ($image_info === false) {
                    $upload_errors[] = 'Файл ' . htmlspecialchars($current_file['name']) . ' не является изображением (getimagesize).';
                    if (file_exists($original_file_path)) {
                        unlink($original_file_path);
                    }
                    continue; // Переходим к следующему файлу в цикле for (continue 1)
                }

                $image_type = $image_info[2];
                $image = null;
                $image_creation_failed = false; // Флаг для обозначения ошибки создания ресурса

                switch ($image_type) {
                    case IMAGETYPE_JPEG:
                        $image = imagecreatefromjpeg($original_file_path);
                        if ($image === false) {
                            $upload_errors[] = 'Файл ' . htmlspecialchars($current_file['name']) . ' не является корректным JPEG изображением.';
                            $image_creation_failed = true;
                        }
                        break; // Важно: break, чтобы не проваливаться дальше
                    case IMAGETYPE_PNG:
                        $image = imagecreatefrompng($original_file_path);
                        if ($image === false) {
                            $upload_errors[] = 'Файл ' . htmlspecialchars($current_file['name']) . ' не является корректным PNG изображением.';
                            $image_creation_failed = true;
                        } else {
                            // Preserve transparency for PNG
                            imagepalettetotruecolor($image);
                            imagealphablending($image, false);
                            imagesavealpha($image, true);
                        }
                        break; // Важно: break
                    case IMAGETYPE_GIF:
                        $image = imagecreatefromgif($original_file_path);
                        if ($image === false) {
                            $upload_errors[] = 'Файл ' . htmlspecialchars($current_file['name']) . ' не является корректным GIF изображением.';
                            $image_creation_failed = true;
                        } else {
                            // Preserve transparency for GIF
                            imagepalettetotruecolor($image);
                            imagealphablending($image, false);
                            imagesavealpha($image, true);
                        }
                        break; // Важно: break
                    default:
                        $upload_errors[] = 'Неподдерживаемый или неопознанный формат изображения для конвертации в WebP: ' . htmlspecialchars($current_file['name']);
                        $image_creation_failed = true; // Отмечаем ошибку
                        break; // break для default также важен
                }

                // Проверяем, была ли ошибка создания ресурса или тип не поддерживается
                if ($image_creation_failed) {
                     if (file_exists($original_file_path)) {
                         unlink($original_file_path);
                     }
                     continue; // Переходим к следующему файлу в цикле for
                }

                // Если $image не создан успешно, $image_creation_failed должен быть true, и мы выше ушли на continue.
                // Если $image создан, продолжаем конвертацию.
                if ($image !== false) {
                    $success = imagewebp($image, $webp_file_path, 80);
                    imagedestroy($image); // Уничтожаем ресурс после использования

                    if ($success) {
                        if (file_exists($original_file_path)) {
                            unlink($original_file_path);
                        }
                        $imageUrl = $webp_public_url;
                    } else {
                        $upload_errors[] = 'Ошибка конвертации в WebP для файла: ' . htmlspecialchars($current_file['name']);
                        if (file_exists($original_file_path)) {
                            unlink($original_file_path);
                        }
                        continue; // Переходим к следующему файлу в цикле for
                    }
                } else {
                     // Этот else理论上 не должен сработать, если логика выше верна
                     // Но на всякий случай, если $image как-то стал false не через $image_creation_failed
                     $upload_errors[] = 'Ошибка обработки изображения (неизвестная ошибка): ' . htmlspecialchars($current_file['name']);
                     if (file_exists($original_file_path)) {
                         unlink($original_file_path);
                     }
                     continue; // Переходим к следующему файлу в цикле for
                }
                // --- КОНЕЦ НОВОГО: Конвертация в WebP ---

                // Определяем, нужно ли установить is_main
                // Первое изображение *вообще* для товара становится главным.
                // Если при начале загрузки уже были изображения, ни одно из новых не станет главным.
                // Если при начале загрузки не было изображений, первое успешно обработанное станет главным.
                $is_main = 0;
                if (!$has_any_images && $upload_success_count === 0) {
                     $is_main = 1; // Первое изображение из пакета, если до этого не было изображений
                     $upload_success_count++; // Увеличиваем счетчик только после успешного сохранения
                }

                // Сохранение пути к WebP изображению в базу данных
                $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, ?)");
                $result = $stmt->execute([$product_id, $imageUrl, $is_main]);

                if ($result) {
                    if ($has_any_images || $is_main !== 1) { // Если уже были изображения ИЛИ текущее не стало главным
                        $upload_success_count++; // Увеличиваем счетчик успешных загрузок
                    }
                    // (Если $is_main === 1, то $upload_success_count уже увеличен выше)
                } else {
                    $upload_errors[] = 'Ошибка при сохранении изображения в базу данных: ' . htmlspecialchars($current_file['name']);
                    // Удаляем WebP файл в случае ошибки базы данных
                    if (file_exists($webp_file_path)) {
                        unlink($webp_file_path);
                    }
                }
            } else {
                $upload_errors[] = 'Ошибка загрузки файла ' . htmlspecialchars($current_file['name']) . ': ' . $upload_result['error'];
            }
        } // Конец цикла for

        // Формируем итоговое уведомление
        if ($upload_success_count > 0) {
            $_SESSION['notifications'][] = [
                'type' => 'success',
                'message' => "Успешно загружено и сконвертировано {$upload_success_count} изображений(е) в WebP."
            ];
        }
        if (!empty($upload_errors)) {
            foreach ($upload_errors as $error) {
                $_SESSION['notifications'][] = [
                    'type' => 'error',
                    'message' => $error
                ];
            }
        }

        header("Location: /admin/images?product_id=$product_id");
        exit();
    } elseif ($action === 'delete_image') {
        $image_id = $_POST['image_id'];
        
        // Проверяем, принадлежит ли изображение текущему товару
        $stmt = $pdo->prepare("SELECT product_id, image_url, is_main FROM product_images WHERE id = ?");
        $stmt->execute([$image_id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image && $image['product_id'] == $product_id) {
            // Удаляем запись из базы данных
            $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
            $result = $stmt->execute([$image_id]);
            
            if ($result) {
                // Удаляем файл с сервера
                $file_path = __DIR__ . '/..' . $image['image_url'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Если удалили главное изображение, делаем следующее изображение главным
                if ($image['is_main']) {
                    $stmt = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? LIMIT 1");
                    $stmt->execute([$product_id]);
                    $next_image = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($next_image) {
                        $stmt = $pdo->prepare("UPDATE product_images SET is_main = 1 WHERE id = ?");
                        $stmt->execute([$next_image['id']]);
                    }
                }
                
                $_SESSION['notifications'][] = [
                    'type' => 'success',
                    'message' => 'Изображение успешно удалено.'
                ];
            } else {
                $_SESSION['notifications'][] = [
                    'type' => 'error',
                    'message' => 'Ошибка при удалении изображения.'
                ];
            }
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Недостаточно прав для удаления этого изображения.'
            ];
        }
        
        header("Location: /admin/images?product_id=$product_id");
        exit();
        
    } elseif ($action === 'set_main_image') {
        $image_id = $_POST['image_id'];
        
        // Проверяем, принадлежит ли изображение текущему товару
        $stmt = $pdo->prepare("SELECT product_id FROM product_images WHERE id = ?");
        $stmt->execute([$image_id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image && $image['product_id'] == $product_id) {
            // Сначала сбрасываем все главные изображения для этого товара
            $stmt = $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            // Устанавливаем выбранное изображение как главное
            $stmt = $pdo->prepare("UPDATE product_images SET is_main = 1 WHERE id = ?");
            $result = $stmt->execute([$image_id]);
            
            if ($result) {
                $_SESSION['notifications'][] = [
                    'type' => 'success',
                    'message' => 'Главное изображение успешно установлено.'
                ];
            } else {
                $_SESSION['notifications'][] = [
                    'type' => 'error',
                    'message' => 'Ошибка при установке главного изображения.'
                ];
            }
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Недостаточно прав для изменения этого изображения.'
            ];
        }
        
        header("Location: /admin/images?product_id=$product_id");
        exit();
    }
}

// Получение изображений товара
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$total_images = count($images);
$main_images = array_filter($images, function($img) { return $img['is_main'] == 1; });
?>

<?php include_once('../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin/products" class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">
          Все товары
        </a>
      </div>
    </div>

    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Изображения товара</h1>
      <p class="text-xl text-gray-700"><?php echo htmlspecialchars($product['name']); ?></p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Уведомления -->
    <?php foreach ($notifications as $notification): ?>
      <div class="mb-6 p-4 rounded-xl <?php echo $notification['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <?php echo htmlspecialchars($notification['message']); ?>
      </div>
    <?php endforeach; ?>

    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo $total_images; ?></div>
        <div class="text-gray-600">Всего изображений</div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#17B890] mb-2"><?php echo count($main_images); ?></div>
        <div class="text-gray-600">Главных изображений</div>
      </div>
    </div>

      <!-- Форма добавления изображения -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Загрузить новое изображение</h2>
      
      <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="upload_image">
        
        <div id="upload-area" class="border-2 border-dashed border-[#118568] rounded-2xl p-8 text-center hover:bg-[#f8fafa] transition-colors duration-300 relative">
          <!-- Контейнер для предварительного просмотра -->
          <div id="preview-container" class="mb-4 flex flex-wrap gap-2 justify-center">
            <!-- Превью изображений будут добавляться сюда -->
          </div>
          
          <div class="w-16 h-16 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
          </div>
          <p class="text-gray-600 mb-4">Перетащите изображения сюда или нажмите для выбора</p>
          <input type="file" name="image[]" accept="image/*"
                 class="hidden" id="image-upload" required multiple> <!-- Добавлен multiple -->
          <label for="image-upload" 
                 class="inline-block px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 cursor-pointer">
            Выбрать файл(ы)
          </label>
          <p class="text-sm text-gray-500 mt-2">Поддерживаемые форматы: JPG, PNG, GIF, WEBP</p>
        </div>
        
        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Загрузить
        </button>
      </form>
    </div>

    <!-- Список изображений -->
    <?php if (empty($images)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Изображения не найдены</h2>
        <p class="text-gray-600 mb-8">Загрузите первое изображение, используя форму выше</p>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-3xl shadow-2xl p-6">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
          <h2 class="text-2xl font-bold text-gray-800">Список изображений</h2>
          <div class="text-gray-600">
            Найдено: <?php echo count($images); ?> <?php echo count($images) == 1 ? 'изображение' : (count($images) < 5 ? 'изображения' : 'изображений'); ?>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <?php foreach ($images as $image): ?>
            <div class="bg-gray-50 rounded-2xl overflow-hidden hover:bg-white hover:shadow-xl transition-all duration-300 group <?php echo $image['is_main'] ? 'ring-4 ring-[#17B890]' : ''; ?>"> <!-- Добавлен стиль для главного изображения -->
              <div class="relative">
                <img src="<?php echo htmlspecialchars($image['image_url']); ?>" 
                     alt="Изображение товара" 
                     class="w-full h-48 object-cover">
                
                <?php if ($image['is_main']): ?>
                  <div class="absolute top-3 left-3 px-3 py-1 bg-[#17B890] text-white text-xs font-bold rounded-full flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    Главное
                  </div>
                <?php endif; ?>
                
                <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <div class="flex gap-2">
                    <?php if (!$image['is_main']): ?>
                      <form action="" method="POST" class="m-0">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="set_main_image">
                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                        <button type="submit" 
                                class="px-3 py-2 bg-[#17B890] text-white rounded-lg hover:bg-[#14a380] transition-colors duration-300 text-sm"
                                title="Сделать главным">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                          </svg>
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <form action="" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить это изображение?')" class="m-0">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_image">
                      <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                      <button type="submit" 
                              class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 text-sm"
                              title="Удалить">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
              
              <div class="p-4">
                <div class="flex justify-between items-center">
                  <div class="text-sm text-gray-600">
                    ID: <?php echo $image['id']; ?>
                  </div>
                  <?php if ($image['is_main']): ?>
                    <span class="px-2 py-1 bg-[#17B890] text-white text-xs rounded-full">
                      Главное
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('image-upload');
    const uploadArea = document.getElementById('upload-area');
    const previewContainer = document.getElementById('preview-container');

    // Функция для отображения превью
    function showPreview(files) {
        previewContainer.innerHTML = ''; // Очистить предыдущие превью
        previewContainer.classList.remove('hidden');
        uploadArea.classList.add('border-[#17B890]', 'bg-[#f0f9f7]');

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = "Превью " + (i+1);
                    img.className = "w-16 h-16 object-cover rounded-lg border border-gray-300 shadow-sm"; // Компактное превью
                    previewContainer.appendChild(img);
                }

                reader.readAsDataURL(file);
            }
        }
    }

    // Функция для скрытия превью
    function hidePreview() {
        previewContainer.innerHTML = ''; // Очистить контейнер
        previewContainer.classList.add('hidden');
        uploadArea.classList.remove('border-[#17B890]', 'bg-[#f0f9f7]');
    }

    // Слушатель на изменение input (выбор файла через диалог)
    fileInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files.length > 0) {
            showPreview(e.target.files);
        } else {
            hidePreview();
        }
    });

    // Слушатель для drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight(e) {
        uploadArea.classList.add('border-[#17B890]', 'bg-[#f0f9f7]');
    }

    function unhighlight(e) {
        // Не убираем подсветку, если файлы уже выбраны
        if (!fileInput.files.length) {
            uploadArea.classList.remove('border-[#17B890]', 'bg-[#f0f9f7]');
        }
    }

    uploadArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        if (dt.files.length > 0 && Array.from(dt.files).every(f => f.type.startsWith('image/'))) {
            fileInput.files = dt.files; // Обновляем файлы в input
            showPreview(dt.files);
        } else {
            // Показать сообщение об ошибке или скрыть превью
            alert('Пожалуйста, перетащите только изображения.');
            hidePreview();
        }
    }

});
</script>

<?php include_once('../includes/footer.php'); ?>