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

<?php include_once('../../includes/header.php');?>

<main class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4 max-w-7xl">
        <h1 class="text-3xl font-bold mb-6">Сообщения с формы контактов</h1>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-3">Имя</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Телефон</th>
                        <th class="p-3">Связь</th>
                        <th class="p-3">Сообщение</th>
                        <th class="p-3">Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                        <tr class="border-b">
                            <td class="p-3"><?php echo htmlspecialchars($msg['name']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($msg['email']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($msg['phone']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($msg['preferred_contact']); ?></td>
                            <td class="p-3"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></td>
                            <td class="p-3"><?php echo $msg['created_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>