<?php
require_once '../includes/functions.php';
requireLibrarian();

$pdo = getConnection();

$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transaction_id) {
    setFlashMessage('error', 'Transaction not found.');
    redirect('index.php');
}

// Get transaction details
$stmt = $pdo->prepare("
    SELECT bi.*, b.title, b.isbn, b.available_copies,
           c.customer_code, c.membership_type,
           u.first_name, u.last_name
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.id
    JOIN customers c ON bi.customer_id = c.id
    JOIN users u ON bi.customer_id = u.id
    WHERE bi.id = ? AND bi.status = 'issued'
");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    setFlashMessage('error', 'Transaction not found or already returned.');
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $return_date = sanitizeInput($_POST['return_date']);
    $fine_amount = (float)$_POST['fine_amount'];
    $fine_type = sanitizeInput($_POST['fine_type']);
    $fine_description = sanitizeInput($_POST['fine_description']);
    $notes = sanitizeInput($_POST['notes']);
    
    if (empty($return_date)) {
        $error = 'Please select a return date.';
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update book issue record
            $stmt = $pdo->prepare("
                UPDATE book_issues 
                SET return_date = ?, status = 'returned', returned_to = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$return_date, $_SESSION['user_id'], $notes]);
            
            // Update book availability
            $stmt = $pdo->prepare("
                UPDATE books 
                SET available_copies = available_copies + 1 
                WHERE id = ?
            ");
            $stmt->execute([$transaction['book_id']]);
            
            // Create fine record if there's a fine
            if ($fine_amount > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO fines (book_issue_id, customer_id, fine_type, amount, description) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$transaction_id, $transaction['customer_id'], $fine_type, $fine_amount, $fine_description]);
                
                // Update customer fine balance
                $stmt = $pdo->prepare("
                    UPDATE customers 
                    SET fine_balance = fine_balance + ? 
                    WHERE id = ?
                ");
                $stmt->execute([$fine_amount, $transaction['customer_id']]);
            }
            
            $pdo->commit();
            
            setFlashMessage('success', 'Book returned successfully!');
            redirect('index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to return book. Please try again.';
        }
    }
}

// Calculate potential fine
$potential_fine = calculateFine($transaction['due_date'], date('Y-m-d'));
$is_overdue = $transaction['due_date'] < date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book - Library Management System</title>
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
                    <h1 class="h2">Return Book</h1>
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
                                <h6 class="m-0 font-weight-bold text-primary">Return Book</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="return_date" class="form-label">Return Date *</label>
                                                <input type="date" class="form-control" id="return_date" name="return_date" 
                                                       value="<?php echo isset($_POST['return_date']) ? $_POST['return_date'] : date('Y-m-d'); ?>" 
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="fine_amount" class="form-label">Fine Amount</label>
                                                <input type="number" class="form-control" id="fine_amount" name="fine_amount" 
                                                       value="<?php echo isset($_POST['fine_amount']) ? $_POST['fine_amount'] : $potential_fine; ?>" 
                                                       step="0.01" min="0">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="fine_type" class="form-label">Fine Type</label>
                                                <select class="form-select" id="fine_type" name="fine_type">
                                                    <option value="">No Fine</option>
                                                    <option value="late_return" <?php echo $is_overdue ? 'selected' : ''; ?>>Late Return</option>
                                                    <option value="damage">Damage</option>
                                                    <option value="lost">Lost Book</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="fine_description" class="form-label">Fine Description</label>
                                                <input type="text" class="form-control" id="fine_description" name="fine_description" 
                                                       value="<?php echo isset($_POST['fine_description']) ? htmlspecialchars($_POST['fine_description']) : ''; ?>" 
                                                       placeholder="Description of the fine">
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
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-undo me-2"></i>Return Book
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Transaction Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Transaction Details</h6>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($transaction['title']); ?></h6>
                                <p class="text-muted">by <?php echo htmlspecialchars($transaction['author']); ?></p>
                                <p><strong>ISBN:</strong> <?php echo htmlspecialchars($transaction['isbn']); ?></p>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></p>
                                <p><strong>Customer Code:</strong> <?php echo htmlspecialchars($transaction['customer_code']); ?></p>
                                <p><strong>Issue Date:</strong> <?php echo formatDate($transaction['issue_date']); ?></p>
                                <p><strong>Due Date:</strong> <?php echo formatDate($transaction['due_date']); ?></p>
                                
                                <?php if ($is_overdue): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Overdue!</strong> This book is overdue by <?php echo floor((strtotime(date('Y-m-d')) - strtotime($transaction['due_date'])) / (60 * 60 * 24)); ?> days.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Fine Calculation -->
                        <?php if ($potential_fine > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Fine Calculation</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Due Date:</strong> <?php echo formatDate($transaction['due_date']); ?></p>
                                <p><strong>Return Date:</strong> <?php echo formatDate(date('Y-m-d')); ?></p>
                                <p><strong>Days Overdue:</strong> <?php echo floor((strtotime(date('Y-m-d')) - strtotime($transaction['due_date'])) / (60 * 60 * 24)); ?> days</p>
                                <p><strong>Daily Rate:</strong> $0.50</p>
                                <p><strong>Calculated Fine:</strong> <span class="text-danger"><?php echo formatCurrency($potential_fine); ?></span></p>
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