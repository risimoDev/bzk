<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad(); // safeLoad, чтобы не падало, если .env отсутствует

$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];

// Настройки кэширования
$cache_enabled = true;
$cache_time = 300; // 5 минут

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка подключения к базе данных: " . $e->getMessage());
    die("Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.");
}

// Функция для кэширования запросов
function cachedQuery($pdo, $sql, $params = [], $cache_key = null) {
    global $cache_enabled, $cache_time;
    
    if (!$cache_enabled) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    if (!$cache_key) {
        $cache_key = md5($sql . serialize($params));
    }
    
    $cache_file = __DIR__ . '/../cache/' . $cache_key . '.cache';
    
    // Проверяем актуальность кэша
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        return unserialize(file_get_contents($cache_file));
    }
    
    // Выполняем запрос и кэшируем результат
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll();
    
    // Сохраняем в кэш
    if (!is_dir(dirname($cache_file))) {
        mkdir(dirname($cache_file), 0755, true);
    }
    file_put_contents($cache_file, serialize($result));
    
    return $result;
}
?>