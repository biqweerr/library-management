<?php
require_once '../includes/functions.php';
requireLibrarian();

$pdo = getConnection();

$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

$error = '';
$success = '';

// Get available books for dropdown
$books = $pdo->query("
    SELECT id, title, author, isbn, available_copies 
    FROM books 
    WHERE available_copies > 0 
    ORDER BY title
")->fetchAll();

// Get customers for dropdown
$customers = $pdo->query("
    SELECT c.id, c.customer_code, c.membership_type, c.membership_expiry, c.max_books_allowed,
           u.first_name, u.last_name, u.email,
           (SELECT COUNT(*) FROM book_issues bi WHERE bi.customer_id = c.id AND bi.status = 'issued') as active_issues
    FROM customers c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE (c.membership_expiry IS NULL OR c.membership_expiry >= CURDATE())
    ORDER BY c.customer_code
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = (int)$_POST['book_id'];
    $customer_id = (int)$_POST['customer_id'];
    $issue_date = sanitizeInput($_POST['issue_date']);
    $due_date = sanitizeInput($_POST['due_date']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Validation
    if (empty($book_id) || empty($customer_id) || empty($issue_date) || empty($due_date)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Check if book is available
            $stmt = $pdo->prepare("SELECT available_copies FROM books WHERE id = ?");
            $stmt->execute([$book_id]);
            $book = $stmt->fetch();
            
            if (!$book || $book['available_copies'] <= 0) {
                $error = 'Book is not available for issue.';
            } else {
                // Check customer limits
                $stmt = $pdo->prepare("
                    SELECT c.max_books_allowed, c.membership_expiry,
                           (SELECT COUNT(*) FROM book_issues bi WHERE bi.customer_id = c.id AND bi.status = 'issued') as active_issues
                    FROM customers c WHERE c.id = ?
                ");
                $stmt->execute([$customer_id]);
                $customer = $stmt->fetch();
                
                if (!$customer) {
                    $error = 'Customer not found.';
                } elseif ($customer['membership_expiry'] && $customer['membership_expiry'] < date('Y-m-d')) {
                    $error = 'Customer membership has expired.';
                } elseif ($customer['active_issues'] >= $customer['max_books_allowed']) {
                    $error = 'Customer has reached their book limit.';
                } else {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    try {
                        // Create book issue record
                        $stmt = $pdo->prepare("
                            INSERT INTO book_issues (book_id, customer_id, issue_date, due_date, issued_by, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$book_id, $customer_id, $issue_date, $due_date, $_SESSION['user_id'], $notes]);
                        
                        // Update book availability
                        $stmt = $pdo->prepare("
                            UPDATE books SET available_copies = available_copies - 1 
                            WHERE id = ?
                        ");
                        $stmt->execute([$book_id]);
                        
                        $pdo->commit();
                        
                        setFlashMessage('success', 'Book issued successfully!');
                        redirect('index.php');
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Failed to issue book. Please try again.';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Failed to issue book. Please try again.';
        }
    }
}

// Pre-select book if provided
$selected_book = null;
if ($book_id) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $selected_book = $stmt->fetch();
}

// Pre-select customer if provided
$selected_customer = null;
if ($customer_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.email,
               (SELECT COUNT(*) FROM book_issues bi WHERE bi.customer_id = c.id AND bi.status = 'issued') as active_issues
        FROM customers c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$customer_id]);
    $selected_customer = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Book - Library Management System</title>
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
                            <a class="nav-link" href="../customers/">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../books/browse.php">
                                <i class="fas fa-search me-2"></i>Browse Books
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
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
                    <h1 class="h2">Issue Book</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Transactions
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Issue Book to Customer</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="book_id" class="form-label">Book *</label>
                                                <select class="form-select" id="book_id" name="book_id" required>
                                                    <option value="">Select a book</option>
                                                    <?php foreach ($books as $book): ?>
                                                        <option value="<?php echo $book['id']; ?>" 
                                                                <?php echo ($selected_book && $selected_book['id'] == $book['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($book['title']); ?> 
                                                            by <?php echo htmlspecialchars($book['author']); ?>
                                                            (<?php echo $book['available_copies']; ?> available)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="customer_id" class="form-label">Customer *</label>
                                                <select class="form-select" id="customer_id" name="customer_id" required>
                                                    <option value="">Select a customer</option>
                                                    <?php foreach ($customers as $customer): ?>
                                                        <option value="<?php echo $customer['id']; ?>" 
                                                                <?php echo ($selected_customer && $selected_customer['id'] == $customer['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($customer['customer_code']); ?> - 
                                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                                            (<?php echo $customer['active_issues']; ?>/<?php echo $customer['max_books_allowed']; ?> books)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="issue_date" class="form-label">Issue Date *</label>
                                                <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                                       value="<?php echo isset($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d'); ?>" 
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="due_date" class="form-label">Due Date *</label>
                                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                                       value="<?php echo isset($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+14 days')); ?>" 
                                                       required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="Any additional notes..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-bookmark me-2"></i>Issue Book
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Selected Book Info -->
                        <?php if ($selected_book): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-book me-2"></i>Selected Book</h6>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($selected_book['title']); ?></h6>
                                <p class="text-muted">by <?php echo htmlspecialchars($selected_book['author']); ?></p>
                                <p><strong>ISBN:</strong> <?php echo htmlspecialchars($selected_book['isbn']); ?></p>
                                <p><strong>Available:</strong> <?php echo $selected_book['available_copies']; ?> copies</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Selected Customer Info -->
                        <?php if ($selected_customer): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Selected Customer</h6>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($selected_customer['first_name'] . ' ' . $selected_customer['last_name']); ?></h6>
                                <p class="text-muted"><?php echo htmlspecialchars($selected_customer['customer_code']); ?></p>
                                <p><strong>Membership:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $selected_customer['membership_type'] === 'faculty' ? 'primary' : 
                                            ($selected_customer['membership_type'] === 'student' ? 'success' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($selected_customer['membership_type']); ?>
                                    </span>
                                </p>
                                <p><strong>Books Issued:</strong> <?php echo $selected_customer['active_issues']; ?>/<?php echo $selected_customer['max_books_allowed']; ?></p>
                                <?php if ($selected_customer['membership_expiry']): ?>
                                    <p><strong>Expires:</strong> <?php echo formatDate($selected_customer['membership_expiry']); ?></p>
                                <?php endif; ?>
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