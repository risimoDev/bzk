<?php
require_once __DIR__ . '/includes/session.php';
$pageTitle = "Страница не найдена";
include_once __DIR__ . '/includes/header.php';
?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8 flex items-center">
  <div class="container mx-auto px-4 max-w-4xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <?php echo backButton(); ?>
      </div>
    </div>

    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="p-8 md:p-12 text-center">
        <div class="w-32 h-32 bg-[#DEE5E5] rounded-full flex items-center justify-center mx-auto mb-8">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-[#5E807F]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        
        <h1 class="text-6xl md:text-8xl font-bold text-gray-800 mb-4">404</h1>
        <h2 class="text-3xl md:text-4xl font-bold text-[#118568] mb-6">Страница не найдена</h2>
        <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
          Упс! Страница, которую вы ищете, не существует или была перемещена. Не волнуйтесь, это случается.
        </p>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-12">
          <a href="/" class="px-8 py-4 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl">
            Вернуться на главную
          </a>
          <a href="javascript:history.back()" class="px-8 py-4 bg-[#DEE5E5] text-[#118568] rounded-xl hover:bg-[#9DC5BB] transition-all duration-300 font-bold text-lg">
            Назад
          </a>
        </div>
        
      </div>
    </div>
    
    <div class="mt-8 text-center">
      <p class="text-gray-500">
        Возникли проблемы? <a href="/contacts" class="text-[#118568] hover:text-[#0f755a] font-medium">Свяжитесь с нами</a>
      </p>
    </div>
  </div>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>