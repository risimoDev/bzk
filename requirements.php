<?php
session_start();
$pageTitle = "Требования к макетам";
include_once __DIR__ . '/includes/header.php';
?>

<main class="container mx-auto px-4 py-8">
  <!-- Вставка breadcrumbs и кнопки "Назад" -->
<div class="container mx-auto px-4 py-4 flex justify-between items-center">
    <!-- Breadcrumbs -->
    <div>
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
    </div>

    <!-- Кнопка "Назад" -->
    <div>
        <?php echo backButton(); ?>
    </div>
</div>
  <h1 class="text-4xl font-bold text-center text-gray-800 mb-8">Требования к макетам</h1>

  <!-- Введение -->
  <section class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Важно!</h2>
    <p class="text-gray-600">
      Для обеспечения высокого качества печати, пожалуйста, соблюдайте следующие требования к макетам. Это поможет избежать ошибок и ускорит процесс подготовки вашего заказа.
    </p>
  </section>

  <!-- Основные требования -->
  <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-lg shadow-md text-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-litegreen mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
      </svg>
      <h3 class="text-xl font-bold text-gray-800 mb-2">Форматы файлов</h3>
      <p class="text-gray-600">Поддерживаемые форматы: CDR, AI, SVG, EPS, PSD, JPG, PDF.</p>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md text-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-litegreen mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 5v1m0 5v1m0-12v12" />
      </svg>
      <h3 class="text-xl font-bold text-gray-800 mb-2">Разрешение</h3>
      <p class="text-gray-600">Минимальное разрешение: 300 DPI.</p>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md text-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-litegreen mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
      </svg>
      <h3 class="text-xl font-bold text-gray-800 mb-2">Цветовые профили</h3>
      <p class="text-gray-600">Используйте CMYK для печати.</p>
    </div>
  </section>

  <!-- Подробная информация -->
  <section class="bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Подробные требования</h2>
    <ul class="list-disc list-inside text-gray-600 space-y-2">
      <li><strong>Отступы:</strong> Убедитесь, что важный контент находится на расстоянии не менее 5 мм от краев.</li>
      <li><strong>Вылеты:</strong> Добавьте вылеты 2мм с каждой стороны для обрезки.</li>
      <li><strong>Шрифты:</strong> Преобразуйте все шрифты в кривые или включите их в файл.</li>
      <li><strong>Цвета:</strong> Используйте сплошные цвета (Pantone) только при необходимости.</li>
      <li><strong>Изображения:</strong> Убедитесь, что все изображения имеют высокое качество и разрешение.</li>
    </ul>
  </section>

  <!-- Советы -->
  <section class="bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Полезные советы</h2>
    <p class="text-gray-600 mb-4">
      Если вы новичок в создании макетов для печати, вот несколько советов:
    </p>
    <ul class="list-disc list-inside text-gray-600 space-y-2">
      <li>Используйте профессиональные программы, такие как CorelDRAW или Adobe Illustrator .</li>
      <li>Проверьте макет на наличие ошибок перед отправкой.</li>
      <li>Если у вас есть сомнения, свяжитесь с нашими специалистами для консультации.</li>
    </ul>
  </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>