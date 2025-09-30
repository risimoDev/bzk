<?php
session_start();
$pageTitle = "Уведомления";
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
  header("Location: /login");
  exit();
}

    // Добавление нового уведомления
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notification'])) {
      // Санитизация и валидация данных
      $title = trim($_POST['title'] ?? '');
      $message = trim($_POST['message'] ?? '');
      $type = $_POST['type'] ?? 'info';
      $active = isset($_POST['active']) ? 1 : 0;
      $target_audience = $_POST['target_audience'] ?? 'all';
      $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
      $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
      
      // Проверка на минимальную и максимальную длину
      if (strlen($title) < 5) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заголовок должен содержать минимум 5 символов'];
      } elseif (strlen($title) > 100) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заголовок не должен превышать 100 символов'];
      } elseif (strlen($message) < 10) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Сообщение должно содержать минимум 10 символов'];
      } elseif (strlen($message) > 500) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Сообщение не должно превышать 500 символов'];
      } else {
        // Подготовленный запрос для безопасности
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, type, active, target_audience, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $message, $type, $active, $target_audience, $start_date, $end_date])) {
          // Don't add to session notifications to prevent duplicates - new system will show from database
        } else {
          $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при добавлении уведомления'];
        }
      }
      header("Location: notifications.php");
      exit;
    }

    // Редактирование уведомления
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_notification'])) {
      // Санитизация и валидация данных
      $title = trim($_POST['title'] ?? '');
      $message = trim($_POST['message'] ?? '');
      $type = $_POST['type'] ?? 'info';
      $active = isset($_POST['active']) ? 1 : 0;
      $target_audience = $_POST['target_audience'] ?? 'all';
      $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
      $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
      $notification_id = (int) $_POST['notification_id'];
      
      // Проверка на минимальную и максимальную длину
      if (strlen($title) < 5) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заголовок должен содержать минимум 5 символов'];
      } elseif (strlen($title) > 100) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Заголовок не должен превышать 100 символов'];
      } elseif (strlen($message) < 10) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Сообщение должно содержать минимум 10 символов'];
      } elseif (strlen($message) > 500) {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Сообщение не должно превышать 500 символов'];
      } else {
        // Подготовленный запрос для безопасности
        $stmt = $pdo->prepare("UPDATE notifications SET title=?, message=?, type=?, active=?, target_audience=?, start_date=?, end_date=? WHERE id=?");
        if ($stmt->execute([$title, $message, $type, $active, $target_audience, $start_date, $end_date, $notification_id])) {
          // Don't add to session notifications to prevent duplicates - new system will show from database
        } else {
          $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при обновлении уведомления'];
        }
      }
      header("Location: notifications.php");
      exit;
    }

    // Включение/выключение
    if (isset($_GET['toggle'])) {
      $id = (int) $_GET['toggle'];
      $stmt = $pdo->prepare("UPDATE notifications SET active = 1 - active WHERE id = ?");
      if ($stmt->execute([$id])) {
        // Don't add to session notifications to prevent duplicates - new system will show from database
      } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при изменении статуса уведомления'];
      }
      header("Location: notifications.php");
      exit;
    }

    // Удаление уведомления
    if (isset($_GET['delete'])) {
      $id = (int) $_GET['delete'];
      // Проверка существования уведомления перед удалением
      $stmt = $pdo->prepare("SELECT id FROM notifications WHERE id = ?");
      $stmt->execute([$id]);
      if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        if ($stmt->execute([$id])) {
          // Don't add to session notifications to prevent duplicates - new system will show from database
        } else {
          $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при удалении уведомления'];
        }
      } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Уведомление не найдено'];
      }
      header("Location: notifications.php");
      exit;
    }

    // Обработка массовых операций
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
      $selected_ids = $_POST['selected_ids'] ?? [];

      if (!empty($selected_ids)) {
        // Преобразуем в массив целых чисел
        $selected_ids = array_map('intval', $selected_ids);
        
        switch ($_POST['bulk_action']) {
          case 'delete':
            $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders)");
            if ($stmt->execute($selected_ids)) {
              // Don't add to session notifications to prevent duplicates - new system will show from database
            } else {
              $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при удалении уведомлений'];
            }
            break;

          case 'toggle_status':
            $status = (int) $_POST['bulk_status'];
            $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
            $params = array_merge([$status], $selected_ids);
            $stmt = $pdo->prepare("UPDATE notifications SET active = ? WHERE id IN ($placeholders)");
            if ($stmt->execute($params)) {
              $action_text = $status ? 'Активировано' : 'Деактивировано';
              // Don't add to session notifications to prevent duplicates - new system will show from database
            } else {
              $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при изменении статуса уведомлений'];
            }
            break;
        }
        header("Location: notifications.php");
        exit;
      }
    }

    // Дублирование уведомления
    if (isset($_GET['duplicate'])) {
      $id = (int) $_GET['duplicate'];
      $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
      $stmt->execute([$id]);
      $original = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($original) {
        // Санитизация данных перед вставкой
        $title = $original['title'] . ' (копия)';
        $message = $original['message'];
        $type = $original['type'];
        $target_audience = $original['target_audience'];
        $start_date = $original['start_date'];
        $end_date = $original['end_date'];
        
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, type, active, target_audience, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([
          $title,
          $message,
          $type,
          0, // Копия создаётся неактивной
          $target_audience,
          $start_date,
          $end_date
        ])) {
          // Don't add to session notifications to prevent duplicates - new system will show from database
        } else {
          $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при дублировании уведомления'];
        }
      } else {
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Уведомление не найдено'];
      }
      header("Location: notifications.php");
      exit;
    }

    // Экспорт уведомлений
    if (isset($_GET['export'])) {
      if ($_GET['export'] === 'all') {
        $export_stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
        $export_notifications = $export_stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="notifications_" . date("Y-m-d_H-i-s") . ".csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Заголовок', 'Сообщение', 'Тип', 'Статус', 'Аудитория', 'Дата создания']);

        foreach ($export_notifications as $n) {
          fputcsv($output, [
            $n['id'],
            $n['title'],
            $n['message'],
            $n['type'],
            $n['active'] ? 'Активно' : 'Неактивно',
            $n['target_audience'],
            $n['created_at']
          ]);
        }

        fclose($output);
        exit;
      }
    }

    // Проверка AJAX-запросов
    if (isset($_GET['ajax'])) {
      header('Content-Type: application/json');

      if ($_GET['ajax'] === 'stats') {
        $total = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
        $active = $pdo->query("SELECT COUNT(*) FROM notifications WHERE active = 1")->fetchColumn();

        echo json_encode([
          'total' => $total,
          'active' => $active,
          'inactive' => $total - $active
        ]);
        exit;
      }

      if ($_GET['ajax'] === 'preview' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($notification) {
          echo json_encode([
            'success' => true,
            'notification' => $notification
          ]);
        } else {
          echo json_encode(['success' => false]);
        }
        exit;
      }
    }

    // Получение уведомления для редактирования
    $edit_notification = null;
    if (isset($_GET['edit'])) {
      $id = (int) $_GET['edit'];
      $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
      $stmt->execute([$id]);
      $edit_notification = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Получение всех уведомлений с фильтрацией
    $filter_type = $_GET['filter_type'] ?? '';
    $filter_status = $_GET['filter_status'] ?? '';
    $search = $_GET['search'] ?? '';

    $query = "SELECT * FROM notifications WHERE 1=1";
    $params = [];

    if ($filter_type) {
      $query .= " AND type = ?";
      $params[] = $filter_type;
    }

    if ($filter_status !== '') {
      $query .= " AND active = ?";
      $params[] = (int) $filter_status;
    }

    if ($search) {
      $query .= " AND (title LIKE ? OR message LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
    }

    $query .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Статистика
    $total_notifications = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    $active_notifications = $pdo->query("SELECT COUNT(*) FROM notifications WHERE active = 1")->fetchColumn();
    $inactive_notifications = $total_notifications - $active_notifications;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Уведомления - BZK Print</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-slide-in {
      animation: slideInUp 0.6s ease-out forwards;
      opacity: 0;
    }

    .hover-lift {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .hover-lift:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    /* Улучшенные стили для уведомлений */
    .notification-card {
      transition: all 0.3s ease;
      border-left-width: 4px;
    }

    .notification-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .notification-info {
      border-left-color: #3b82f6;
    }

    .notification-success {
      border-left-color: #10b981;
    }

    .notification-warning {
      border-left-color: #f59e0b;
    }

    .notification-error {
      border-left-color: #ef4444;
    }
    
    /* Стили для скрытых уведомлений */
    .notification-hidden {
      opacity: 0.6;
    }
    
    .notification-hidden .hidden-badge {
      display: inline-block;
    }
    
    .hidden-badge {
      display: none;
      background-color: #94a3b8;
      color: white;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 0.7rem;
      margin-left: 8px;
    }
  </style>
</head>

<body class="font-sans bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] min-h-screen">

  <?php include __DIR__ . '/../includes/header.php'; ?>

  <main class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Breadcrumbs и навигация -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <?php echo backButton(); ?>
      </div>
    </div>

    <!-- Заголовок -->
    <div class="text-center mb-8">
      <div
        class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-[#118568] to-[#17B890] rounded-full mb-4 shadow-lg">
        <i class="fas fa-bell text-white text-xl"></i>
      </div>
      <h1 class="text-4xl font-bold text-gray-800 mb-2">Управление уведомлениями</h1>
      <p class="text-xl text-gray-700">Создавайте и управляйте уведомлениями для пользователей</p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-xl p-6 border-l-4 border-blue-500 hover-lift animate-slide-in"
        style="animation-delay: 0.1s">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 mb-1">Всего уведомлений</p>
            <p class="text-3xl font-bold text-gray-900 stat-total"><?php echo $total_notifications; ?></p>
            <p class="text-xs text-gray-500 mt-1">Создано всего</p>
          </div>
          <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
            <i class="fas fa-bell text-white text-xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 border-l-4 border-green-500 hover-lift animate-slide-in"
        style="animation-delay: 0.2s">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 mb-1">Активные</p>
            <p class="text-3xl font-bold text-gray-900 stat-active"><?php echo $active_notifications; ?></p>
            <p class="text-xs text-gray-500 mt-1">Показываются пользователям</p>
          </div>
          <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
            <i class="fas fa-bell-on text-white text-xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 border-l-4 border-gray-500 hover-lift animate-slide-in"
        style="animation-delay: 0.3s">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 mb-1">Неактивные</p>
            <p class="text-33xl font-bold text-gray-900 stat-inactive"><?php echo $inactive_notifications; ?></p>
            <p class="text-xs text-gray-500 mt-1">Отключенные уведомления</p>
          </div>
          <div class="w-12 h-12 bg-gray-500 rounded-full flex items-center justify-center">
            <i class="fas fa-bell-slash text-white text-xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 border-l-4 border-purple-500 hover-lift animate-slide-in"
        style="animation-delay: 0.4s">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 mb-1">Показано сегодня</p>
            <p class="text-3xl font-bold text-gray-900">
              <?php
              $today_notifications = $pdo->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()")->fetchColumn();
              echo $today_notifications;
              ?>
            </p>
            <p class="text-xs text-gray-500 mt-1">Новые за сегодня</p>
          </div>
          <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center">
            <i class="fas fa-calendar-day text-white text-xl"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Левая колонка - Форма -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-xl p-8 hover-lift animate-slide-in" style="animation-delay: 0.4s">
          <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-<?php echo $edit_notification ? 'edit' : 'plus-circle'; ?> text-[#118568] mr-3"></i>
            <?php echo $edit_notification ? 'Редактировать уведомление' : 'Новое уведомление'; ?>
          </h2>

          <form method="POST" class="space-y-6" id="notification-form">
            <?php if ($edit_notification): ?>
              <input type="hidden" name="notification_id" value="<?php echo $edit_notification['id']; ?>">
            <?php endif; ?>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Заголовок *</label>
              <input type="text" name="title"
                value="<?php echo $edit_notification ? htmlspecialchars($edit_notification['title']) : ''; ?>"
                placeholder="Введите заголовок уведомления" required maxlength="100"
                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
              <div class="text-xs text-gray-500 mt-1">Максимум 100 символов</div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Сообщение *</label>
              <textarea name="message" rows="4" required maxlength="500"
                placeholder="Введите текст уведомления для пользователей"
                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300"><?php echo $edit_notification ? htmlspecialchars($edit_notification['message']) : ''; ?></textarea>
              <div class="text-xs text-gray-500 mt-1">Максимум 500 символов</div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Тип уведомления</label>
                <select name="type"
                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
                  <option value="info" <?php echo ($edit_notification && $edit_notification['type'] == 'info') ? 'selected' : ''; ?>>
                    <i class="fas fa-info-circle"></i> Информация
                  </option>
                  <option value="success" <?php echo ($edit_notification && $edit_notification['type'] == 'success') ? 'selected' : ''; ?>>
                    <i class="fas fa-check-circle"></i> Успех
                  </option>
                  <option value="warning" <?php echo ($edit_notification && $edit_notification['type'] == 'warning') ? 'selected' : ''; ?>>
                    <i class="fas fa-exclamation-triangle"></i> Предупреждение
                  </option>
                  <option value="error" <?php echo ($edit_notification && $edit_notification['type'] == 'error') ? 'selected' : ''; ?>>
                    <i class="fas fa-times-circle"></i> Ошибка
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Целевая аудитория</label>
                <select name="target_audience"
                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300">
                  <option value="all" <?php echo ($edit_notification && $edit_notification['target_audience'] == 'all') ? 'selected' : ''; ?>>
                    <i class="fas fa-users"></i> Все пользователи
                  </option>
                  <option value="clients" <?php echo ($edit_notification && $edit_notification['target_audience'] == 'clients') ? 'selected' : ''; ?>>
                    <i class="fas fa-user"></i> Только клиенты
                  </option>
                  <option value="admins" <?php echo ($edit_notification && $edit_notification['target_audience'] == 'admins') ? 'selected' : ''; ?>>
                    <i class="fas fa-user-shield"></i> Только администраторы
                  </option>
                  <option value="managers" <?php echo ($edit_notification && $edit_notification['target_audience'] == 'managers') ? 'selected' : ''; ?>>
                    <i class="fas fa-user-tie"></i> Только менеджеры
                  </option>
                </select>
              </div>
            </div>

            <div class="bg-gray-50 rounded-xl p-4">
              <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                <i class="fas fa-calendar-alt text-[#118568] mr-2"></i>
                Период показа (опционально)
              </h4>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Начать показ с</label>
                  <input type="datetime-local" name="start_date"
                    value="<?php echo $edit_notification && $edit_notification['start_date'] ? date('Y-m-d\TH:i', strtotime($edit_notification['start_date'])) : ''; ?>"
                    class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 text-sm">
                </div>

                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Закончить показ</label>
                  <input type="datetime-local" name="end_date"
                    value="<?php echo $edit_notification && $edit_notification['end_date'] ? date('Y-m-d\TH:i', strtotime($edit_notification['end_date'])) : ''; ?>"
                    class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300 text-sm">
                </div>
              </div>
              <div class="text-xs text-gray-500 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                Если даты не указаны, уведомление будет показываться без ограничений по времени
              </div>
            </div>

            <div class="bg-blue-50 rounded-xl p-4">
              <div class="flex items-center justify-between">
                <div>
                  <h4 class="text-sm font-semibold text-gray-700 mb-1">Статус уведомления</h4>
                  <p class="text-xs text-gray-600">Активные уведомления будут показаны пользователям</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" name="active" id="active" <?php echo (!$edit_notification || $edit_notification['active']) ? 'checked' : ''; ?> class="sr-only peer">
                  <div
                    class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#9DC5BB] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#118568]">
                  </div>
                </label>
              </div>
            </div>

            <div class="flex gap-4">
              <button type="submit" name="<?php echo $edit_notification ? 'edit_notification' : 'add_notification'; ?>"
                class="flex-1 bg-gradient-to-r from-[#118568] to-[#17B890] text-white py-4 rounded-xl hover:from-[#0f755a] hover:to-[#118568] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center">
                <i class="fas fa-<?php echo $edit_notification ? 'save' : 'plus'; ?> mr-2"></i>
                <?php echo $edit_notification ? 'Обновить' : 'Создать'; ?>
              </button>

              <?php if ($edit_notification): ?>
                <a href="notifications.php"
                  class="flex-1 bg-gray-500 text-white py-4 rounded-xl hover:bg-gray-600 transition-all duration-300 font-bold text-lg shadow-lg hover:shadow-xl flex items-center justify-center text-center">
                  <i class="fas fa-times mr-2"></i>Отмена
                </a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Быстрые действия -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mt-6 hover-lift animate-slide-in" style="animation-delay: 0.5s">
          <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-bolt text-[#17B890] mr-2"></i>
            Быстрые действия
          </h3>
          <div class="space-y-3">
            <button onclick="fillTemplate('maintenance')"
              class="w-full text-left p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition-colors duration-300">
              <div class="flex items-center">
                <i class="fas fa-tools text-yellow-600 mr-3"></i>
                <div>
                  <div class="font-medium text-gray-800">Техническое обслуживание</div>
                  <div class="text-xs text-gray-600">Уведомление о планируемых работах</div>
                </div>
              </div>
            </button>

            <button onclick="fillTemplate('update')"
              class="w-full text-left p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors duration-300">
              <div class="flex items-center">
                <i class="fas fa-arrow-up text-blue-600 mr-3"></i>
                <div>
                  <div class="font-medium text-gray-800">Обновление системы</div>
                  <div class="text-xs text-gray-600">Информация о новых возможностях</div>
                </div>
              </div>
            </button>

            <button onclick="fillTemplate('promo')"
              class="w-full text-left p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors duration-300">
              <div class="flex items-center">
                <i class="fas fa-gift text-green-600 mr-3"></i>
                <div>
                  <div class="font-medium text-gray-800">Акция/Скидка</div>
                  <div class="text-xs text-gray-600">Промо-уведомление для клиентов</div>
                </div>
              </div>
            </button>

            <button onclick="fillTemplate('welcome')"
              class="w-full text-left p-3 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors duration-300">
              <div class="flex items-center">
                <i class="fas fa-hand-sparkles text-purple-600 mr-3"></i>
                <div>
                  <div class="font-medium text-gray-800">Добро пожаловать</div>
                  <div class="text-xs text-gray-600">Приветственное уведомление для новых пользователей</div>
                </div>
              </div>
            </button>

            <button onclick="fillTemplate('payment')"
              class="w-full text-left p-3 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors duration-300">
              <div class="flex items-center">
                <i class="fas fa-credit-card text-indigo-600 mr-3"></i>
                <div>
                  <div class="font-medium text-gray-800">Оплата заказа</div>
                  <div class="text-xs text-gray-600">Напоминание об оплате заказа</div>
                </div>
              </div>
            </button>

            <button onclick="fillTemplate('delivery')"
              class="w-full text-left p-3 bg-teal-50 border border-teal-200 rounded-lg hover:bg-teal-100 transition-colors duration-300">
              <div class="flex items-center">
                <i class="fas fa-truck text-teal-600 mr-3"></i>
                <div>
                  <div class="font-medium text-gray-800">Доставка заказа</div>
                  <div class="text-xs text-gray-600">Уведомление о статусе доставки</div>
                </div>
              </div>
            </button>

            <button onclick="fillTemplate('feedback')"
              class="w-full text-left p-3 bg-pink-50 border border-pink-200 rounded-lg hover:bg-pink-100 transition-colors duration-300">
              <div class="flex items-center">
                <i class="fas fa-comment-dots text-pink-600 mr-3"></i>
                <div>
                  <div class="font-medium text-gray-800">Обратная связь</div>
                  <div class="text-xs text-gray-600">Запрос на отзыв о качестве услуг</div>
                </div>
              </div>
            </button>

            <button onclick="fillTemplate('holiday')"
              class="w-full text-left p-3 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors duration-300">
              <div class="flex items-center">
                <i class="fas fa-gifts text-amber-600 mr-3"></i>
                <div>
                  <div class="font-medium text-gray-800">Праздничные скидки</div>
                  <div class="text-xs text-gray-600">Специальные предложения к праздникам</div>
                </div>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Правая колонка - Список уведомлений -->
      <div class="lg:col-span-2">
        <!-- Фильтры и поиск -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 hover-lift animate-slide-in" style="animation-delay: 0.5s">
          <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-filter text-[#17B890] mr-2"></i>
            Фильтры и поиск
          </h3>

          <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Поиск по заголовку..."
                class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] transition-all duration-300">
            </div>

            <div>
              <select name="filter_type"
                class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] transition-all duration-300">
                <option value="">Все типы</option>
                <option value="info" <?php echo $filter_type == 'info' ? 'selected' : ''; ?>>Информация</option>
                <option value="success" <?php echo $filter_type == 'success' ? 'selected' : ''; ?>>Успех</option>
                <option value="warning" <?php echo $filter_type == 'warning' ? 'selected' : ''; ?>>Предупреждение</option>
                <option value="error" <?php echo $filter_type == 'error' ? 'selected' : ''; ?>>Ошибка</option>
              </select>
            </div>

            <div>
              <select name="filter_status"
                class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] transition-all duration-300">
                <option value="">Все статусы</option>
                <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>Активные</option>
                <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>Неактивные</option>
              </select>
            </div>

            <div class="flex gap-2">
              <button type="submit"
                class="flex-1 bg-gradient-to-r from-[#118568] to-[#17B890] text-white px-4 py-2 rounded-lg hover:from-[#0f755a] hover:to-[#118568] transition-all duration-300 flex items-center justify-center">
                <i class="fas fa-search mr-2"></i>Поиск
              </button>
              <a href="notifications.php"
                class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-all duration-300 flex items-center justify-center">
                <i class="fas fa-times"></i>
              </a>
            </div>
          </form>
        </div>

        <!-- Список уведомлений -->
        <div class="bg-white rounded-2xl shadow-xl p-6 hover-lift animate-slide-in" style="animation-delay: 0.6s">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800 flex items-center">
              <i class="fas fa-list text-[#17B890] mr-2"></i>
              Список уведомлений
            </h3>
            <span class="text-sm text-gray-500">Найдено: <?php echo count($notifications); ?></span>
          </div>

          <?php if (empty($notifications)): ?>
            <div class="text-center py-12">
              <i class="fas fa-bell-slash text-gray-300 text-6xl mb-4"></i>
              <h3 class="text-xl font-semibold text-gray-500 mb-2">Уведомлений не найдено</h3>
              <p class="text-gray-400">Создайте первое уведомление или измените фильтры</p>
            </div>
          <?php else: ?>
            <div class="space-y-4">
              <?php foreach ($notifications as $i => $n): ?>
                <div
                  class="notification-card p-6 rounded-xl border border-gray-200 hover:shadow-md transition-all duration-300 animate-slide-in <?php
                    switch ($n['type']) {
                      case 'success':
                        echo 'notification-success bg-green-50';
                        break;
                      case 'warning':
                        echo 'notification-warning bg-yellow-50';
                        break;
                      case 'error':
                        echo 'notification-error bg-red-50';
                        break;
                      default:
                        echo 'notification-info bg-blue-50';
                    }
                  ?>"
                  style="animation-delay: <?php echo 0.1 * ($i + 1); ?>s"
                  data-notification-id="<?php echo $n['id']; ?>">
                  <div class="flex justify-between items-start">
                    <div class="flex-1">
                      <div class="flex items-center mb-3">
                        <h4 class="font-bold text-lg text-gray-800 mr-3"><?= htmlspecialchars($n['title']) ?></h4>

                        <!-- Иконки статуса -->
                        <div class="flex items-center space-x-2">
                          <span class="px-3 py-1 rounded-full text-xs font-medium 
                                                    <?php
                                                    switch ($n['type']) {
                                                      case 'success':
                                                        echo 'bg-green-100 text-green-700';
                                                        break;
                                                      case 'warning':
                                                        echo 'bg-yellow-100 text-yellow-700';
                                                        break;
                                                      case 'error':
                                                        echo 'bg-red-100 text-red-700';
                                                        break;
                                                      default:
                                                        echo 'bg-blue-100 text-blue-700';
                                                    }
                                                    ?>">
                            <i class="fas fa-<?php
                            switch ($n['type']) {
                              case 'success':
                                echo 'check-circle';
                                break;
                              case 'warning':
                                echo 'exclamation-triangle';
                                break;
                              case 'error':
                                echo 'times-circle';
                                break;
                              default:
                                echo 'info-circle';
                            }
                            ?> mr-1"></i>
                            <?= ucfirst($n['type']) ?>
                          </span>

                          <span
                            class="px-3 py-1 rounded-full text-xs font-medium <?= $n['active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' ?>">
                            <i class="fas fa-<?= $n['active'] ? 'eye' : 'eye-slash' ?> mr-1"></i>
                            <?= $n['active'] ? 'Активно' : 'Неактивно' ?>
                          </span>
                          
                          <!-- Badge for hidden notifications -->
                          <span class="hidden-badge">Скрыто</span>
                        </div>
                      </div>

                      <p class="text-gray-700 mb-3 leading-relaxed"><?= htmlspecialchars($n['message']) ?></p>

                      <div class="flex items-center text-sm text-gray-500 space-x-4">
                        <span class="flex items-center">
                          <i class="fas fa-calendar mr-1"></i>
                          <?= date('d.m.Y H:i', strtotime($n['created_at'])) ?>
                        </span>
                        <?php if ($n['target_audience']): ?>
                          <span class="flex items-center">
                            <i class="fas fa-users mr-1"></i>
                            <?php
                            switch ($n['target_audience']) {
                              case 'all':
                                echo 'Все пользователи';
                                break;
                              case 'clients':
                                echo 'Клиенты';
                                break;
                              case 'admins':
                                echo 'Администраторы';
                                break;
                              default:
                                echo $n['target_audience'];
                            }
                            ?>
                          </span>
                        <?php endif; ?>
                        <?php if ($n['start_date'] || $n['end_date']): ?>
                          <span class="flex items-center">
                            <i class="fas fa-clock mr-1"></i>
                            <?php if ($n['start_date']): ?>
                              с <?= date('d.m.Y', strtotime($n['start_date'])) ?>
                            <?php endif; ?>
                            <?php if ($n['end_date']): ?>
                              до <?= date('d.m.Y', strtotime($n['end_date'])) ?>
                            <?php endif; ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="flex flex-col gap-2 ml-6">
                      <a href="?edit=<?= $n['id'] ?>"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm flex items-center justify-center">
                        <i class="fas fa-edit mr-1"></i>Редактировать
                      </a>

                      <a href="?toggle=<?= $n['id'] ?>"
                        class="px-4 py-2 <?= $n['active'] ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-500 hover:bg-green-600' ?> text-white rounded-lg transition-all duration-300 text-sm flex items-center justify-center">
                        <i class="fas fa-<?= $n['active'] ? 'eye-slash' : 'eye' ?> mr-1"></i>
                        <?= $n['active'] ? 'Отключить' : 'Включить' ?>
                      </a>

                      <a href="?duplicate=<?= $n['id'] ?>"
                        class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-all duration-300 text-sm flex items-center justify-center">
                        <i class="fas fa-copy mr-1"></i>Копировать
                      </a>

                      <a href="?delete=<?= $n['id'] ?>"
                        onclick="return confirm('Вы уверены, что хотите удалить это уведомление?')"
                        class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all duration-300 text-sm flex items-center justify-center">
                        <i class="fas fa-trash mr-1"></i>Удалить
                      </a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
    // Функция для заполнения шаблонов
    function fillTemplate(type) {
      const titleInput = document.querySelector('input[name="title"]');
      const messageTextarea = document.querySelector('textarea[name="message"]');
      const typeSelect = document.querySelector('select[name="type"]');
      const audienceSelect = document.querySelector('select[name="target_audience"]');

      const templates = {
        maintenance: {
          title: 'Техническое обслуживание системы',
          message: 'Уважаемые пользователи! Планируется техническое обслуживание системы. Возможны кратковременные перебои в работе. Приносим извинения за временные неудобства.',
          type: 'warning',
          audience: 'all'
        },
        update: {
          title: 'Обновление функционала',
          message: 'Мы рады сообщить о новых возможностях в системе! Ознакомьтесь с обновленным интерфейсом и дополнительными функциями для более удобной работы.',
          type: 'success',
          audience: 'all'
        },
        promo: {
          title: 'Специальное предложение',
          message: 'Не упустите возможность! Действует специальная скидка на наши услуги. Подробности уточняйте у менеджеров или в личном кабинете.',
          type: 'info',
          audience: 'clients'
        },
        // Новые шаблоны
        welcome: {
          title: 'Добро пожаловать!',
          message: 'Добро пожаловать в наш сервис! Мы рады видеть вас и надеемся на долгое и плодотворное сотрудничество.',
          type: 'success',
          audience: 'clients'
        },
        payment: {
          title: 'Оплата заказа',
          message: 'Напоминаем о необходимости оплаты вашего заказа. Пожалуйста, произведите оплату в ближайшее время для продолжения обработки заказа.',
          type: 'warning',
          audience: 'clients'
        },
        delivery: {
          title: 'Доставка заказа',
          message: 'Ваш заказ отправлен и находится в пути. Ожидайте получение в ближайшие дни. Спасибо за выбор нашего сервиса!',
          type: 'info',
          audience: 'clients'
        },
        feedback: {
          title: 'Обратная связь',
          message: 'Нам важно ваше мнение! Пожалуйста, оставьте отзыв о качестве наших услуг. Это поможет нам стать лучше.',
          type: 'info',
          audience: 'clients'
        },
        holiday: {
          title: 'Праздничные скидки',
          message: 'С наступающим праздником! В честь праздника действуют специальные скидки на все услуги. Спешите воспользоваться предложением!',
          type: 'success',
          audience: 'all'
        },
        reminder: {
          title: 'Напоминание',
          message: 'Напоминаем вам о важном событии или задаче. Пожалуйста, уделите этому внимание в ближайшее время.',
          type: 'info',
          audience: 'all'
        },
        system: {
          title: 'Системное уведомление',
          message: 'Важное системное уведомление. Пожалуйста, ознакомьтесь с информацией и при необходимости примите меры.',
          type: 'warning',
          audience: 'all'
        },
        security: {
          title: 'Безопасность',
          message: 'Обратите внимание на безопасность вашего аккаунта. Рекомендуем проверить настройки безопасности и обновить пароль.',
          type: 'error',
          audience: 'all'
        }
      };

      const template = templates[type];
      if (template) {
        titleInput.value = template.title;
        messageTextarea.value = template.message;
        typeSelect.value = template.type;
        audienceSelect.value = template.audience;

        // Анимация заполнения
        [titleInput, messageTextarea].forEach(el => {
          el.style.backgroundColor = '#f0fdf4';
          setTimeout(() => {
            el.style.backgroundColor = '';
          }, 1000);
        });
      }
    }

    // Валидация формы
    document.getElementById('notification-form').addEventListener('submit', function (e) {
      const title = document.querySelector('input[name="title"]').value.trim();
      const message = document.querySelector('textarea[name="message"]').value.trim();

      if (title.length < 5) {
        e.preventDefault();
        alert('Заголовок должен содержать минимум 5 символов');
        return;
      }

      if (message.length < 10) {
        e.preventDefault();
        alert('Сообщение должно содержать минимум 10 символов');
        return;
      }

      if (title.length > 100) {
        e.preventDefault();
        alert('Заголовок не должен превышать 100 символов');
        return;
      }

      if (message.length > 500) {
        e.preventDefault();
        alert('Сообщение не должно превышать 500 символов');
        return;
      }
    });

    // Обновление счетчиков символов
    function updateCharCounter(input, maxLength, counterId) {
      const counter = document.getElementById(counterId);
      if (counter) {
        const length = input.value.length;
        counter.textContent = `${length}/${maxLength} символов`;

        if (length > maxLength * 0.9) {
          counter.style.color = '#ef4444';
        } else if (length > maxLength * 0.7) {
          counter.style.color = '#f59e0b';
        } else {
          counter.style.color = '#6b7280';
        }
      }
    }

    // Добавляем счетчики
    document.addEventListener('DOMContentLoaded', function () {
      const titleInput = document.querySelector('input[name="title"]');
      const messageTextarea = document.querySelector('textarea[name="message"]');

      if (titleInput) {
        const titleCounter = titleInput.parentElement.querySelector('.text-xs');
        if (titleCounter) {
          titleCounter.id = 'title-counter';
          titleInput.addEventListener('input', () => updateCharCounter(titleInput, 100, 'title-counter'));
          updateCharCounter(titleInput, 100, 'title-counter');
        }
      }

      if (messageTextarea) {
        const messageCounter = messageTextarea.parentElement.querySelector('.text-xs');
        if (messageCounter) {
          messageCounter.id = 'message-counter';
          messageTextarea.addEventListener('input', () => updateCharCounter(messageTextarea, 500, 'message-counter'));
          updateCharCounter(messageTextarea, 500, 'message-counter');
        }
      }
    });

    // Подтверждение удаления
    function confirmDelete(id, title) {
      if (confirm(`Вы уверены, что хотите удалить уведомление "${title}"?\n\nЭто действие нельзя отменить.`)) {
        window.location.href = `?delete=${id}`;
      }
    }

    // Bulk actions
    function toggleAllNotifications() {
      const checkboxes = document.querySelectorAll('input[name="selected_notifications[]"]');
      const selectAllCheckbox = document.getElementById('select-all');

      checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
      });

      updateBulkActionsVisibility();
    }

    function updateBulkActionsVisibility() {
      const checkboxes = document.querySelectorAll('input[name="selected_notifications[]"]:checked');
      const bulkActions = document.getElementById('bulk-actions');

      if (bulkActions) {
        bulkActions.style.display = checkboxes.length > 0 ? 'block' : 'none';
      }
    }

    // Auto refresh statistics
    setInterval(function () {
      fetch('?ajax=stats')
        .then(response => response.json())
        .then(data => {
          if (data.total) {
            document.querySelector('.stat-total').textContent = data.total;
            document.querySelector('.stat-active').textContent = data.active;
            document.querySelector('.stat-inactive').textContent = data.inactive;
          }
        })
        .catch(err => console.log('Stats update failed:', err));
    }, 30000); // Обновление каждые 30 секунд
    // Показать скрытые уведомления
    document.addEventListener('DOMContentLoaded', function() {
      // Помечаем скрытые уведомления в списке
      const notificationCards = document.querySelectorAll('.notification-card');
      notificationCards.forEach(card => {
        const id = card.dataset.notificationId;
        if (id && localStorage.getItem('notification_' + id) === 'closed') {
          card.classList.add('notification-hidden');
        }
      });
    });
  </script>

</body>

</html>