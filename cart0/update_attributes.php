<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

// Verify CSRF token
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_index = intval($_POST['cart_index'] ?? -1);
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($cart_index >= 0 && $product_id > 0 && isset($_SESSION['cart'][$cart_index])) {
        $cart_item = $_SESSION['cart'][$cart_index];
        
        // Проверяем, что product_id совпадает
        if ($cart_item['product_id'] == $product_id) {
            // Получаем новые атрибуты из POST
            $new_attributes = [];
            
            // Ищем все атрибуты для данного товара
            $stmt = $pdo->prepare("SELECT id, name FROM product_attributes WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($attributes as $attribute) {
                $attr_key = 'attribute_' . $attribute['id'];
                if (isset($_POST[$attr_key])) {
                    $value_id = intval($_POST[$attr_key]);
                    if ($value_id > 0) {
                        $new_attributes[$attribute['id']] = $value_id;
                    }
                }
            }
            
            // Обновляем атрибуты в корзине
            $_SESSION['cart'][$cart_index]['attributes'] = $new_attributes;
            
            $_SESSION['notifications'][] = [
                'type' => 'success',
                'message' => 'Характеристики товара обновлены.'
            ];
        } else {
            $_SESSION['notifications'][] = [
                'type' => 'error',
                'message' => 'Ошибка: неверный идентификатор товара.'
            ];
        }
    } else {
        $_SESSION['notifications'][] = [
            'type' => 'error',
            'message' => 'Товар не найден в корзине.'
        ];
    }
}

// Перенаправляем обратно в корзину
header('Location: /cart');
exit();