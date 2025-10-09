<?php
session_start();
$pageTitle = "SEO-настройки";

// Проверка авторизации (только админ)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: /login");
  exit();
}

include_once('../../includes/db.php');
include_once('../../includes/security.php');

// Обработка уведомлений
$notifications = [];
if (isset($_SESSION['notifications'])) {
  $notifications = $_SESSION['notifications'];
  unset($_SESSION['notifications']);
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $id = $_POST['id'] ?? null;
  $page = trim($_POST['page']);
  $title = trim($_POST['title']);
  $description = trim($_POST['description']);
  $keywords = trim($_POST['keywords']);
  $og_title = trim($_POST['og_title']);
  $og_description = trim($_POST['og_description']);
  $og_image = trim($_POST['og_image']);

  if (!empty($page)) {
    try {
      if ($id) {
        // Обновляем
        $stmt = $pdo->prepare("UPDATE seo_settings SET page=?, title=?, description=?, keywords=?, og_title=?, og_description=?, og_image=? WHERE id=?");
        $stmt->execute([$page, $title, $description, $keywords, $og_title, $og_description, $og_image, $id]);
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'SEO-настройки успешно обновлены!'];
      } else {
        // Проверяем, существует ли уже такая страница
        $stmt_check = $pdo->prepare("SELECT id FROM seo_settings WHERE page = ?");
        $stmt_check->execute([$page]);
        if ($stmt_check->fetch()) {
          $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'SEO-настройки для этой страницы уже существуют!'];
        } else {
          // Добавляем
          $stmt = $pdo->prepare("INSERT INTO seo_settings (page, title, description, keywords, og_title, og_description, og_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
          $stmt->execute([$page, $title, $description, $keywords, $og_title, $og_description, $og_image]);
          $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'SEO-настройки успешно добавлены!'];
        }
      }
    } catch (Exception $e) {
      error_log("SEO ERROR: " . $e->getMessage());
      $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при сохранении SEO-настроек.'];
    }
  } else {
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Пожалуйста, укажите название страницы.'];
  }

  header("Location: /admin/seo/");
  exit();
}

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_seo'])) {
  verify_csrf();
  $id = $_POST['delete_seo'];

  try {
    $stmt = $pdo->prepare("DELETE FROM seo_settings WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'SEO-настройки успешно удалены.'];
  } catch (Exception $e) {
    error_log("SEO DELETE ERROR: " . $e->getMessage());
    $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при удалении SEO-настроек.'];
  }

  header("Location: /admin/seo/");
  exit();
}

// Получаем все страницы
$stmt = $pdo->query("SELECT * FROM seo_settings ORDER BY page ASC");
$seo_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем статистику
$total_seo_settings = count($seo_list);
$pages_with_og = array_filter($seo_list, function ($seo) {
  return !empty($seo['og_title']) || !empty($seo['og_description']) || !empty($seo['og_image']); });
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto flex gap-2">
        <?php echo backButton(); ?>
        <a href="/admin"
          class="px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm">
          Админ-панель
        </a>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-800 mb-4">SEO-настройки</h1>
      <p class="text-xl text-gray-700">Управление метатегами и Open Graph данными</p>
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
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#118568] mb-2"><?php echo $total_seo_settings; ?></div>
        <div class="text-gray-600">Всего страниц</div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#17B890] mb-2"><?php echo count($pages_with_og); ?></div>
        <div class="text-gray-600">С Open Graph</div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 text-center hover:shadow-2xl transition-shadow duration-300">
        <div class="w-12 h-12 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </div>
        <div class="text-3xl font-bold text-[#5E807F] mb-2"><?php echo $total_seo_settings - count($pages_with_og); ?>
        </div>
        <div class="text-gray-600">Без OG данных</div>
      </div>
    </div>

    <!-- Список SEO-настроек -->
    <?php if (empty($seo_list)): ?>
      <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
        <div class="w-24 h-24 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[#5E807F]" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">SEO-настройки не найдены</h2>
        <p class="text-gray-600 mb-8">Добавьте первую страницу, используя форму ниже</p>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-3xl shadow-2xl p-6 mb-12">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
          <h2 class="text-2xl font-bold text-gray-800">Список SEO-настроек</h2>
          <div class="text-gray-600">
            Найдено: <?php echo count($seo_list); ?>
            <?php echo count($seo_list) == 1 ? 'страница' : (count($seo_list) < 5 ? 'страницы' : 'страниц'); ?>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($seo_list as $seo): ?>
            <div class="bg-gray-50 rounded-2xl p-6 hover:bg-white hover:shadow-xl transition-all duration-300 group">
              <div class="flex justify-between items-start mb-4">
                <h3 class="text-xl font-bold text-gray-800 group-hover:text-[#118568] transition-colors duration-300">
                  <?php echo htmlspecialchars($seo['page']); ?>
                </h3>
                <?php if (!empty($seo['og_title']) || !empty($seo['og_description']) || !empty($seo['og_image'])): ?>
                  <span class="px-2 py-1 bg-[#17B890] text-white text-xs rounded-full">
                    OG
                  </span>
                <?php endif; ?>
              </div>

              <div class="space-y-3 mb-6">
                <?php if (!empty($seo['title'])): ?>
                  <div class="text-sm">
                    <span class="font-medium text-gray-700">Title:</span>
                    <span
                      class="text-gray-600 ml-1"><?php echo htmlspecialchars(substr($seo['title'], 0, 50)); ?><?php echo strlen($seo['title']) > 50 ? '...' : ''; ?></span>
                  </div>
                <?php endif; ?>

                <?php if (!empty($seo['description'])): ?>
                  <div class="text-sm">
                    <span class="font-medium text-gray-700">Description:</span>
                    <span
                      class="text-gray-600 ml-1"><?php echo htmlspecialchars(substr($seo['description'], 0, 50)); ?><?php echo strlen($seo['description']) > 50 ? '...' : ''; ?></span>
                  </div>
                <?php endif; ?>

                <?php if (!empty($seo['keywords'])): ?>
                  <div class="text-sm">
                    <span class="font-medium text-gray-700">Keywords:</span>
                    <span
                      class="text-gray-600 ml-1"><?php echo htmlspecialchars(substr($seo['keywords'], 0, 50)); ?><?php echo strlen($seo['keywords']) > 50 ? '...' : ''; ?></span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="flex flex-wrap gap-2">
                <button
                  class="flex-1 px-4 py-2 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 text-sm font-medium"
                  onclick="editSEO(<?php echo $seo['id']; ?>, '<?php echo htmlspecialchars(addslashes($seo['page'])); ?>', '<?php echo htmlspecialchars(addslashes($seo['title'])); ?>', '<?php echo htmlspecialchars(addslashes($seo['description'])); ?>', '<?php echo htmlspecialchars(addslashes($seo['keywords'])); ?>', '<?php echo htmlspecialchars(addslashes($seo['og_title'])); ?>', '<?php echo htmlspecialchars(addslashes($seo['og_description'])); ?>', '<?php echo htmlspecialchars($seo['og_image']); ?>')">
                  Редактировать
                </button>

                <form action="" method="POST"
                  onsubmit="return confirm('Вы уверены, что хотите удалить SEO-настройки для страницы <?php echo htmlspecialchars($seo['page']); ?>?')"
                  class="m-0">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="delete_seo" value="<?php echo $seo['id']; ?>">
                  <button type="submit"
                    class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-300 text-sm font-medium">
                    Удалить
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Форма добавления/редактирования SEO-настроек -->
    <div class="bg-white rounded-3xl shadow-2xl p-6">
      <h2 id="form-title" class="text-2xl font-bold text-gray-800 mb-6">Добавить SEO-настройки</h2>

      <form method="POST" class="space-y-8">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" id="seo-id">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Основные метатеги -->
          <div class="space-y-6">
            <div>
              <label for="seo-page" class="block text-gray-700 font-medium mb-2">Страница *</label>
              <input type="text" id="seo-page" name="page" required
                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                placeholder="Например: /catalog, /about, /contacts">
            </div>

            <div>
              <label for="seo-title" class="block text-gray-700 font-medium mb-2">Title</label>
              <input type="text" id="seo-title" name="title"
                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                placeholder="Заголовок страницы">
            </div>

            <div>
              <label for="seo-description" class="block text-gray-700 font-medium mb-2">Description</label>
              <textarea id="seo-description" name="description" rows="3"
                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                placeholder="Описание страницы для поисковых систем"></textarea>
            </div>

            <div>
              <label for="seo-keywords" class="block text-gray-700 font-medium mb-2">Keywords</label>
              <textarea id="seo-keywords" name="keywords" rows="2"
                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                placeholder="Ключевые слова через запятую"></textarea>
            </div>
          </div>

          <!-- Open Graph данные -->
          <div class="space-y-6">
            <div class="bg-[#DEE5E5] rounded-2xl p-4">
              <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                Open Graph данные
              </h3>
              <p class="text-gray-600 text-sm mb-4">Используются для корректного отображения ссылок в соцсетях</p>

              <div class="space-y-4">
                <div>
                  <label for="seo-og-title" class="block text-gray-700 font-medium mb-2">OG Title</label>
                  <input type="text" id="seo-og-title" name="og_title"
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    placeholder="Заголовок для соцсетей">
                </div>

                <div>
                  <label for="seo-og-description" class="block text-gray-700 font-medium mb-2">OG Description</label>
                  <textarea id="seo-og-description" name="og_description" rows="2"
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    placeholder="Описание для соцсетей"></textarea>
                </div>

                <div>
                  <label for="seo-og-image" class="block text-gray-700 font-medium mb-2">OG Image (URL)</label>
                  <input type="url" id="seo-og-image" name="og_image"
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"
                    placeholder="https://example.com/image.jpg">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 pt-4">
          <button type="submit"
            class="flex-1 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Сохранить SEO-настройки
          </button>

          <button type="button" onclick="resetForm()"
            class="flex-1 px-4 py-4 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors duration-300 font-bold text-lg">
            Сбросить форму
          </button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
  function editSEO(id, page, title, description, keywords, og_title, og_description, og_image) {
    document.getElementById('form-title').innerText = "Редактировать SEO-настройки";
    document.getElementById('seo-id').value = id;
    document.getElementById('seo-page').value = page;
    document.getElementById('seo-title').value = title;
    document.getElementById('seo-description').value = description;
    document.getElementById('seo-keywords').value = keywords;
    document.getElementById('seo-og-title').value = og_title;
    document.getElementById('seo-og-description').value = og_description;
    document.getElementById('seo-og-image').value = og_image;

    // Прокручиваем к форме
    document.querySelector('#form-title').scrollIntoView({ behavior: 'smooth' });
  }

  function resetForm() {
    document.getElementById('form-title').innerText = "Добавить SEO-настройки";
    document.getElementById('seo-id').value = '';
    document.getElementById('seo-page').value = '';
    document.getElementById('seo-title').value = '';
    document.getElementById('seo-description').value = '';
    document.getElementById('seo-keywords').value = '';
    document.getElementById('seo-og-title').value = '';
    document.getElementById('seo-og-description').value = '';
    document.getElementById('seo-og-image').value = '';
  }
</script>

<?php include_once('../../includes/footer.php'); ?>