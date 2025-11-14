<?php
session_start();
$pageTitle = "Подтверждение заказа | Типография";

?>

<?php include_once('../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] bg-pattern py-8">
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
      <!-- Верхняя часть с успехом -->
      <div class="bg-gradient-to-r from-[#118568] to-[#17B890] p-12 text-center">
        <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-6">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-white" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h1 class="text-4xl font-bold text-white mb-4">Заказ успешно оформлен!</h1>
        <p class="text-xl text-white/90 max-w-2xl mx-auto">
          Спасибо за ваш заказ. Мы свяжемся с вами в ближайшее время для уточнения деталей.
        </p>
      </div>

      <!-- Информация о заказе -->
      <div class="p-8">
        <div class="text-center mb-10">
          <h2 class="text-2xl font-bold text-gray-800 mb-2">Что дальше?</h2>
          <p class="text-gray-600">Следующие шаги после оформления заказа</p>
          <div class="w-16 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
          <div class="text-center p-6 bg-[#DEE5E5] rounded-2xl hover:bg-[#9DC5BB] transition-colors duration-300">
            <div class="w-16 h-16 bg-[#118568] rounded-full flex items-center justify-center mx-auto mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Подтверждение</h3>
            <p class="text-gray-600 text-sm">Мы отправили подтверждение на ваш email</p>
          </div>

          <div class="text-center p-6 bg-[#9DC5BB] rounded-2xl hover:bg-[#5E807F] transition-colors duration-300">
            <div class="w-16 h-16 bg-[#17B890] rounded-full flex items-center justify-center mx-auto mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Обработка</h3>
            <p class="text-gray-700 text-sm">Наш менеджер свяжется с вами в течение часа (В рабочие дни)</p>
          </div>

          <div class="text-center p-6 bg-[#5E807F] rounded-2xl hover:bg-[#118568] transition-colors duration-300">
            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#5E807F]" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Доставка</h3>
            <p class="text-white/90 text-sm">Заказ будет доставлен в указанный срок</p>
          </div>
        </div>

        <!-- Номер заказа -->
        <?php if (isset($_SESSION['order_id'])): ?>
          <div class="bg-gradient-to-r from-[#17B890] to-[#118568] rounded-2xl p-6 text-center text-white mb-8">
            <h3 class="text-xl font-bold mb-2">Номер вашего заказа</h3>
            <div class="text-3xl font-bold tracking-wider">
              #<?php echo htmlspecialchars($_SESSION['order_id']); ?>
            </div>
            <p class="text-white/90 mt-2">Сохраните этот номер для отслеживания заказа</p>
          </div>
        <?php endif; ?>

        <!-- Кнопки действий -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/"
            class="px-8 py-4 bg-gradient-to-r from-[#118568] to-[#0f755a] text-white rounded-xl hover:from-[#0f755a] hover:to-[#0d654a] transition-all duration-300 transform hover:scale-105 font-bold text-lg shadow-lg hover:shadow-xl text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Вернуться на главную
          </a>

          <a href="/client/orders"
            class="px-8 py-4 bg-[#DEE5E5] text-[#118568] rounded-xl hover:bg-[#9DC5BB] transition-all duration-300 font-bold text-lg text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            Мои заказы
          </a>

          <a href="/catalog"
            class="px-8 py-4 bg-[#5E807F] text-white rounded-xl hover:bg-[#4a6665] transition-all duration-300 font-bold text-lg text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Продолжить покупки
          </a>
        </div>
      </div>

      <!-- Дополнительная информация -->
      <div class="bg-gray-50 p-8 border-t border-gray-200">
        <div class="text-center">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Есть вопросы?</h3>
          <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <div class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#118568] mr-2" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              <span class="text-gray-700">+7 (922) 304-04-65</span>
            </div>
            <div class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#118568] mr-2" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <span class="text-gray-700">bzkprint@yandex.ru</span>
            </div>
          </div>
          <p class="text-gray-600 text-sm mt-4">
            Наши специалисты работают с 9:00 до 18:00, Пн-Пт
          </p>
        </div>
      </div>
    </div>
  </div>
</main>

<script type="module">
  import { poopetti, rainPoop } from '/assets/js/poopetti/index.js';
  window.addEventListener('DOMContentLoaded', () => {
    try {
      poopetti({ content: ['🎉', '🧾', '⭐️', '💚'], duration: 1200, density: 140 });
      setTimeout(() => {
        rainPoop({ emoji: ['🎉', '✨', '💚'], duration: 2200, density: 140 });
      }, 300);
    } catch (e) { /* ignore */ }
  });

  // Фокус на блок с номером заказа для видимости
  try {
    const orderBlock = document.querySelector('[class*="from-[#17B890]"]');
    if (orderBlock) orderBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
  } catch (_) { }
</script>

<?php include_once('../includes/footer.php'); ?>