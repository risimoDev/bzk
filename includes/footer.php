<?php
// Вывод уведомлений из сессии
if (isset($_SESSION['notifications'])) {
    foreach ($_SESSION['notifications'] as $notification) {
        echo '<div class="notification ' . htmlspecialchars($notification['type']) . '">' . htmlspecialchars($notification['message']) . '</div>';
    }
    unset($_SESSION['notifications']); // Очищаем уведомления после отображения
}
?>
 <!-- Футер -->
  <footer class="bg-gray-800 text-white py-8 mt-8">
    <div class="container mx-auto px-4 text-center">
      <p class="mb-4">© 2025 Типография "BZK PRINT". Все права защищены.</p>
      <div class="flex justify-center space-x-4">
        <a href="#" class="hover:text-blue-400">Facebook</a>
        <a href="#" class="hover:text-blue-400">Instagram</a>
        <a href="#" class="hover:text-blue-400">VK</a>
      </div>
    </div>
  </footer>
</body>
</html>