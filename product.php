<?php
session_start();
$pageTitle = "Товар | Типография";
// Подключение к базе данных
include_once __DIR__ . '/includes/db.php';

$product_id = $_GET['id'];

// Получение товара
$stmt = $pdo->prepare("SELECT * FROM products0 WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Получение характеристик
$stmt = $pdo->prepare("SELECT pa.id AS attribute_id, pa.name, pa.type, av.id AS value_id, av.value, av.price_modifier 
                       FROM product_attributes pa 
                       LEFT JOIN attribute_values av ON pa.id = av.attribute_id 
                       WHERE pa.product_id = ?");
$stmt->execute([$product_id]);
$attributes = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
?>

<h1><?php echo htmlspecialchars($product['name']); ?></h1>
<p><?php echo htmlspecialchars($product['description']); ?></p>

<form action="/cart/add" method="POST">
    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

    <?php foreach ($attributes as $attribute_id => $values): ?>
        <div>
            <strong><?php echo htmlspecialchars($values[0]['name']); ?>:</strong>
            <?php foreach ($values as $value): ?>
                <label>
                    <input type="radio" name="attributes[<?php echo $attribute_id; ?>]" value="<?php echo $value['id']; ?>" required>
                    <?php echo htmlspecialchars($value['value']); ?>
                    <?php if ($value['price_modifier'] > 0): ?>
                        (+<?php echo htmlspecialchars($value['price_modifier']); ?> руб.)
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit">Добавить в корзину</button>
</form>