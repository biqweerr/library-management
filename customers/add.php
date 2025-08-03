<?php
require_once '../includes/functions.php';
requireLibrarian();

$pdo = getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $membership_type = sanitizeInput($_POST['membership_type']);
    $membership_expiry = sanitizeInput($_POST['membership_expiry']);
    $max_books_allowed = (int)$_POST['max_books_allowed'];
    
    // Generate customer code
    $customer_code = generateCustomerCode();
    
    // Validation
    if (empty($membership_type)) {
        $error = 'Please select a membership type.';
    } elseif ($max_books_allowed < 1) {
        $error = 'Maximum books allowed must be at least 1.';
    } else {
        try {
            // Insert new customer
            $stmt = $pdo->prepare("
                INSERT INTO customers (user_id, customer_code, membership_type, membership_expiry, max_books_allowed) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id, $customer_code, $membership_type, $membership_expiry, $max_books_allowed
            ]);
            
            setFlashMessage('success', 'Customer added successfully!');
            redirect('index.php');
        } catch (Exception $e) {
            $error = 'Failed to add customer. Please try again.';
        }
    }
}

// Get users not already associated with a customer
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email 
    FROM users u 
    LEFT JOIN customers c ON u.id = c.user_id 
    WHERE c.id IS NULL AND u.role = 'member'
    ORDER BY u.first_name, u.last_name
");
$stmt->execute();
$available_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer - Library Management System</title>
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
                            <a class="nav-link" href="../dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../books/">
                                <i class="fas fa-book me-2"></i>Books
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../books/browse.php">
                                <i class="fas fa-search me-2"></i>Browse Books
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../transactions/">
                                <i class="fas fa-exchange-alt me-2"></i>Transactions
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../passes/">
                                <i class="fas fa-id-card me-2"></i>Library Passes
                            </a>
                        </li>
                        
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../users/">
                                <i class="fas fa-user-cog me-2"></i>Users
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Add New Customer</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Customers
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Customer Information</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="user_id" class="form-label">Link to User Account</label>
                                        <select class="form-select" id="user_id" name="user_id">
                                            <option value="">No User Account</option>
                                            <?php foreach ($available_users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>" 
                                                        <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">Optional: Link this customer to an existing user account</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="membership_type" class="form-label">Membership Type *</label>
                                        <select class="form-select" id="membership_type" name="membership_type" required>
                                            <option value="">Select Membership Type</option>
                                            <option value="student" <?php echo (isset($_POST['membership_type']) && $_POST['membership_type'] === 'student') ? 'selected' : ''; ?>>Student</option>
                                            <option value="faculty" <?php echo (isset($_POST['membership_type']) && $_POST['membership_type'] === 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                                            <option value="public" <?php echo (isset($_POST['membership_type']) && $_POST['membership_type'] === 'public') ? 'selected' : ''; ?>>Public</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="membership_expiry" class="form-label">Membership Expiry Date</label>
                                        <input type="date" class="form-control" id="membership_expiry" name="membership_expiry" 
                                               value="<?php echo isset($_POST['membership_expiry']) ? htmlspecialchars($_POST['membership_expiry']) : date('Y-m-d', strtotime('+1 year')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_books_allowed" class="form-label">Maximum Books Allowed *</label>
                                        <input type="number" class="form-control" id="max_books_allowed" name="max_books_allowed" 
                                               value="<?php echo isset($_POST['max_books_allowed']) ? htmlspecialchars($_POST['max_books_allowed']) : '3'; ?>" 
                                               min="1" required>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Add Customer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>