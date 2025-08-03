<?php
session_start();
require_once(__DIR__ . '/../db/connection.php');


// Utility functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateCustomerCode() {
    return 'CUST' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generatePassNumber() {
    return 'PASS' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function calculateDueDate($issueDate, $days = 14) {
    return date('Y-m-d', strtotime($issueDate . ' + ' . $days . ' days'));
}

function calculateFine($dueDate, $returnDate = null) {
    if (!$returnDate) {
        $returnDate = date('Y-m-d');
    }
    
    $due = new DateTime($dueDate);
    $return = new DateTime($returnDate);
    $diff = $due->diff($return);
    
    if ($return > $due) {
        return $diff->days * 0.50; // $0.50 per day
    }
    return 0;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isLibrarian() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'librarian' || $_SESSION['role'] === 'admin');
}

function isMember() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'member';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../dashboard.php?error=access_denied');
        exit();
    }
}

function requireLibrarian() {
    requireLogin();
    if (!isLibrarian()) {
        header('Location: ../dashboard.php?error=access_denied');
        exit();
    }
}

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getBookStatus($available, $total) {
    if ($available > 0) {
        return '<span class="badge bg-success">Available</span>';
    } else {
        return '<span class="badge bg-danger">Not Available</span>';
    }
}

function getIssueStatus($status) {
    switch ($status) {
        case 'issued':
            return '<span class="badge bg-primary">Issued</span>';
        case 'returned':
            return '<span class="badge bg-success">Returned</span>';
        case 'overdue':
            return '<span class="badge bg-danger">Overdue</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

function getReservationStatus($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">Pending</span>';
        case 'fulfilled':
            return '<span class="badge bg-success">Fulfilled</span>';
        case 'expired':
            return '<span class="badge bg-danger">Expired</span>';
        case 'cancelled':
            return '<span class="badge bg-secondary">Cancelled</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

function getPassStatus($status) {
    switch ($status) {
        case 'active':
            return '<span class="badge bg-success">Active</span>';
        case 'expired':
            return '<span class="badge bg-danger">Expired</span>';
        case 'suspended':
            return '<span class="badge bg-warning">Suspended</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}
?> 