<?php
session_start();

// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Обработка заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $attribute_id = intval($_POST['attribute_id']);
    $user_id = $_SESSION['user_id'];

    // Получение данных о товаре и характеристике
    $product_stmt = $pdo->prepare("SELECT * FROM calculator_products WHERE id = ?");
    $product_stmt->execute([$product_id]);
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

    $attribute_stmt = $pdo->prepare("SELECT * FROM calculator_attributes WHERE id = ?");
    $attribute_stmt->execute([$attribute_id]);
    $attribute = $attribute_stmt->fetch(PDO::FETCH_ASSOC);

    if ($product && $attribute) {
        $total_price = $product['base_price'] * $attribute['price_multiplier'] * $quantity;

        // Сохранение заказа в базу данных
        $stmt = $pdo->prepare("
            INSERT INTO orders1 (user_id, product_id, attribute_id, quantity, total_price, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $product_id, $attribute_id, $quantity, $total_price]);

        // уведомление
        $_SESSION['notifications'][] = ['type' => 'success', 'message' => 'Заказ успешно оформлен!'];

        header("Location: /client/dashboard");
        exit();
    } else {
        // Добавляем уведомление об ошибке
        $_SESSION['notifications'][] = ['type' => 'error', 'message' => 'Ошибка при оформлении заказа.'];
        header("Location: /catalog");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Оформление заказа | Типография</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans bg-gray-100">

  <!-- Шапка -->
  <?php include_once __DIR__ . '/includes/header.php'; ?>

  <!-- Страница подтверждения заказа -->
  <main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Заказ оформлен</h1>
    <p class="text-gray-700 text-center">Спасибо за ваш заказ! Мы свяжемся с вами в ближайшее время.</p>
  </main>

  <!-- Футер -->
  <?php include_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>