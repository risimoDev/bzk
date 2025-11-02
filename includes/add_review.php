<?php
// includes/add_review.php
require_once __DIR__ . '/session.php'; // assumes this starts session
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

// Проверяем метод
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Метод не разрешен']);
    exit;
}

// Проверяем авторизацию
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Только авторизованные пользователи могут оставлять отзывы.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = trim($_POST['name'] ?? ($_SESSION['user_name'] ?? ''));

// Получаем поля
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$message = trim($_POST['message'] ?? '');

// Валидация базовая
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success'=>false,'message'=>'Некорректный рейтинг.']);
    exit;
}
if (mb_strlen($message) < 5) {
    echo json_encode(['success'=>false,'message'=>'Текст отзыва слишком короткий.']);
    exit;
}
if (empty($user_name)) $user_name = 'Пользователь';

// Turnstile validation
$cf_response = $_POST['cf-turnstile-response'] ?? $_POST['cf_turnstile_response'] ?? null;
if (!$cf_response) {
    echo json_encode(['success'=>false,'message'=>'Пожалуйста, подтвердите, что вы не робот.']);
    exit;
}

// секретный ключ из окружения
$secret = getenv('CLOUDFLARE_TURNSTILE_SECRET_KEY') ?: ($_ENV['CLOUDFLARE_TURNSTILE_SECRET_KEY'] ?? null);
if (!$secret) {
    // для безопасности: не принимать без ключа
    echo json_encode(['success'=>false,'message'=>'Серверная настройка Turnstile не выполнена.']);
    exit;
}

// Проверяем Turnstile через API (POST)
$verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
$postdata = http_build_query([
    'secret' => $secret,
    'response' => $cf_response,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
]);

$opts = ['http' =>
    [
        'method'  => 'POST',
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => $postdata,
        'timeout' => 10
    ]
];
$context  = stream_context_create($opts);
$result = @file_get_contents($verify_url, false, $context);
if ($result === false) {
    echo json_encode(['success'=>false,'message'=>'Ошибка проверки Turnstile. Пожалуйста, попробуйте позже.']);
    exit;
}
$resp = json_decode($result, true);
if (empty($resp) || empty($resp['success'])) {
    // можно вернуть дополнительные подсказки из $resp['error-codes']
    echo json_encode(['success'=>false,'message'=>'Проверка Turnstile не пройдена.']);
    exit;
}

// Сохранение в DB (status = pending)
try {
    $stmt = $pdo->prepare("INSERT INTO reviews (user_id, name, rating, review_text, status) VALUES (:user_id, :name, :rating, :review_text, 'pending')");
    $stmt->execute([
        ':user_id' => $user_id,
        ':name' => mb_substr($user_name,0,255),
        ':rating' => $rating,
        ':review_text' => $message
    ]);
    echo json_encode(['success'=>true,'message'=>'Отзыв отправлен и ожидает модерации.']);
    exit;
} catch (PDOException $e) {
    // логирование сервера рекомендуется
    error_log('Review insert error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Ошибка сервера. Попробуйте позже.']);
    exit;
}
