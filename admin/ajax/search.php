<?php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/security.php';

// CSRF protection
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit(json_encode(['error' => 'CSRF token mismatch']));
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = trim($input['query'] ?? '');
    $status = $input['status'] ?? '';
    $date = $input['date'] ?? '';
    
    // Build search query
    $sql = "SELECT o.*, u.name as user_name, u.email as user_email 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE 1=1";
    $params = [];
    
    if (!empty($query)) {
        $sql .= " AND (o.id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR o.description LIKE ?)";
        $searchTerm = "%{$query}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($status) && $status !== 'all') {
        $sql .= " AND o.status = ?";
        $params[] = $status;
    }
    
    if (!empty($date)) {
        $sql .= " AND DATE(o.created_at) = ?";
        $params[] = $date;
    }
    
    $sql .= " ORDER BY o.created_at DESC LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for display
    $results = [];
    foreach ($orders as $order) {
        $statusBadge = getStatusBadge($order['status']);
        $results[] = [
            'id' => $order['id'],
            'user_name' => e($order['user_name'] ?? 'Гость'),
            'email' => e($order['user_email'] ?? 'Не указан'),
            'total' => number_format($order['total'], 2),
            'status' => $order['status'],
            'status_badge' => $statusBadge,
            'created_at' => date('d.m.Y H:i', strtotime($order['created_at'])),
            'description' => e(mb_substr($order['description'] ?? '', 0, 100))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getStatusBadge($status) {
    $badges = [
        'new' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Новый</span>',
        'in_progress' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">В работе</span>',
        'ready' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Готов</span>',
        'delivered' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Выдан</span>',
        'cancelled' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Отменен</span>'
    ];
    
    return $badges[$status] ?? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Неизвестно</span>';
}
?>