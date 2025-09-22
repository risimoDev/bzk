<?php
session_start();
$pageTitle = "Сообщения с сайта";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: /login");
    exit();
}

require_once '../../includes/db.php';

$stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pdo->query("UPDATE contact_messages SET status = 'read' WHERE status = 'new'");
?>

<?php include_once('../../includes/header.php'); ?>

<main class="min-h-screen bg-gradient-to-br from-[#DEE5E5] to-[#9DC5BB] py-8">
    <div class="container mx-auto px-4 max-w-7xl">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Сообщения с формы контактов</h1>
            <div class="w-24 h-1 bg-gradient-to-r from-[#118568] to-[#17B890] rounded-full mx-auto"></div>
        </div>

        <?php if (empty($messages)): ?>
            <div class="bg-white rounded-3xl shadow-xl p-12 text-center">
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-600">Сообщения не найдены</h3>
                <p class="text-gray-500 mt-2">Никто ещё не отправлял сообщения через форму контактов</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($messages as $msg): ?>
                    <div
                        class="bg-white rounded-2xl shadow-xl p-6 hover:shadow-2xl transition-shadow duration-300 border border-gray-100">
                        <!-- Заголовок карточки -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-[#118568] rounded-full flex items-center justify-center mr-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($msg['name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <?php echo date('d.m.Y H:i', strtotime($msg['created_at'])); ?></p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-[#17B890] text-white rounded-full text-xs font-medium">
                                Новое
                            </span>
                        </div>

                        <!-- Контактная информация -->
                        <div class="space-y-3 mb-4">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 mr-2" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <span class="text-sm text-gray-600"><?php echo htmlspecialchars($msg['email']); ?></span>
                            </div>

                            <?php if (!empty($msg['phone'])): ?>
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    <span class="text-sm text-gray-600"><?php echo htmlspecialchars($msg['phone']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($msg['preferred_contact'])): ?>
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z" />
                                    </svg>
                                    <span class="text-sm text-gray-600">Предпочитаемая связь:
                                        <?php echo htmlspecialchars($msg['preferred_contact']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Сообщение -->
                        <div class="bg-gray-50 rounded-xl p-4">
                            <h4 class="font-medium text-gray-800 mb-2">Сообщение:</h4>
                            <p class="text-gray-700 text-sm leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                        </div>

                        <!-- Действия -->
                        <div class="flex gap-2 mt-4 pt-4 border-t border-gray-200">
                            <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>"
                                class="flex-1 text-center px-3 py-2 bg-[#118568] text-white rounded-lg text-sm hover:bg-[#0f755a] transition-colors duration-300">
                                Ответить
                            </a>
                            <?php if (!empty($msg['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($msg['phone']); ?>"
                                    class="flex-1 text-center px-3 py-2 bg-[#17B890] text-white rounded-lg text-sm hover:bg-[#14a07a] transition-colors duration-300">
                                    Позвонить
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>