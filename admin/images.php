<?php
session_start();
require_once '../includes/security.php';
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
        $image = $_FILES['image'];
        
        // Use secure file upload
        $upload_result = secure_file_upload($image, [
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 10 * 1024 * 1024, // 10MB
            'upload_dir' => __DIR__ . '/../storage/uploads/',
            'public_dir' => '/storage/uploads/',
            'filename_prefix' => 'product_' . $product_id . '_'
        ]);
        
        if ($upload_result['success']) {
            $imageUrl = $upload_result['public_url'];
            
            // Проверяем, есть ли уже главное изображение
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_main = 1");
            $stmt_check->execute([$product_id]);
            $has_main = $stmt_check->fetchColumn();
            
            $is_main = $has_main ? 0 : 1; // Первое изображение становится главным
            
            // Сохранение пути к изображению в базу данных
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, ?)");
            $result = $stmt->execute([$product_id, $imageUrl, $is_main]);
            
            if ($result) {
                $_SESSION['notifications'][] = [
                    'type' => 'success',
                    'message' => 'Изображение успешно загружено.'
                ];
            } else {
                $_SESSION['notifications'][] = [
                    'type' => 'error',
                    'message' => 'Ошибка при сохранении изображения в базу данных.'
                ];
                // Удаляем файл в случае ошибки базы данных
                if (file_exists($upload_result['full_path'])) {
                    unlink($upload_result['full_path']);
                }
            }
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Ошибка загрузки: ' . $upload_result['error']
            ];
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
        
        <div class="border-2 border-dashed border-[#118568] rounded-2xl p-8 text-center hover:bg-[#f8fafa] transition-colors duration-300">
          <div class="w-16 h-16 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#118568]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
          </div>
          <p class="text-gray-600 mb-4">Перетащите изображение сюда или нажмите для выбора</p>
          <input type="file" name="image" accept="image/*" 
                 class="hidden" id="image-upload" required>
          <label for="image-upload" 
                 class="inline-block px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 cursor-pointer">
            Выбрать файл
          </label>
          <p class="text-sm text-gray-500 mt-2">Поддерживаемые форматы: JPG, PNG, GIF, WEBP</p>
        </div>
        
        <button type="submit" 
                class="w-full bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
          Загрузить изображение
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
            <div class="bg-gray-50 rounded-2xl overflow-hidden hover:bg-white hover:shadow-xl transition-all duration-300 group">
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

<?php include_once('../includes/footer.php'); ?>