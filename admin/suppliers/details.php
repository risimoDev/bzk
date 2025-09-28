<?php
session_start();
$pageTitle = "Информация о поставщике";

// Проверка авторизации
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');
include_once('../../includes/security.php');
include_once('../../includes/common.php');

// ---------- Получение ID поставщика ----------
$supplier_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($supplier_id <= 0) {
    add_notification('error', 'Некорректный ID поставщика.');
    header("Location: /admin/suppliers");
    exit();
}

// ---------- Получение данных поставщика ----------
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    add_notification('error', 'Поставщик не найден.');
    header("Location: /admin/suppliers");
    exit();
}

// ---------- Обработка удаления ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_supplier'])) {
    verify_csrf();

    try {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->execute([$supplier_id]);

        add_notification('success', 'Поставщик успешно удален.');
        header("Location: /admin/suppliers");
        exit();
    } catch (PDOException $e) {
        add_notification('error', 'Ошибка при удалении поставщика: ' . $e->getMessage());
    }
}
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
    <div class="container mx-auto px-4 max-w-7xl">
        <!-- breadcrumbs + назад -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div><?php echo generateBreadcrumbs($pageTitle ?? ''); ?></div>
            <div class="flex gap-2">
                <?php echo backButton(); ?>
            </div>
        </div>

        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Информация о поставщике</h1>
            <p class="text-xl text-gray-700"><?php echo e($supplier['name']); ?></p>
            <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto mt-4"></div>
        </div>

        <!-- уведомления -->
        <?php echo display_notifications(); ?>

        <!-- Детали поставщика -->
        <div class="bg-white rounded-3xl shadow-2xl p-8 max-w-4xl mx-auto">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between mb-8 pb-6 border-b border-gray-200">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800"><?php echo e($supplier['name']); ?></h2>
                    <?php if (!empty($supplier['contact_person'])): ?>
                        <p class="text-gray-600 mt-2"><?php echo e($supplier['contact_person']); ?></p>
                    <?php endif; ?>
                </div>
                <span
                    class="px-3 py-1 rounded-full text-sm font-medium mt-4 md:mt-0 <?php echo $supplier['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $supplier['is_active'] ? 'Активен' : 'Неактивен'; ?>
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Основная информация -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-100">Основная информация
                    </h3>

                    <div class="space-y-4">
                        <?php if (!empty($supplier['phone'])): ?>
                            <div class="flex">
                                <div class="w-32 text-gray-500">Телефон</div>
                                <div class="flex-1"><?php echo e($supplier['phone']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($supplier['email'])): ?>
                            <div class="flex">
                                <div class="w-32 text-gray-500">Email</div>
                                <div class="flex-1">
                                    <a href="mailto:<?php echo e($supplier['email']); ?>"
                                        class="text-[#118568] hover:underline">
                                        <?php echo e($supplier['email']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($supplier['website'])): ?>
                            <div class="flex">
                                <div class="w-32 text-gray-500">Сайт</div>
                                <div class="flex-1">
                                    <a href="<?php echo e($supplier['website']); ?>" target="_blank"
                                        class="text-[#118568] hover:underline">
                                        <?php echo e($supplier['website']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($supplier['address'])): ?>
                            <div class="flex">
                                <div class="w-32 text-gray-500">Адрес</div>
                                <div class="flex-1"><?php echo nl2br(e($supplier['address'])); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($supplier['service_cost'])): ?>
                            <div class="flex">
                                <div class="w-32 text-gray-500">Стоимость услуг</div>
                                <div class="flex-1 font-bold text-[#118568]">
                                    <?php echo number_format($supplier['service_cost'], 2, '.', ' '); ?> руб.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Условия сотрудничества -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-100">Условия
                        сотрудничества</h3>

                    <div class="space-y-4">
                        <?php if (!empty($supplier['payment_terms'])): ?>
                            <div>
                                <div class="text-gray-500 mb-1">Условия оплаты</div>
                                <div><?php echo nl2br(e($supplier['payment_terms'])); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($supplier['delivery_terms'])): ?>
                            <div>
                                <div class="text-gray-500 mb-1">Условия доставки</div>
                                <div><?php echo nl2br(e($supplier['delivery_terms'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Примечания -->
            <?php if (!empty($supplier['notes'])): ?>
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Примечания</h3>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <?php echo nl2br(e($supplier['notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Даты -->
            <div class="mt-8 pt-6 border-t border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-500">
                <div>
                    <span class="font-medium">Создан:</span>
                    <?php echo date('d.m.Y H:i', strtotime($supplier['created_at'])); ?>
                </div>
                <div>
                    <span class="font-medium">Обновлен:</span>
                    <?php echo date('d.m.Y H:i', strtotime($supplier['updated_at'])); ?>
                </div>
            </div>

            <!-- Кнопки действий -->
            <div
                class="mt-8 pt-6 border-t border-gray-200 flex flex-col sm:flex-row sm:justify-end space-y-4 sm:space-y-0 sm:space-x-4">
                <a href="/admin/suppliers"
                    class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-300 font-medium text-center">
                    Назад к списку
                </a>
                <a href="/admin/suppliers/edit?id=<?php echo $supplier['id']; ?>"
                    class="px-6 py-3 bg-[#118568] text-white rounded-lg hover:bg-[#0f755a] transition-colors duration-300 font-medium text-center">
                    Редактировать
                </a>

                <!-- Кнопка удаления с подтверждением -->
                <form method="POST" class="inline"
                    onsubmit="return confirm('Вы уверены, что хотите удалить этого поставщика? Это действие нельзя отменить.')">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="delete_supplier" value="1">
                    <button type="submit"
                        class="w-full sm:w-auto px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300 font-medium text-center">
                        Удалить поставщика
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include_once('../../includes/footer.php'); ?>