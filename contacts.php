<?php
session_start();
$pageTitle = "Контакты | Типография";
include_once __DIR__ . '/includes/header.php';

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);

    // Отправка email администратору
    $to = 'admin@example.com';
    $subject = 'Новое сообщение с сайта';
    $body = "Имя: $name\nEmail: $email\nСообщение: $message";
    mail($to, $subject, $body);

    echo "<p class='text-green-600 text-center'>Ваше сообщение успешно отправлено!</p>";
}
?>

<main class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">Контакты</h1>

  <!-- Форма обратной связи -->
  <section class="mb-12">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Свяжитесь с нами</h2>
    <form action="" method="POST" class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md">
      <div class="mb-4">
        <label for="name" class="block text-gray-700 font-medium mb-2">Имя</label>
        <input type="text" id="name" name="name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
      </div>
      <div class="mb-4">
        <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
        <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
      </div>
      <div class="mb-6">
        <label for="message" class="block text-gray-700 font-medium mb-2">Сообщение</label>
        <textarea id="message" name="message" rows="4" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required></textarea>
      </div>
      <button type="submit" name="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-300">
        Отправить
      </button>
    </form>
  </section>

  <!-- Карта -->
  <section class="mb-12">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Где мы находимся</h2>
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2244.430307459372!2d37.617635!3d55.755826!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46b54afc73d4b0c9%3A0x3d44d6cc5757cf4c!2z0JzQvtGB0LrQstCwLCDQnNC-0YHQutCy0LAsIDEwMTAwMA!5e0!3m2!1sru!2sru!4v1696523456789"
      width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
  </section>

  <!-- Контактная информация -->
  <section>
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Контактная информация</h2>
    <div class="text-center">
      <p class="text-gray-700 mb-2">Телефон: +7 (999) 123-45-67</p>
      <p class="text-gray-700 mb-2">Email: info@typography.ru</p>
      <p class="text-gray-700">Адрес: г. Москва, ул. Примерная, д. 10</p>
    </div>
  </section>
</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>