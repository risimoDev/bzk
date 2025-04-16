<?php
$pageTitle = "Страница не найдена | Типография";
include_once __DIR__ . '/includes/header.php';
?>

<main class="container mx-auto px-4 py-8 text-center">
  <h1 class="text-4xl font-bold text-gray-800 mb-4">404</h1>
  <p class="text-xl text-gray-600 mb-6">Страница, которую вы ищете, не существует.</p>
  <a href="/" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-300">
    Вернуться на главную
  </a>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>