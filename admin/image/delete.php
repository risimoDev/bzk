<?php
session_start();

// Подключение к базе данных
include_once('../../includes/db.php');

$image_id = $_POST['image_id'] ?? null;

if ($image_id) {
    // Получение пути к изображению
    $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($image) {
        // Удаление файла
        $filePath = __DIR__ . '/../' . $image['image_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Удаление записи из базы данных
        $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
        $stmt->execute([$image_id]);
    }
}

header("Location: /admin/images?product_id=" . $_GET['product_id']);
exit();
?>