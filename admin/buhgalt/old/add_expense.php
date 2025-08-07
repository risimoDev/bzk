<?php
session_start();
$pageTitle = "Детали заказа | Админ-панель";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Подключение к базе данных
include_once('../../includes/db.php');

$stmt = $pdo->prepare("
    INSERT INTO order_expenses (order_accounting_id, description, amount, expense_date)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([
    $_POST['order_id'],
    $_POST['description'],
    $_POST['amount'],
    $_POST['expense_date']
]);

header("Location: /admin/buhgalt/orderdetail?id=" . $_POST['order_id']);
exit;
?>