<?php
session_start();
$pageTitle = "Детали заказа | Админ-панель";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');

// Получаем все заказы из orders, которых нет в order_accounting
$stmt = $pdo->query("
    SELECT o.id, o.user_id, o.total_price, o.created_at, u.name
    FROM orders o
    LEFT JOIN orders_accounting oa ON oa.order_id = o.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE oa.id IS NULL
");

$orders = $stmt->fetchAll();

$added = 0;

foreach ($orders as $order) {
    $stmtInsert = $pdo->prepare("
        INSERT INTO orders_accounting (order_id, source, client_name, income, created_at)
        VALUES (?, 'site', ?, ?, ?)
    ");
    $stmtInsert->execute([
        $order['id'],
        $order['name'] ?? 'Неизвестный клиент',
        $order['total_price'],
        $order['created_at']
    ]);
    $added++;
}

// Перенаправление обратно с сообщением
header("Location: admin/buhgalt/accountingorders?synced={$added}");
exit;
?>