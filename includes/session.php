<?php
// includes/session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/db.php';

        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $role = $stmt->fetchColumn();

        if ($role) {
            $_SESSION['role'] = $role;
        }
    } catch (Exception $e) {
        error_log("Ошибка синхронизации роли: " . $e->getMessage());
    }
}
