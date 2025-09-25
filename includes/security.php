<?php
/**
 * Security Functions for BZK Print Application
 * Comprehensive security utilities for XSS, CSRF, file uploads, and input validation
 */

// ==================== XSS PROTECTION ====================

/**
 * Universal XSS protection function
 * @param mixed $data - String or array to escape
 * @param string $encoding - Character encoding
 * @return mixed - Escaped data
 */
function e($data, $encoding = 'UTF-8') {
    if (is_array($data)) {
        return array_map(function($item) use ($encoding) {
            return e($item, $encoding);
        }, $data);
    }
    
    if (is_object($data)) {
        return $data;
    }
    
    return htmlspecialchars($data ?? '', ENT_QUOTES | ENT_HTML5, $encoding);
}

/**
 * Output safe HTML with nl2br conversion
 * @param string $text
 * @return string
 */
function safe_nl2br($text) {
    return nl2br(e($text));
}

/**
 * Safe JSON encode for HTML output
 * @param mixed $data
 * @return string
 */
function safe_json($data) {
    return htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

// ==================== CSRF PROTECTION ====================

/**
 * Generate CSRF token for current session
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token
 * @return bool
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF hidden input field
 * @return string
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify CSRF token from POST data and exit with error if invalid
 */
function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($token)) {
            http_response_code(419);
            die('CSRF token mismatch. Please refresh the page and try again.');
        }
    }
}

// ==================== FILE UPLOAD SECURITY ====================

/**
 * Secure file upload handler
 * @param array $file - $_FILES array element
 * @param array $options - Configuration options
 * @return array - ['success' => bool, 'filename' => string, 'error' => string]
 */
function secure_file_upload($file, $options = []) {
    $defaults = [
        'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'max_size' => 5 * 1024 * 1024, // 5MB
        'upload_dir' => __DIR__ . '/../storage/uploads/',
        'public_dir' => '/storage/uploads/',
        'filename_prefix' => '',
        'create_thumbnails' => false
    ];
    
    $config = array_merge($defaults, $options);
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
    }
    
    // Check file size
    if ($file['size'] > $config['max_size']) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed size'];
    }
    
    // Verify MIME type
    $actual_mime = mime_content_type($file['tmp_name']);
    if (!in_array($actual_mime, $config['allowed_types'])) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Verify file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $config['allowed_extensions'])) {
        return ['success' => false, 'error' => 'Invalid file extension'];
    }
    
    // Create upload directory if it doesn't exist
    if (!is_dir($config['upload_dir'])) {
        if (!mkdir($config['upload_dir'], 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory'];
        }
    }
    
    // Generate secure filename
    $safe_filename = $config['filename_prefix'] . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $full_path = $config['upload_dir'] . $safe_filename;
    $public_url = $config['public_dir'] . $safe_filename;
    
    // Additional security: Re-encode images to strip potential malicious code
    if (in_array($actual_mime, ['image/jpeg', 'image/png', 'image/gif'])) {
        $result = sanitize_image($file['tmp_name'], $full_path, $actual_mime);
        if (!$result) {
            return ['success' => false, 'error' => 'Failed to process image'];
        }
    } else {
        // Move file
        if (!move_uploaded_file($file['tmp_name'], $full_path)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }
    }
    
    return [
        'success' => true,
        'filename' => $safe_filename,
        'full_path' => $full_path,
        'public_url' => $public_url,
        'size' => $file['size'],
        'mime_type' => $actual_mime
    ];
}

/**
 * Sanitize uploaded image by re-encoding
 * @param string $source_path
 * @param string $dest_path
 * @param string $mime_type
 * @return bool
 */
function sanitize_image($source_path, $dest_path, $mime_type) {
    switch ($mime_type) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source_path);
            if ($image && imagejpeg($image, $dest_path, 90)) {
                imagedestroy($image);
                return true;
            }
            break;
            
        case 'image/png':
            $image = imagecreatefrompng($source_path);
            if ($image && imagepng($image, $dest_path, 9)) {
                imagedestroy($image);
                return true;
            }
            break;
            
        case 'image/gif':
            $image = imagecreatefromgif($source_path);
            if ($image && imagegif($image, $dest_path)) {
                imagedestroy($image);
                return true;
            }
            break;
    }
    return false;
}

// ==================== INPUT VALIDATION ====================

/**
 * Validate email address
 * @param string $email
 * @return bool
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Russian format)
 * @param string $phone
 * @return bool
 */
function validate_phone($phone) {
    $phone = preg_replace('/[^\d+]/', '', $phone);
    return preg_match('/^\+7\d{10}$/', $phone);
}

/**
 * Validate password strength
 * @param string $password
 * @param int $min_length
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_password($password, $min_length = 8) {
    $errors = [];
    
    if (strlen($password) < $min_length) {
        $errors[] = "Пароль должен содержать минимум {$min_length} символов";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Пароль должен содержать хотя бы одну строчную букву";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Пароль должен содержать хотя бы одну заглавную букву";
    }
    
    if (!preg_match('/\d/', $password)) {
        $errors[] = "Пароль должен содержать хотя бы одну цифру";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Sanitize and validate text input
 * @param string $input
 * @param int $max_length
 * @param bool $allow_html
 * @return string
 */
function sanitize_text($input, $max_length = null, $allow_html = false) {
    $input = trim($input);
    
    if (!$allow_html) {
        $input = strip_tags($input);
    }
    
    if ($max_length && strlen($input) > $max_length) {
        $input = substr($input, 0, $max_length);
    }
    
    return $input;
}

// ==================== RATE LIMITING ====================

/**
 * Simple rate limiting implementation
 * @param string $identifier - IP or user ID
 * @param string $action - Action name (login, register, etc.)
 * @param int $max_attempts
 * @param int $time_window - Time window in seconds
 * @return array ['allowed' => bool, 'attempts' => int, 'reset_time' => int]
 */
function check_rate_limit($identifier, $action, $max_attempts = 5, $time_window = 300) {
    $cache_key = "rate_limit_{$action}_{$identifier}";
    $cache_file = sys_get_temp_dir() . '/' . md5($cache_key) . '.tmp';
    
    $current_time = time();
    $attempts = [];
    
    // Load existing attempts
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data && is_array($data)) {
            $attempts = $data;
        }
    }
    
    // Remove old attempts outside time window
    $attempts = array_filter($attempts, function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });
    
    $attempt_count = count($attempts);
    $allowed = $attempt_count < $max_attempts;
    
    if (!$allowed) {
        $oldest_attempt = min($attempts);
        $reset_time = $oldest_attempt + $time_window;
    } else {
        $reset_time = null;
    }
    
    return [
        'allowed' => $allowed,
        'attempts' => $attempt_count,
        'reset_time' => $reset_time
    ];
}

/**
 * Record rate limit attempt
 * @param string $identifier
 * @param string $action
 */
function record_rate_limit_attempt($identifier, $action) {
    $cache_key = "rate_limit_{$action}_{$identifier}";
    $cache_file = sys_get_temp_dir() . '/' . md5($cache_key) . '.tmp';
    
    $attempts = [];
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data && is_array($data)) {
            $attempts = $data;
        }
    }
    
    $attempts[] = time();
    file_put_contents($cache_file, json_encode($attempts));
}

// ==================== SESSION SECURITY ====================

/**
 * Secure session initialization
 */
function init_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration'])) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Check if user is authenticated and session is valid
 * @return bool
 */
function is_authenticated() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['is_authenticated']) && 
           $_SESSION['is_authenticated'] === true;
}

/**
 * Require authentication or redirect to login
 * @param string $redirect_url
 */
function require_auth($redirect_url = '/login') {
    if (!is_authenticated()) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Check if user has required role
 * @param array|string $required_roles
 * @return bool
 */
function has_role($required_roles) {
    if (!is_authenticated()) {
        return false;
    }
    
    $user_role = $_SESSION['role'] ?? 'user';
    
    if (is_string($required_roles)) {
        return $user_role === $required_roles;
    }
    
    if (is_array($required_roles)) {
        return in_array($user_role, $required_roles);
    }
    
    return false;
}

/**
 * Require specific role or redirect
 * @param array|string $required_roles
 * @param string $redirect_url
 */
function require_role($required_roles, $redirect_url = '/login') {
    if (!has_role($required_roles)) {
        header("Location: $redirect_url");
        exit();
    }
}