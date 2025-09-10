<?php
require_once __DIR__ . '/includes/session.php';
$pageTitle = "Требования к макетам";
?>

<?php include_once __DIR__ . '/includes/header.php'; ?>

<main class="bg-pattern min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-6xl">
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

    <div class="text-center mb-12">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Требования к макетам</h1>
      <p class="text-xl text-gray-700 max-w-3xl mx-auto">Для обеспечения высокого качества печати и безупречного
        результата, пожалуйста, внимательно ознакомьтесь с нашими требованиями к макетам.</p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <!-- Введение -->
    <div class="bg-white rounded-2xl shadow-xl p-8 mb-12 transform transition-all duration-300 hover:shadow-2xl">
      <div class="flex items-start">
        <div class="flex-shrink-0 w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-6">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div>
          <h2 class="text-2xl font-bold text-gray-800 mb-4">Важно знать!</h2>
          <p class="text-gray-700 text-lg leading-relaxed">
            Соблюдение этих требований поможет избежать ошибок, ускорит процесс подготовки вашего заказа и обеспечит
            идеальное качество печати.
            Наши специалисты всегда готовы проконсультировать вас по любым вопросам.
          </p>
        </div>
      </div>
    </div>

    <!-- Основные требования -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
      <div
        class="bg-white rounded-2xl shadow-lg p-8 text-center transform transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 group">
        <div
          class="w-20 h-20 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-[#17B890] transition-colors duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-4">Форматы файлов</h3>
        <div class="bg-[#DEE5E5] rounded-xl p-4 mb-4">
          <p class="text-gray-700 font-medium">Рекомендуемые форматы:</p>
        </div>
        <ul class="text-gray-600 space-y-2 text-left">
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            <span class="font-medium">CDR, AI, SVG</span> - векторные
          </li>
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            <span class="font-medium">EPS, PDF</span> - универсальные
          </li>
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            <span class="font-medium">PSD, JPG</span> - растровые
          </li>
        </ul>
      </div>

      <div
        class="bg-white rounded-2xl shadow-lg p-8 text-center transform transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 group">
        <div
          class="w-20 h-20 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-[#118568] transition-colors duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-4">Разрешение</h3>
        <div class="bg-[#DEE5E5] rounded-xl p-4 mb-4">
          <p class="text-2xl font-bold text-[#118568]">300 DPI</p>
          <p class="text-gray-600 text-sm">Минимальное разрешение</p>
        </div>
        <ul class="text-gray-600 space-y-2 text-left">
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            Для офсетной печати
          </li>
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            Для цифровой печати
          </li>
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            Для широкоформатной печати
          </li>
        </ul>
      </div>

      <div
        class="bg-white rounded-2xl shadow-lg p-8 text-center transform transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 group">
        <div
          class="w-20 h-20 bg-[#5E807F] rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-[#118568] transition-colors duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-4">Цветовые профили</h3>
        <div class="bg-[#DEE5E5] rounded-xl p-4 mb-4">
          <p class="text-2xl font-bold text-[#118568]">CMYK</p>
          <p class="text-gray-600 text-sm">Для печати</p>
        </div>
        <ul class="text-gray-600 space-y-2 text-left">
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            <span class="font-medium">CMYK</span> - полноцветная печать
          </li>
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            <span class="font-medium">Pantone</span> - специальные цвета
          </li>
          <li class="flex items-center">
            <span class="w-2 h-2 bg-[#17B890] rounded-full mr-3"></span>
            <span class="font-medium">RGB</span> - только для цифровой
          </li>
        </ul>
      </div>
    </div>

    <!-- Подробная информация -->
    <div class="bg-white rounded-2xl shadow-xl p-8 mb-8 transform transition-all duration-300 hover:shadow-2xl">
      <div class="flex items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Подробные требования</h2>
        <div class="ml-4 px-3 py-1 bg-[#118568] text-white text-sm rounded-full">Важно</div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div>
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#17B890]" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            Технические параметры
          </h3>
          <ul class="space-y-3">
            <li class="flex items-start p-3 bg-[#DEE5E5] rounded-lg hover:bg-[#9DC5BB] transition-colors duration-300">
              <div class="flex-shrink-0 w-6 h-6 bg-[#118568] rounded-full flex items-center justify-center mr-3 mt-0.5">
                <span class="text-white text-xs font-bold">1</span>
              </div>
              <div>
                <strong class="text-gray-800">Отступы:</strong>
                <span class="text-gray-700">Важный контент на расстоянии не менее 5 мм от краев</span>
              </div>
            </li>
            <li class="flex items-start p-3 bg-[#DEE5E5] rounded-lg hover:bg-[#9DC5BB] transition-colors duration-300">
              <div class="flex-shrink-0 w-6 h-6 bg-[#118568] rounded-full flex items-center justify-center mr-3 mt-0.5">
                <span class="text-white text-xs font-bold">2</span>
              </div>
              <div>
                <strong class="text-gray-800">Вылеты:</strong>
                <span class="text-gray-700">Добавьте вылеты 2мм с каждой стороны для обрезки</span>
              </div>
            </li>
            <li class="flex items-start p-3 bg-[#DEE5E5] rounded-lg hover:bg-[#9DC5BB] transition-colors duration-300">
              <div class="flex-shrink-0 w-6 h-6 bg-[#118568] rounded-full flex items-center justify-center mr-3 mt-0.5">
                <span class="text-white text-xs font-bold">3</span>
              </div>
              <div>
                <strong class="text-gray-800">Шрифты:</strong>
                <span class="text-gray-700">Преобразуйте все шрифты в кривые или включите их в файл</span>
              </div>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#17B890]" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Изображения и цвета
          </h3>
          <ul class="space-y-3">
            <li class="flex items-start p-3 bg-[#DEE5E5] rounded-lg hover:bg-[#9DC5BB] transition-colors duration-300">
              <div class="flex-shrink-0 w-6 h-6 bg-[#17B890] rounded-full flex items-center justify-center mr-3 mt-0.5">
                <span class="text-white text-xs font-bold">4</span>
              </div>
              <div>
                <strong class="text-gray-800">Цвета:</strong>
                <span class="text-gray-700">Используйте сплошные цвета (Pantone) только при необходимости</span>
              </div>
            </li>
            <li class="flex items-start p-3 bg-[#DEE5E5] rounded-lg hover:bg-[#9DC5BB] transition-colors duration-300">
              <div class="flex-shrink-0 w-6 h-6 bg-[#17B890] rounded-full flex items-center justify-center mr-3 mt-0.5">
                <span class="text-white text-xs font-bold">5</span>
              </div>
              <div>
                <strong class="text-gray-800">Изображения:</strong>
                <span class="text-gray-700">Все изображения должны иметь высокое качество и разрешение</span>
              </div>
            </li>
            <li class="flex items-start p-3 bg-[#DEE5E5] rounded-lg hover:bg-[#9DC5BB] transition-colors duration-300">
              <div class="flex-shrink-0 w-6 h-6 bg-[#17B890] rounded-full flex items-center justify-center mr-3 mt-0.5">
                <span class="text-white text-xs font-bold">6</span>
              </div>
              <div>
                <strong class="text-gray-800">Слои:</strong>
                <span class="text-gray-700">Организуйте слои в файле для удобства обработки</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Советы -->
    <div class="bg-gradient-to-r from-[#118568] to-[#17B890] rounded-2xl shadow-xl p-8 text-white mb-12">
      <div class="flex items-center mb-6">
        <h2 class="text-2xl font-bold">Полезные советы</h2>
        <div class="ml-4 px-3 py-1 bg-white text-[#118568] text-sm rounded-full font-bold">Для новичков</div>
      </div>

      <p class="text-lg mb-6 opacity-90">
        Если вы новичок в создании макетов для печати, вот несколько советов от наших специалистов:
      </p>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 hover:bg-white/20 transition-all duration-300">
          <div class="text-3xl font-bold text-white mb-3">1</div>
          <h3 class="text-lg font-bold mb-2">Выбор программ</h3>
          <p class="opacity-90">Используйте профессиональные программы: CorelDRAW, Adobe Illustrator, Photoshop.</p>
        </div>

        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 hover:bg-white/20 transition-all duration-300">
          <div class="text-3xl font-bold text-white mb-3">2</div>
          <h3 class="text-lg font-bold mb-2">Проверка макета</h3>
          <p class="opacity-90">Всегда проверяйте макет на наличие ошибок перед отправкой в печать.</p>
        </div>

        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 hover:bg-white/20 transition-all duration-300">
          <div class="text-3xl font-bold text-white mb-3">3</div>
          <h3 class="text-lg font-bold mb-2">Консультация</h3>
          <p class="opacity-90">При сомнениях свяжитесь с нашими специалистами для консультации.</p>
        </div>
      </div>
    </div>

    <!-- Контактная информация -->
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">Остались вопросы?</h2>
      <p class="text-gray-600 mb-6">Наши специалисты всегда готовы помочь вам с подготовкой макетов</p>

      <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
        <a href="/contacts"
          class="bg-[#118568] text-white px-8 py-3 rounded-lg hover:bg-[#0f755a] transition-all duration-300 transform hover:scale-105 font-medium flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
          </svg>
          Связаться с нами
        </a>

        <!-- <a href="/upload" class="bg-[#17B890] text-white px-8 py-3 rounded-lg hover:bg-[#14a380] transition-all duration-300 transform hover:scale-105 font-medium flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          Загрузить макет
        </a>-->
      </div>
    </div>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>