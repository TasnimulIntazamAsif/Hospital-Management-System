<?php
// includes/auth-check.php - Check if user is authenticated
session_start();

function requireAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function requireRole($allowedRoles) {
    requireAuth();
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowedRoles)) {
        header('Location: index.php');
        exit;
    }
}

function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? ''
    ];
}
?>

