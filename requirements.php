<?php
$pageTitle = "Требования к макетам | Типография";
include_once __DIR__ . '/includes/header.php';
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-gray-800 mb-6">Требования к макетам</h1>
  <p class="text-gray-700 leading-relaxed mb-6">
    Для корректной печати ваших макетов, пожалуйста, соблюдайте следующие требования:
  </p>
  <ul class="list-disc pl-6 text-gray-700">
    <li>Формат файла: PDF, AI, PSD или TIFF.</li>
    <li>Разрешение: минимум 300 DPI.</li>
    <li>Цветовая модель: CMYK.</li>
    <li>Добавьте отступы (блик) минимум 3 мм.</li>
    <li>Шрифты должны быть встроены или переведены в кривые.</li>
  </ul>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>