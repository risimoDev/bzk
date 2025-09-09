<?php
session_start();
$pageTitle = "Условия использования";
?>
  <?php include_once __DIR__ . '/includes/header.php'; ?>
<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
  <div class="container mx-auto px-4 max-w-7xl">
    <!-- Вставка breadcrumbs и кнопки "Назад" -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
      <div class="w-full md:w-auto">
        <?php echo generateBreadcrumbs($pageTitle ?? ''); ?>
      </div>
      <div class="w-full md:w-auto">
        <?php echo backButton(); ?>
      </div>
    </div>

    <div class="text-center mb-12">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">Условия использования</h1>
      <p class="text-xl text-gray-700 max-w-3xl mx-auto">
        Пожалуйста, внимательно ознакомьтесь с этими условиями перед использованием нашего сайта
      </p>
      <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
    </div>

    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
      <div class="p-6 border-b border-[#DEE5E5]">
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Общие положения</h2>
        <div class="w-12 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full"></div>
      </div>
      
      <div class="p-8">
        <div class="prose prose-lg max-w-none">
          <p class="text-gray-700 leading-relaxed mb-6">
            Настоящие Условия использования регулируют отношения между ИП Кобелев Лев Алексеевич (далее — "Оператор") 
            и любым физическим или юридическим лицом ("Пользователь"), использующим веб-сайт <a href="https://bzkprint.ru" class="text-[#118568] hover:underline">https://bzkprint.ru</a> (далее — "Сайт").
          </p>

          <div class="space-y-8">
            <!-- Раздел 1 -->
            <div class="bg-gray-50 rounded-2xl p-6">
              <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <div class="w-8 h-8 bg-[#118568] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                  <span class="text-white font-bold">1</span>
                </div>
                Предмет условий
              </h3>
              <div class="space-y-4 text-gray-700 leading-relaxed">
                <p>
                  <span class="font-bold">1.1.</span> Оператор предоставляет Пользователю доступ к Сайту и его функционалу 
                  в соответствии с настоящими Условиями.
                </p>
                <p>
                  <span class="font-bold">1.2.</span> Сайт предоставляет возможность ознакомиться с ассортиментом товаров и услуг, 
                  оформить заказ, получить консультацию и другую информацию.
                </p>
                <p>
                  <span class="font-bold">1.3.</span> Использование Сайта означает безоговорочное согласие Пользователя 
                  с настоящими Условиями и указанными в них правилами.
                </p>
              </div>
            </div>

            <!-- Раздел 2 -->
            <div class="bg-gray-50 rounded-2xl p-6">
              <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <div class="w-8 h-8 bg-[#17B890] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                  <span class="text-white font-bold">2</span>
                </div>
                Права и обязанности сторон
              </h3>
              <div class="space-y-4 text-gray-700 leading-relaxed">
                <p><span class="font-bold">2.1. Права и обязанности Оператора:</span></p>
                <ul class="list-disc list-inside ml-4 space-y-2">
                  <li>Предоставлять Пользователю доступ к Сайту и его функционалу;</li>
                  <li>Обеспечивать работоспособность Сайта и его соответствие настоящим Условиям;</li>
                  <li>Обрабатывать персональные данные Пользователя в соответствии с Политикой конфиденциальности;</li>
                  <li>Изменять функционал Сайта без предварительного уведомления Пользователя;</li>
                  <li>Ограничивать доступ Пользователя к Сайту в случае нарушения настоящих Условий.</li>
                </ul>
                
                <p><span class="font-bold">2.2. Права и обязанности Пользователя:</span></p>
                <ul class="list-disc list-inside ml-4 space-y-2">
                  <li>Использовать Сайт в соответствии с настоящими Условиями и законодательством РФ;</li>
                  <li>Предоставлять достоверную информацию при регистрации и оформлении заказов;</li>
                  <li>Соблюдать авторские и иные права Оператора;</li>
                  <li>Не использовать Сайт в коммерческих целях без согласия Оператора;</li>
                  <li>Не пытаться получить несанкционированный доступ к Сайту или его частям.</li>
                </ul>
              </div>
            </div>

            <!-- Раздел 3 -->
            <div class="bg-gray-50 rounded-2xl p-6">
              <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <div class="w-8 h-8 bg-[#5E807F] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                  <span class="text-white font-bold">3</span>
                </div>
                Использование сайта
              </h3>
              <div class="space-y-4 text-gray-700 leading-relaxed">
                <p>
                  <span class="font-bold">3.1.</span> Пользователь обязуется использовать Сайт только в законных целях 
                  и не нарушать права третьих лиц.
                </p>
                <p>
                  <span class="font-bold">3.2.</span> Запрещается:
                </p>
                <ul class="list-disc list-inside ml-4 space-y-2">
                  <li>Размещать информацию, противоречащую законодательству РФ;</li>
                  <li>Использовать автоматизированные средства для сбора информации с Сайта;</li>
                  <li>Пытаться нарушить безопасность или функциональность Сайта;</li>
                  <li>Копировать или воспроизводить материалы Сайта без разрешения Оператора;</li>
                  <li>Использовать Сайт для рассылки спама или иной нежелательной информации.</li>
                </ul>
                <p>
                  <span class="font-bold">3.3.</span> Оператор оставляет за собой право в любое время изменить, 
                  модифицировать, добавить или удалить части настоящих Условий без предварительного уведомления.
                </p>
              </div>
            </div>

            <!-- Раздел 4 -->
            <div class="bg-gray-50 rounded-2xl p-6">
              <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <div class="w-8 h-8 bg-[#9DC5BB] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                  <span class="text-white font-bold">4</span>
                </div>
                Оформление заказов
              </h3>
              <div class="space-y-4 text-gray-700 leading-relaxed">
                <p>
                  <span class="font-bold">4.1.</span> Оформление заказа через Сайт означает согласие Пользователя 
                  с условиями оплаты и доставки, указанными на Сайте.
                </p>
                <p>
                  <span class="font-bold">4.2.</span> Оператор оставляет за собой право отказать в оформлении заказа 
                  без объяснения причин.
                </p>
                <p>
                  <span class="font-bold">4.3.</span> Все заказы обрабатываются в порядке очереди, 
                  установленной внутренними правилами Оператора.
                </p>
                <p>
                  <span class="font-bold">4.4.</span> Пользователь несет ответственность за достоверность 
                  предоставленной информации при оформлении заказа.
                </p>
              </div>
            </div>

            <!-- Раздел 5 -->
            <div class="bg-gray-50 rounded-2xl p-6">
              <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <div class="w-8 h-8 bg-[#118568] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                  <span class="text-white font-bold">5</span>
                </div>
                Оплата и доставка
              </h3>
              <div class="space-y-4 text-gray-700 leading-relaxed">
                <p>
                  <span class="font-bold">5.1.</span> Оплата товаров и услуг осуществляется в соответствии 
                  с условиями, указанными в разделе "Оплата и доставка" Сайта.
                </p>
                <p>
                  <span class="font-bold">5.2.</span> Оператор не несет ответственности за задержки в доставке, 
                  вызванные форс-мажорными обстоятельствами.
                </p>
                <p>
                  <span class="font-bold">5.3.</span> В случае отказа от заказа после его оплаты, 
                  денежные средства возвращаются в порядке, установленном законодательством РФ.
                </p>
              </div>
            </div>

            <!-- Раздел 6 -->
            <div class="bg-gray-50 rounded-2xl p-6">
              <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <div class="w-8 h-8 bg-[#17B890] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                  <span class="text-white font-bold">6</span>
                </div>
              </h3>
              <div class="space-y-4 text-gray-700 leading-relaxed">
                <p>
                  <span class="font-bold">6.1.</span> Вся информация на Сайте предоставляется "как есть" 
                  без каких-либо гарантий.
                </p>
                <p>
                  <span class="font-bold">6.2.</span> Оператор не гарантирует бесперебойную и безошибочную работу Сайта.
                </p>
                <p>
                  <span class="font-bold">6.3.</span> Оператор не несет ответственности за любые прямые или косвенные 
                  убытки, возникшие в результате использования или невозможности использования Сайта.
                </p>
                <p>
                  <span class="font-bold">6.4.</span> Вся информация на Сайте носит ознакомительный характер 
                  и не является публичной офертой.
                </p>
              </div>
            </div>

            <!-- Раздел 7 -->
            <div class="bg-gray-50 rounded-2xl p-6">
              <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <div class="w-8 h-8 bg-[#5E807F] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                  <span class="text-white font-bold">7</span>
                </div>
                Заключительные положения
              </h3>
              <div class="space-y-4 text-gray-700 leading-relaxed">
                <p>
                  <span class="font-bold">7.1.</span> Настоящие Условия регулируются законодательством 
                  Российской Федерации.
                </p>
                <p>
                  <span class="font-bold">7.2.</span> В случае возникновения споров и разногласий, 
                  стороны приложат усилия для их разрешения путем переговоров.
                </p>
                <p>
                  <span class="font-bold">7.3.</span> Если иное не предусмотрено законодательством, 
                  споры рассматриваются в суде по месту нахождения Оператора.
                </p>
                <p>
                  <span class="font-bold">7.4.</span> Оператор оставляет за собой право вносить изменения 
                  в настоящие Условия в любое время без предварительного уведомления.
                </p>
                <p>
                  <span class="font-bold">7.5.</span> Актуальная версия Условий использования 
                  всегда доступна на Сайте по адресу <a href="https://bzkprint.ru/terms" class="text-[#118568] hover:underline">https://bzkprint.ru/terms</a>.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

  <?php include_once __DIR__ . '/includes/footer.php'; ?>