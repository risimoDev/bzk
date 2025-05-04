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
    INSERT INTO orders_accounting (source, client_name, description, income)
    VALUES ('external', ?, ?, ?)
");
$stmt->execute([
    $_POST['client_name'],
    $_POST['description'],
    $_POST['income']
]);

header("Location: /admin/buhgalt/accountingorders");
exit;
?>