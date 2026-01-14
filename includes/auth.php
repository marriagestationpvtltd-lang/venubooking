<?php
/**
 * Authentication Functions
 * Handles user authentication and session management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Login user
 */
function loginUser($username, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND status = 'active'");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['admin_id'] = $user['id']; // For admin pages
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        // Update last login
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $updateStmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Log activity
        logActivity($user['id'], 'login', 'users', $user['id']);
        
        return ['success' => true, 'user' => $user];
    }
    
    return ['success' => false, 'message' => 'Invalid username or password'];
}

/**
 * Logout user
 */
function logoutUser() {
    $user_id = $_SESSION['user_id'] ?? null;
    if (isLoggedIn() && $user_id) {
        logActivity($user_id, 'logout');
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Start new session
    session_start();
    
    return true;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is manager
 */
function isManager() {
    return isLoggedIn() && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager');
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please login to continue');
        redirect('/admin/login.php');
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlashMessage('error', 'Access denied. Admin privileges required.');
        redirect('/admin/dashboard.php');
    }
}

/**
 * Require manager role
 */
function requireManager() {
    requireLogin();
    if (!isManager()) {
        setFlashMessage('error', 'Access denied. Manager privileges required.');
        redirect('/admin/dashboard.php');
    }
}

/**
 * Create new user
 */
function createUser($username, $password, $full_name, $email, $role = 'staff') {
    $db = getDB();
    
    // Check if username exists
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return ['success' => false, 'message' => 'Username already exists'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    $sql = "INSERT INTO users (username, password, full_name, email, role, status) 
            VALUES (:username, :password, :full_name, :email, :role, 'active')";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    
    if ($stmt->execute()) {
        $userId = $db->lastInsertId();
        $currentUserId = $_SESSION['user_id'] ?? null;
        if ($currentUserId) {
            logActivity($currentUserId, 'create', 'users', $userId, null, ['username' => $username]);
        }
        return ['success' => true, 'user_id' => $userId];
    }
    
    return ['success' => false, 'message' => 'Failed to create user'];
}

/**
 * Update user password
 */
function updateUserPassword($user_id, $new_password) {
    $db = getDB();
    
    $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        logActivity($user_id, 'update_password', 'users', $user_id);
        return ['success' => true];
    }
    
    return ['success' => false, 'message' => 'Failed to update password'];
}

/**
 * Verify current password
 */
function verifyCurrentPassword($user_id, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        return true;
    }
    
    return false;
}
