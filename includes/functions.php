<?php
// includes/functions.php

/* ==========================================
   Security & Input Handling
   ========================================== */

/**
 * Sanitize user input to prevent XSS and injection
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/* ==========================================
   Authentication & Authorization
   ========================================== */

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function require_role($required_role) {
    require_login();
    if ($_SESSION['role'] !== $required_role) {
        // Log unauthorized access attempt
        log_activity($_SESSION['user_id'], 'UNAUTHORIZED_ACCESS', "Tried to access $required_role area");
        redirect('index.php?error=unauthorized');
    }
}

function get_user_role() {
    return $_SESSION['role'] ?? null;
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_name() {
    return $_SESSION['full_name'] ?? 'Guest';
}

/* ==========================================
   Utilities
   ========================================== */

/**
 * Redirect helper
 * Handles both absolute URLs and relative paths
 */
function redirect($path) {
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        header("Location: " . $path);
    } else {
        // Ensure SITE_URL is defined, otherwise fallback to relative
        $base = defined('SITE_URL') ? SITE_URL : '';
        // Remove leading slash to avoid double slashes if SITE_URL has trailing slash
        $path = ltrim($path, '/');
        header("Location: " . $base . '/' . $path);
    }
    exit();
}

function format_date($date) {
    if (!$date) return 'N/A';
    return date('F j, Y', strtotime($date));
}

function format_datetime($datetime) {
    if (!$datetime) return 'N/A';
    return date('F j, Y g:i A', strtotime($datetime));
}

/* ==========================================
   Database Helpers
   ========================================== */

/**
 * Log user activity to database
 */
function log_activity($user_id, $action, $description) {
    try {
        $db = Database::getInstance()->getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip]);
    } catch (PDOException $e) {
        // Silently fail logging to not disrupt user flow
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

function get_branches() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name");
    return $stmt->fetchAll();
}

function get_categories() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM categories ORDER BY category_name");
    return $stmt->fetchAll();
}

/* ==========================================
   File Upload Helper
   ========================================== */

/**
 * Handle File Upload
 * @param array $file The $_FILES['name'] array
 * @param string $destination_folder Subfolder in 'uploads/'
 * @param array $allowed_types Array of allowed extensions
 * @return array ['success' => bool, 'message' => string, 'path' => string]
 */
function upload_file($file, $destination_folder = 'materials', $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'png']) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error code: ' . $file['error']];
    }

    // Validate size (e.g., max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit.'];
    }

    // Validate extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }

    // Generate unique name
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    
    // Define target path (adjusting for directory depth)
    // Assuming this function is called from a file 1 level deep (e.g., admin/ or student/)
    // We need to locate the absolute path to uploads
    $target_dir = __DIR__ . '/../uploads/' . $destination_folder . '/';
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Return the relative path for database storage
        return [
            'success' => true, 
            'message' => 'File uploaded successfully', 
            'path' => 'uploads/' . $destination_folder . '/' . $new_filename
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file. Check folder permissions.'];
    }
}
?>