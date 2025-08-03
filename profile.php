<?php
require_once 'includes/functions.php';
requireLogin();

$pdo = getConnection();
$error = '';
$success = '';

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get customer information if exists
$customer = null;
if (isMember()) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $customer = $stmt->fetch();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email already exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = 'Email already exists.';
            } else {
                // Update user information
                $stmt = $pdo->prepare("
                    UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $address, $_SESSION['user_id']]);
                
                // Update session
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                
                $success = 'Profile updated successfully!';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $success = 'Password changed successfully!';
        } catch (Exception $e) {
            $error = 'Failed to change password. Please try again.';
        }
    }
}

// Get user's recent transactions
$recent_transactions = [];
if (isMember() && $customer) {
    $stmt = $pdo->prepare("
        SELECT bi.*, b.title, b.isbn
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        WHERE bi.customer_id = ?
        ORDER BY bi.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$customer['id']]);
    $recent_transactions = $stmt->fetchAll();
}

// Get user's active reservations
$active_reservations = [];
if (isMember() && $customer) {
    $stmt = $pdo->prepare("
        SELECT r.*, b.title, b.isbn
        FROM reservations r
        JOIN books b ON r.book_id = b.id
        WHERE r.customer_id = ? AND r.status = 'pending'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$customer['id']]);
    $active_reservations = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white"><i class="fas fa-book-open me-2"></i>Library System</h4>
                        <p class="text-white-50">Welcome, <?php echo $_SESSION['first_name']; ?>!</p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        
                        <?php if (isAdmin() || isLibrarian()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="books/">
                                <i class="fas fa-book me-2"></i>Books
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers/">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="books/browse.php">
                                <i class="fas fa-search me-2"></i>Browse Books
                            </a>
                        </li>
                        
                        <?php if (isAdmin() || isLibrarian()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions/">
                                <i class="fas fa-exchange-alt me-2"></i>Transactions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="passes/">
                                <i class="fas fa-id-card me-2"></i>Library Passes
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="users/">
                                <i class="fas fa-user-cog me-2"></i>Users
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Profile</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-primary"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Information -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-user me-2"></i>Profile Information
                                </h6>
                            </div>
                            <div class="card-body text-center">
                                <div class="profile-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                                
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="first_name" class="form-label">First Name *</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="last_name" class="form-label">Last Name *</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email *</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="phone" class="form-label">Phone</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?: ''); ?></textarea>
                                    </div>

                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-lock me-2"></i>Change Password
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password *</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password *</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Account Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                <p><strong>Role:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $_SESSION['role'] === 'admin' ? 'danger' : 
                                            ($_SESSION['role'] === 'librarian' ? 'primary' : 'success'); 
                                    ?>">
                                        <?php echo ucfirst($_SESSION['role']); ?>
                                    </span>
                                </p>
                                <p><strong>Member Since:</strong> <?php echo formatDate($user['created_at']); ?></p>
                                <p><strong>Last Updated:</strong> <?php echo formatDate($user['updated_at']); ?></p>
                            </div>
                        </div>

                        <!-- Customer Information (for members) -->
                        <?php if (isMember() && $customer): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-id-card me-2"></i>Library Membership</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Customer Code:</strong> <?php echo htmlspecialchars($customer['customer_code']); ?></p>
                                <p><strong>Membership Type:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $customer['membership_type'] === 'faculty' ? 'primary' : 
                                            ($customer['membership_type'] === 'student' ? 'success' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($customer['membership_type']); ?>
                                    </span>
                                </p>
                                <?php if ($customer['membership_expiry']): ?>
                                    <p><strong>Expires:</strong> <?php echo formatDate($customer['membership_expiry']); ?></p>
                                <?php endif; ?>
                                <p><strong>Max Books:</strong> <?php echo $customer['max_books_allowed']; ?></p>
                                <?php if ($customer['fine_balance'] > 0): ?>
                                    <p><strong>Fine Balance:</strong> <span class="text-danger"><?php echo formatCurrency($customer['fine_balance']); ?></span></p>
                                <?php else: ?>
                                    <p><strong>Fine Balance:</strong> <span class="text-success">$0.00</span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Recent Activity -->
                        <?php if (isMember() && !empty($recent_transactions)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($recent_transactions as $transaction): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <small class="text-muted"><?php echo htmlspecialchars($transaction['title']); ?></small><br>
                                            <small><?php echo formatDate($transaction['issue_date']); ?></small>
                                        </div>
                                        <div>
                                            <?php echo getIssueStatus($transaction['status']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 