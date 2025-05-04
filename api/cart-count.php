<?php
session_start();

header('Content-Type: application/json');

$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;

echo json_encode(['count' => $cart_count]);
?>