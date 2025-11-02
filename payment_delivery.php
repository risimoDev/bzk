<?php
require_once __DIR__ . '/includes/session.php';
$pageTitle = "Оплата и доставка";
?>

<?php include_once __DIR__ . '/includes/header.php';?>

<main class="min-h-screen bg-pattern from-[#DEE5E5] to-[#9DC5BB] py-8">
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

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 mb-6">
      <div>
        <h1 class="text-4xl md:text-5xl font-extrabold text-gray-800">Оплата и доставка</h1>
        <p class="text-gray-600 mt-2">Узнайте все о способах оплаты и условиях доставки наших товаров и услуг</p>
        <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mt-4"></div>
      </div>
    </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Оплата -->
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
                <div class="p-6 border-b border-[#DEE5E5] bg-gradient-to-r from-[#118568] to-[#0f755a]">
                    <h2 class="text-3xl font-bold text-white mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-3" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Оплата
                    </h2>
                </div>

                <div class="p-8">
                    <div class="space-y-8">
                        <!-- Наличными -->
                        <div
                            class="flex items-start p-6 bg-[#DEE5E5] rounded-2xl hover:bg-[#9DC5BB] transition-colors duration-300">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Наличными</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Оплата наличными производится при заказе лично в офисе, мы берем полную предоплату
                                    за наши услуги.
                                </p>
                            </div>
                        </div>

                        <!-- Банковская карта -->
                        <div
                            class="flex items-start p-6 bg-[#9DC5BB] rounded-2xl hover:bg-[#5E807F] transition-colors duration-300">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Через СБП</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Оплата СБП возможна как онлайн на сайте через QR-код,
                                    так ипо номеру телефона.
                                </p>
                            </div>
                        </div>

                        <!-- Онлайн оплата -->
                        <!--<div
                            class="flex items-start p-6 bg-[#5E807F] rounded-2xl hover:bg-[#17B890] transition-colors duration-300">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white mb-2">Онлайн оплата</h3>
                                <p class="text-white leading-relaxed">
                                    Совершайте оплату прямо на сайте через защищенное соединение.
                                    Принимаем все основные платежные системы и электронные кошельки.
                                </p>
                            </div>
                        </div> -->

                        <!-- Безналичный расчет -->
                        <div
                            class="flex items-start p-6 bg-[#17B890] rounded-2xl hover:bg-[#118568] transition-colors duration-300">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-white rounded-full flex items-center justify-center mr-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[#118568]" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white mb-2">Безналичный расчет</h3>
                                <p class="text-white leading-relaxed">
                                    Для юридических лиц предусмотрена оплата по безналичному расчету.
                                    После оформления заказа вы получите счет на оплату.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Дополнительная информация об оплате -->
                    <div class="mt-12 pt-8 border-t border-[#DEE5E5]">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6">Дополнительная информация</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-2xl p-6">
                                <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Безопасность
                                </h4>
                                <p class="text-gray-700 text-sm">
                                    Все платежи защищены современными системами шифрования.
                                    Мы не храним данные ваших банковских карт.
                                </p>
                            </div>

                            <div class="bg-gray-50 rounded-2xl p-6">
                                <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Сроки
                                </h4>
                                <p class="text-gray-700 text-sm">
                                    Оплата по безналичному расчету может занять до 3 банковских дней.
                                    После поступления средств заказ переходит в статус "Оплачен".
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Доставка -->
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
                <div class="p-6 border-b border-[#DEE5E5] bg-gradient-to-r from-[#17B890] to-[#118568]">
                    <h2 class="text-3xl font-bold text-white mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-3" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Доставка
                    </h2>
                </div>

                <div class="p-8">
                    <div class="space-y-8">
                        <!-- Самовывоз -->
                        <div
                            class="flex items-start p-6 bg-[#DEE5E5] rounded-2xl hover:bg-[#9DC5BB] transition-colors duration-300">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Самовывоз</h3>
                                <p class="text-gray-700 leading-relaxed mb-4">
                                    Заберите заказ самостоятельно по адресу нашего офиса.
                                    Предварительно согласуйте время получения с менеджером.
                                </p>
                                <div class="bg-white rounded-xl p-4">
                                    <div class="flex items-center text-sm text-gray-600 mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-[#118568]"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0L5 15.243V19a2 2 0 01-2 2H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2h-3.586l-4.243-4.243z" />
                                        </svg>
                                        <span>Адрес офиса:</span>
                                    </div>
                                    <p class="font-medium text-gray-800">г. Пермь, ул. Сухобруса, д. 27. Офис 101</p>
                                </div>
                            </div>
                        </div>

                        <!-- Курьерская доставка -->
                        <div
                            class="flex items-start p-6 bg-[#9DC5BB] rounded-2xl hover:bg-[#5E807F] transition-colors duration-300">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-[#17B890] rounded-full flex items-center justify-center mr-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Курьерская доставка</h3>
                                <p class="text-gray-700 leading-relaxed mb-4">
                                    Доставка курьером по городу Перми и области.
                                    Стоимость доставки зависит от веса и объема заказа.
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-white rounded-xl p-4">
                                        <div class="text-sm text-gray-600 mb-1">Стоимость:</div>
                                        <div class="font-bold text-[#118568]">от 300 ₽</div>
                                    </div>
                                    <div class="bg-white rounded-xl p-4">
                                        <div class="text-sm text-gray-600 mb-1">Сроки:</div>
                                        <div class="font-bold text-[#118568]">1-3 дня</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Почта России -->
                        <div
                            class="flex items-start p-6 bg-[#5E807F] rounded-2xl hover:bg-[#17B890] transition-colors duration-300">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-[#118568] rounded-full flex items-center justify-center mr-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white mb-2">Почта России</h3>
                                <p class="text-white leading-relaxed mb-4">
                                    Отправка заказов Почтой России по всей России.
                                    Отслеживание посылки по трек-номеру.
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-white bg-opacity-20 rounded-xl p-4">
                                        <div class="text-sm text-white mb-1">Стоимость:</div>
                                        <div class="font-bold text-white">от 400 ₽</div>
                                    </div>
                                    <div class="bg-white bg-opacity-20 rounded-xl p-4">
                                        <div class="text-sm text-white mb-1">Сроки:</div>
                                        <div class="font-bold text-white">3-14 дней</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Транспортные компании -->
                        <div
                            class="flex items-start p-6 bg-[#17B890] rounded-2xl hover:bg-[#118568] transition-colors duration-300">
                            <div
                                class="flex-shrink-0 w-12 h-12 bg-white rounded-full flex items-center justify-center mr-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[#118568]" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white mb-2">Транспортные компании</h3>
                                <p class="text-white leading-relaxed mb-4">
                                    Отправка через транспортную компанию СДЭК.
                                    Быстрая и надежная доставка по всей России.
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-white bg-opacity-20 rounded-xl p-4">
                                        <div class="text-sm text-white mb-1">Стоимость:</div>
                                        <div class="font-bold text-white">от 500 ₽</div>
                                    </div>
                                    <div class="bg-white bg-opacity-20 rounded-xl p-4">
                                        <div class="text-sm text-white mb-1">Сроки:</div>
                                        <div class="font-bold text-white">1-7 дней</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Дополнительная информация о доставке -->
                    <div class="mt-12 pt-8 border-t border-[#DEE5E5]">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6">Дополнительная информация</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-2xl p-6">
                                <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Время отправки
                                </h4>
                                <p class="text-gray-700 text-sm">
                                    Заказы, оформленные до 15:00, отправляются в тот же день.
                                    Заказы после 15:00 отправляются на следующий рабочий день.
                                </p>
                            </div>

                            <div class="bg-gray-50 rounded-2xl p-6">
                                <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Упаковка
                                </h4>
                                <p class="text-gray-700 text-sm">
                                    Все заказы тщательно упаковываются в прочные коробки или конверты
                                    для обеспечения сохранности при транспортировке.
                                </p>
                            </div>

                            <div class="bg-gray-50 rounded-2xl p-6">
                                <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Страхование
                                </h4>
                                <p class="text-gray-700 text-sm">
                                    По запросу возможно оформление страхования посылки.
                                    Стоимость страховки определяет транспортная компания.
                                </p>
                            </div>

                            <div class="bg-gray-50 rounded-2xl p-6">
                                <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-[#118568]"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    Контакт
                                </h4>
                                <p class="text-gray-700 text-sm">
                                    При возникновении вопросов по доставке свяжитесь с нами:
                                    <a href="tel:+79223040465" class="text-[#118568] hover:underline">+7 (922)
                                        304-04-65</a>
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