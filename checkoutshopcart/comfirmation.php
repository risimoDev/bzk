<?php
session_start();
$pageTitle = "Подтверждение заказа | Типография";
include_once('../includes/header.php');
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Заказ успешно оформлен!</h1>
  <p class="text-center text-gray-600">Спасибо за ваш заказ. Мы свяжемся с вами в ближайшее время для уточнения деталей.</p>
  <a href="/" class="block text-center mt-6 px-6 py-3 bg-litegreen text-white rounded-lg hover:bg-emerald transition duration-300">
    Вернуться на главную
  </a>
</main>

<?php include_once('../includes/footer.php'); ?>