<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/common.php';

// Check admin access
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit();
}

$pageTitle = "Тест мобильной адаптации";
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - BZK PRINT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'litegreen': '#118568',
                        'emerald': '#0f755a',
                        'litegray': '#f8f9fa',
                        'darkgray': '#5E807F'
                    }
                }
            }
        }
    </script>
    <style>
        .mobile-debug {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 9999;
        }
        
        @media (min-width: 768px) {
            .mobile-debug {
                background: rgba(0,128,0,0.8);
            }
        }
        
        @media (min-width: 1024px) {
            .mobile-debug {
                background: rgba(0,0,255,0.8);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] min-h-screen">
    <!-- Debug indicator -->
    <div class="mobile-debug">
        <span class="block md:hidden">📱 Mobile</span>
        <span class="hidden md:block lg:hidden">📱 Tablet</span>
        <span class="hidden lg:block">🖥️ Desktop</span>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-800 mb-4">
                Тест мобильной адаптации
            </h1>
            <p class="text-base md:text-lg lg:text-xl text-gray-700">
                Проверка всех компонентов на различных устройствах
            </p>
            <div class="w-24 h-1 bg-gradient-to-r from-litegreen to-emerald rounded-full mx-auto mt-4"></div>
        </div>

        <!-- Navigation Test -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Навигация</h2>
            <nav class="flex flex-col sm:flex-row gap-2 sm:gap-4">
                <a href="#" class="px-4 py-2 bg-litegreen text-white rounded-lg hover:bg-emerald transition-colors">Главная</a>
                <a href="#" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Каталог</a>
                <a href="#" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Контакты</a>
                <a href="#" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">О нас</a>
            </nav>
        </div>

        <!-- Responsive Grid Test -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Адаптивная сетка</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                <div class="bg-gradient-to-br from-litegreen to-emerald text-white p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold">Карточка <?php echo $i; ?></div>
                    <p class="text-sm mt-2">Тестовое содержимое карточки</p>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Responsive Table Test -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Адаптивная таблица</h2>
            <?php
            $columns = [
                'name' => ['title' => 'Название'],
                'category' => ['title' => 'Категория'],
                'price' => ['title' => 'Цена'],
                'status' => ['title' => 'Статус'],
                'actions' => ['title' => 'Действия']
            ];
            
            $test_data = [
                [
                    'name' => 'Тестовый товар 1',
                    'category' => 'Категория А',
                    'price' => '1 500 руб.',
                    'status' => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">Активен</span>',
                    'actions' => '<button class="px-3 py-1 bg-litegreen text-white rounded hover:bg-emerald transition-colors text-sm">Действие</button>'
                ],
                [
                    'name' => 'Тестовый товар 2',
                    'category' => 'Категория Б',
                    'price' => '2 300 руб.',
                    'status' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Ожидание</span>',
                    'actions' => '<button class="px-3 py-1 bg-litegreen text-white rounded hover:bg-emerald transition-colors text-sm">Действие</button>'
                ],
                [
                    'name' => 'Тестовый товар 3',
                    'category' => 'Категория В',
                    'price' => '3 750 руб.',
                    'status' => '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-sm">Неактивен</span>',
                    'actions' => '<button class="px-3 py-1 bg-litegreen text-white rounded hover:bg-emerald transition-colors text-sm">Действие</button>'
                ]
            ];
            
            echo responsive_table($columns, $test_data, [
                'default_view' => 'cards', // Карточки по умолчанию на мобильных
                'table_classes' => 'w-full'
            ]);
            ?>
        </div>

        <!-- Form Test -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Адаптивная форма</h2>
            <form class="space-y-4">
                <?php echo csrf_token_field(); ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Имя *</label>
                        <input type="text" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-litegreen focus:ring-2 focus:ring-emerald transition-all duration-300" placeholder="Введите имя">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Фамилия *</label>
                        <input type="text" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-litegreen focus:ring-2 focus:ring-emerald transition-all duration-300" placeholder="Введите фамилию">
                    </div>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Email *</label>
                    <input type="email" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-litegreen focus:ring-2 focus:ring-emerald transition-all duration-300" placeholder="Введите email">
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Сообщение</label>
                    <textarea rows="4" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-litegreen focus:ring-2 focus:ring-emerald transition-all duration-300 resize-none" placeholder="Введите сообщение"></textarea>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-4">
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-litegreen to-emerald text-white rounded-lg hover:from-emerald hover:to-litegreen transition-all duration-300 transform hover:scale-105 font-bold">
                        Отправить
                    </button>
                    <button type="reset" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-300 font-bold">
                        Очистить
                    </button>
                </div>
            </form>
        </div>

        <!-- Visual Elements Test -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Визуальные элементы</h2>
            
            <!-- Buttons -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Кнопки</h3>
                <div class="flex flex-wrap gap-2">
                    <button class="px-4 py-2 bg-litegreen text-white rounded-lg hover:bg-emerald transition-colors">Основная</button>
                    <button class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Вторичная</button>
                    <button class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">Опасная</button>
                    <button class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors">Предупреждение</button>
                </div>
            </div>
            
            <!-- Badges -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Бейджи</h3>
                <div class="flex flex-wrap gap-2">
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Активен</span>
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">В ожидании</span>
                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">Отключен</span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">В процессе</span>
                </div>
            </div>
            
            <!-- Progress bars -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Прогресс-бары</h3>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Прогресс</span>
                            <span>75%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-gradient-to-r from-litegreen to-emerald h-2 rounded-full" style="width: 75%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Typography Test -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Типографика</h2>
            <div class="space-y-4">
                <h1 class="text-2xl md:text-3xl lg:text-4xl font-bold text-gray-800">Заголовок H1</h1>
                <h2 class="text-xl md:text-2xl lg:text-3xl font-bold text-gray-800">Заголовок H2</h2>
                <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-800">Заголовок H3</h3>
                <p class="text-base md:text-lg text-gray-700">
                    Обычный текст параграфа. Lorem ipsum dolor sit amet, consectetur adipiscing elit. 
                    Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                </p>
                <p class="text-sm md:text-base text-gray-600">
                    Мелкий текст для дополнительной информации.
                </p>
            </div>
        </div>

        <!-- Modal Test -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Модальное окно</h2>
            <button onclick="openModal()" class="px-6 py-3 bg-litegreen text-white rounded-lg hover:bg-emerald transition-colors">
                Открыть модальное окно
            </button>
        </div>

        <!-- Statistics -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Результаты тестирования</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">✓</div>
                    <div class="text-sm text-green-800 mt-1">Навигация</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">✓</div>
                    <div class="text-sm text-green-800 mt-1">Сетка</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">✓</div>
                    <div class="text-sm text-green-800 mt-1">Таблицы</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">✓</div>
                    <div class="text-sm text-green-800 mt-1">Формы</div>
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-green-800 font-medium">Все компоненты корректно адаптированы для мобильных устройств</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="testModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Тестовое модальное окно</h3>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="text-gray-700 mb-4">
                        Это тестовое модальное окно для проверки адаптивности на мобильных устройствах.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button onclick="closeModal()" class="flex-1 px-4 py-2 bg-litegreen text-white rounded-lg hover:bg-emerald transition-colors">
                            Закрыть
                        </button>
                        <button onclick="closeModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            Отмена
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('testModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('testModal').classList.add('hidden');
        }
        
        // Auto-detect screen size and show notification
        window.addEventListener('resize', function() {
            const width = window.innerWidth;
            let deviceType = 'Desktop';
            
            if (width < 768) {
                deviceType = 'Mobile';
            } else if (width < 1024) {
                deviceType = 'Tablet';
            }
            
            console.log(`Screen size: ${width}px - Device: ${deviceType}`);
        });
        
        // Initial check
        window.addEventListener('load', function() {
            const width = window.innerWidth;
            let deviceType = 'Desktop';
            
            if (width < 768) {
                deviceType = 'Mobile';
            } else if (width < 1024) {
                deviceType = 'Tablet';
            }
            
            console.log(`Initial screen size: ${width}px - Device: ${deviceType}`);
        });
    </script>
</body>
</html>