<?php
/**
 * Common Functions for BZK Print Application
 * Centralized functions to eliminate code duplication and improve maintainability
 */

require_once __DIR__ . '/security.php';

// ==================== BREADCRUMBS & NAVIGATION ====================

/**
 * Generate breadcrumb navigation
 * @param string $current_page
 * @param array $custom_path
 * @return string
 */
function generateBreadcrumbs($current_page = '', $custom_path = []) {
    $breadcrumbs = [
        '' => 'Главная',
        'catalog' => 'Каталог',
        'service' => 'Товар',
        'cart' => 'Корзина',
        'checkout' => 'Оформление заказа',
        'about' => 'О компании',
        'contacts' => 'Контакты',
        'requirements' => 'Требования к макетам',
        'payment_delivery' => 'Доставка и оплата',
        'register' => 'Регистрация',
        'login' => 'Вход',
        'client/dashboard' => 'Личный кабинет',
        'client/orders' => 'Мои заказы',
        'client/settings' => 'Настройки',
        'admin' => 'Админ панель',
        'admin/orders' => 'Управление заказами',
        'admin/products' => 'Управление товарами',
        'admin/users' => 'Управление пользователями'
    ];
    
    if (!empty($custom_path)) {
        $breadcrumbs = array_merge($breadcrumbs, $custom_path);
    }
    
    $current_url = $_SERVER['REQUEST_URI'];
    $current_path = trim(parse_url($current_url, PHP_URL_PATH), '/');
    
    $html = '<nav class="flex" aria-label="Breadcrumb">';
    $html .= '<ol class="inline-flex items-center space-x-1 md:space-x-3">';
    
    // Home link
    $html .= '<li class="inline-flex items-center">';
    $html .= '<a href="/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-[#118568] transition-colors duration-200">';
    $html .= '<svg class="w-3 h-3 mr-2.5" fill="currentColor" viewBox="0 0 20 20">';
    $html .= '<path d="m19.707 9.293-2-2-7-7a1 1 0 0 0-1.414 0l-7 7-2 2a1 1 0 0 0 1.414 1.414L2 10.414V18a2 2 0 0 0 2 2h3a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h3a2 2 0 0 0 2-2v-7.586l.293.293a1 1 0 0 0 1.414-1.414Z"/>';
    $html .= '</svg>';
    $html .= 'Главная';
    $html .= '</a>';
    $html .= '</li>';
    
    // Build path segments
    $path_segments = explode('/', $current_path);
    $accumulated_path = '';
    
    foreach ($path_segments as $segment) {
        if (empty($segment)) continue;
        
        $accumulated_path .= ($accumulated_path ? '/' : '') . $segment;
        $page_name = $breadcrumbs[$accumulated_path] ?? ucfirst($segment);
        
        $html .= '<li>';
        $html .= '<div class="flex items-center">';
        $html .= '<svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"></path>';
        $html .= '</svg>';
        
        if ($accumulated_path === $current_path) {
            $html .= '<span class="text-sm font-medium text-gray-500">' . e($page_name) . '</span>';
        } else {
            $html .= '<a href="/' . e($accumulated_path) . '" class="text-sm font-medium text-gray-700 hover:text-[#118568] transition-colors duration-200">' . e($page_name) . '</a>';
        }
        
        $html .= '</div>';
        $html .= '</li>';
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Generate back button
 * @param string $custom_url
 * @param string $text
 * @return string
 */
function backButton($custom_url = null, $text = 'Назад') {
    $back_url = $custom_url ?: 'javascript:history.back()';
    
    $html = '<a href="' . e($back_url) . '" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 hover:text-gray-900 font-medium rounded-lg transition-all duration-200 group">';
    $html .= '<svg class="w-4 h-4 mr-2 transform group-hover:-translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>';
    $html .= '</svg>';
    $html .= e($text);
    $html .= '</a>';
    
    return $html;
}

// ==================== NOTIFICATIONS ====================

/**
 * Add notification to session
 * @param string $type - success, error, warning, info
 * @param string $message
 */
function add_notification($type, $message) {
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    
    $_SESSION['notifications'][] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Get and clear notifications from session
 * @return array
 */
function get_notifications() {
    $notifications = $_SESSION['notifications'] ?? [];
    unset($_SESSION['notifications']);
    return $notifications;
}

/**
 * Display notifications HTML
 * @return string
 */
function display_notifications() {
    $notifications = get_notifications();
    if (empty($notifications)) {
        return '';
    }
    
    $html = '';
    foreach ($notifications as $notification) {
        $type = $notification['type'];
        $message = $notification['message'];
        
        $bg_color = [
            'success' => 'bg-green-100 border-green-400 text-green-700',
            'error' => 'bg-red-100 border-red-400 text-red-700',
            'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700',
            'info' => 'bg-blue-100 border-blue-400 text-blue-700'
        ][$type] ?? 'bg-gray-100 border-gray-400 text-gray-700';
        
        $icon = [
            'success' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
            'error' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
            'warning' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>',
            'info' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
        ][$type] ?? '';
        
        $html .= '<div class="mb-4 p-4 border rounded-lg ' . $bg_color . ' notification-alert" role="alert">';
        $html .= '<div class="flex items-center">';
        $html .= '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . $icon . '</svg>';
        $html .= '<div class="flex-1">' . e($message) . '</div>';
        $html .= '<button type="button" class="ml-2 text-current hover:text-opacity-75 transition-colors duration-200" onclick="this.parentElement.parentElement.remove()">';
        $html .= '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
        $html .= '</svg>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    return $html;
}

// ==================== FORM HELPERS ====================

/**
 * Generate form input with validation
 * @param string $type
 * @param string $name
 * @param mixed $value
 * @param array $attributes
 * @param array $validation_rules
 * @return string
 */
function form_input($type, $name, $value = '', $attributes = [], $validation_rules = []) {
    $html = '<div class="mb-4">';
    
    // Label
    if (isset($attributes['label'])) {
        $required = in_array('required', $validation_rules) ? ' *' : '';
        $html .= '<label for="' . e($name) . '" class="block text-gray-700 font-medium mb-2">';
        $html .= e($attributes['label']) . $required;
        $html .= '</label>';
        unset($attributes['label']);
    }
    
    // Input wrapper
    $html .= '<div class="relative">';
    
    // Icon
    if (isset($attributes['icon'])) {
        $html .= '<div class="absolute left-3 top-3 text-gray-400">' . $attributes['icon'] . '</div>';
        $attributes['class'] = ($attributes['class'] ?? '') . ' pl-10';
        unset($attributes['icon']);
    }
    
    // Default classes
    $default_classes = 'w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-[#118568] focus:ring-2 focus:ring-[#9DC5BB] transition-all duration-300';
    $attributes['class'] = ($attributes['class'] ?? '') . ' ' . $default_classes;
    
    // Build attributes string
    $attr_string = '';
    foreach ($attributes as $key => $val) {
        if ($key === 'label') continue;
        $attr_string .= ' ' . e($key) . '="' . e($val) . '"';
    }
    
    // Add validation attributes
    foreach ($validation_rules as $rule) {
        if ($rule === 'required') {
            $attr_string .= ' required';
        } elseif (strpos($rule, 'min:') === 0) {
            $min = substr($rule, 4);
            if ($type === 'password' || $type === 'text') {
                $attr_string .= ' minlength="' . e($min) . '"';
            }
        } elseif (strpos($rule, 'max:') === 0) {
            $max = substr($rule, 4);
            if ($type === 'password' || $type === 'text') {
                $attr_string .= ' maxlength="' . e($max) . '"';
            }
        }
    }
    
    // Generate input
    if ($type === 'textarea') {
        $html .= '<textarea name="' . e($name) . '" id="' . e($name) . '"' . $attr_string . '>' . e($value) . '</textarea>';
    } else {
        $html .= '<input type="' . e($type) . '" name="' . e($name) . '" id="' . e($name) . '" value="' . e($value) . '"' . $attr_string . '>';
    }
    
    $html .= '</div>';
    
    // Help text
    if (isset($attributes['help'])) {
        $html .= '<p class="text-xs text-gray-500 mt-1">' . e($attributes['help']) . '</p>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// ==================== ORDER HELPERS ====================

/**
 * Render order card component
 * @param array $order
 * @param array $options
 * @return string
 */
function renderOrderCard($order, $options = []) {
    $defaults = [
        'show_actions' => true,
        'show_details' => true,
        'admin_view' => false
    ];
    $config = array_merge($defaults, $options);
    
    $status_colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'processing' => 'bg-blue-100 text-blue-800', 
        'shipped' => 'bg-purple-100 text-purple-800',
        'delivered' => 'bg-indigo-100 text-indigo-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'completed' => 'bg-green-100 text-green-800'
    ];
    
    $status_names = [
        'pending' => 'В ожидании',
        'processing' => 'В обработке',
        'shipped' => 'Отправлен',
        'delivered' => 'Доставлен',
        'cancelled' => 'Отменен',
        'completed' => 'Выполнен'
    ];
    
    $status_class = $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800';
    $status_name = $status_names[$order['status']] ?? 'Неизвестно';
    
    $html = '<div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100">';
    
    // Header
    $html .= '<div class="p-6 border-b border-gray-100">';
    $html .= '<div class="flex items-center justify-between">';
    $html .= '<div>';
    $html .= '<h3 class="text-lg font-bold text-gray-800">Заказ #' . e($order['id']) . '</h3>';
    $html .= '<p class="text-sm text-gray-500">' . date('d.m.Y H:i', strtotime($order['created_at'])) . '</p>';
    $html .= '</div>';
    $html .= '<span class="px-3 py-1 rounded-full text-xs font-medium ' . $status_class . '">' . e($status_name) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Content
    $html .= '<div class="p-6">';
    
    if ($config['show_details']) {
        // Order details
        if (isset($order['total_price'])) {
            $html .= '<div class="mb-4">';
            $html .= '<div class="text-2xl font-bold text-[#118568]">' . number_format($order['total_price'], 0, '', ' ') . ' ₽</div>';
            $html .= '</div>';
        }
        
        if (isset($order['items_count'])) {
            $html .= '<div class="text-sm text-gray-600 mb-4">';
            $html .= 'Позиций в заказе: ' . e($order['items_count']);
            $html .= '</div>';
        }
    }
    
    if ($config['show_actions']) {
        $html .= '<div class="flex gap-2">';
        
        if ($config['admin_view']) {
            $html .= '<a href="/admin/order/details?id=' . e($order['id']) . '" class="flex-1 bg-[#118568] text-white py-2 px-4 rounded-lg hover:bg-[#0f755a] transition-colors duration-200 text-center text-sm font-medium">';
            $html .= 'Управление';
            $html .= '</a>';
        } else {
            $html .= '<a href="/client/order_details?id=' . e($order['id']) . '" class="flex-1 bg-[#118568] text-white py-2 px-4 rounded-lg hover:bg-[#0f755a] transition-colors duration-200 text-center text-sm font-medium">';
            $html .= 'Подробнее';
            $html .= '</a>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// ==================== TABLE RESPONSIVE HELPERS ====================

/**
 * Generate responsive table with mobile card view toggle
 * @param array $columns
 * @param array $data
 * @param array $options
 * @return string
 */
function responsive_table($columns, $data, $options = []) {
    $defaults = [
        'table_classes' => 'w-full bg-white rounded-2xl shadow-lg overflow-hidden',
        'card_classes' => 'grid grid-cols-1 gap-4',
        'show_toggle' => true,
        'default_view' => 'table' // 'table' or 'cards'
    ];
    $config = array_merge($defaults, $options);
    
    $table_id = 'table_' . uniqid();
    
    $html = '<div class="responsive-table-container">';
    
    // Toggle buttons
    if ($config['show_toggle']) {
        $html .= '<div class="mb-4 flex justify-end">';
        $html .= '<div class="inline-flex rounded-lg border border-gray-200 bg-white p-1">';
        $html .= '<button type="button" onclick="toggleView(\'' . $table_id . '\', \'table\')" ';
        $html .= 'class="view-toggle px-3 py-1 rounded-md text-sm font-medium transition-colors duration-200 ' . ($config['default_view'] === 'table' ? 'bg-[#118568] text-white' : 'text-gray-500 hover:text-gray-700') . '">';
        $html .= '<svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18m-9 8h9"></path></svg>';
        $html .= 'Таблица';
        $html .= '</button>';
        $html .= '<button type="button" onclick="toggleView(\'' . $table_id . '\', \'cards\')" ';
        $html .= 'class="view-toggle px-3 py-1 rounded-md text-sm font-medium transition-colors duration-200 ' . ($config['default_view'] === 'cards' ? 'bg-[#118568] text-white' : 'text-gray-500 hover:text-gray-700') . '">';
        $html .= '<svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14-7H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2z"></path></svg>';
        $html .= 'Карточки';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    // Container
    $html .= '<div id="' . $table_id . '" class="table-responsive-container">';
    
    // Table view
    $html .= '<div class="table-view ' . ($config['default_view'] === 'cards' ? 'hidden' : '') . '">';
    $html .= '<div class="overflow-x-auto">';
    $html .= '<table class="' . $config['table_classes'] . '">';
    
    // Table header
    $html .= '<thead class="bg-gradient-to-r from-[#118568] to-[#0f755a] text-white">';
    $html .= '<tr>';
    foreach ($columns as $key => $column) {
        $html .= '<th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">' . e($column['title']) . '</th>';
    }
    $html .= '</tr>';
    $html .= '</thead>';
    
    // Table body
    $html .= '<tbody class="divide-y divide-gray-200">';
    foreach ($data as $row) {
        $html .= '<tr class="hover:bg-gray-50 transition-colors duration-200">';
        foreach ($columns as $key => $column) {
            $value = $row[$key] ?? '';
            if (isset($column['render'])) {
                $value = $column['render']($value, $row);
            } else {
                $value = e($value);
            }
            $html .= '<td class="px-6 py-4 text-sm text-gray-900">' . $value . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Cards view
    $html .= '<div class="cards-view ' . ($config['default_view'] === 'table' ? 'hidden' : '') . '">';
    $html .= '<div class="' . $config['card_classes'] . '">';
    foreach ($data as $row) {
        $html .= '<div class="bg-white rounded-lg shadow p-6 border border-gray-200">';
        foreach ($columns as $key => $column) {
            $value = $row[$key] ?? '';
            if (isset($column['render'])) {
                $value = $column['render']($value, $row);
            } else {
                $value = e($value);
            }
            $html .= '<div class="mb-2">';
            $html .= '<span class="text-sm font-medium text-gray-600">' . e($column['title']) . ':</span> ';
            $html .= '<span class="text-sm text-gray-900">' . $value . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    // JavaScript for toggle functionality
    $html .= '<script>';
    $html .= 'function toggleView(tableId, view) {';
    $html .= '  const container = document.getElementById(tableId);';
    $html .= '  const tableView = container.querySelector(".table-view");';
    $html .= '  const cardsView = container.querySelector(".cards-view");';
    $html .= '  const toggles = container.parentElement.querySelectorAll(".view-toggle");';
    $html .= '  ';
    $html .= '  if (view === "table") {';
    $html .= '    tableView.classList.remove("hidden");';
    $html .= '    cardsView.classList.add("hidden");';
    $html .= '  } else {';
    $html .= '    tableView.classList.add("hidden");';
    $html .= '    cardsView.classList.remove("hidden");';
    $html .= '  }';
    $html .= '  ';
    $html .= '  toggles.forEach(toggle => {';
    $html .= '    toggle.classList.remove("bg-[#118568]", "text-white");';
    $html .= '    toggle.classList.add("text-gray-500", "hover:text-gray-700");';
    $html .= '  });';
    $html .= '  ';
    $html .= '  const activeToggle = container.parentElement.querySelector(`[onclick*="${view}"]`);';
    $html .= '  activeToggle.classList.remove("text-gray-500", "hover:text-gray-700");';
    $html .= '  activeToggle.classList.add("bg-[#118568]", "text-white");';
    $html .= '}';
    $html .= '</script>';
    
    return $html;
}

// ==================== VALIDATION HELPERS ====================

/**
 * Validate form data against rules
 * @param array $data
 * @param array $rules
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_form($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $field_rules) {
        $value = $data[$field] ?? null;
        $field_errors = [];
        
        foreach ($field_rules as $rule) {
            if ($rule === 'required' && (empty($value) && $value !== '0')) {
                $field_errors[] = "Поле {$field} обязательно для заполнения";
            } elseif (strpos($rule, 'min:') === 0) {
                $min = (int)substr($rule, 4);
                if (strlen($value) < $min) {
                    $field_errors[] = "Поле {$field} должно содержать минимум {$min} символов";
                }
            } elseif (strpos($rule, 'max:') === 0) {
                $max = (int)substr($rule, 4);
                if (strlen($value) > $max) {
                    $field_errors[] = "Поле {$field} должно содержать максимум {$max} символов";
                }
            } elseif ($rule === 'email' && !empty($value)) {
                if (!validate_email($value)) {
                    $field_errors[] = "Поле {$field} должно содержать корректный email адрес";
                }
            } elseif ($rule === 'phone' && !empty($value)) {
                if (!validate_phone($value)) {
                    $field_errors[] = "Поле {$field} должно содержать корректный номер телефона";
                }
            }
        }
        
        if (!empty($field_errors)) {
            $errors[$field] = $field_errors;
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}