<?php
require_once '../includes/functions.php';
requireLogin();

$pdo = getConnection();

$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$book_id) {
    setFlashMessage('error', 'Book not found.');
    redirect('browse.php');
}

// Get book details
$stmt = $pdo->prepare("
    SELECT b.*, 
           (SELECT COUNT(*) FROM book_issues bi WHERE bi.book_id = b.id AND bi.status = 'issued') as issued_count
    FROM books b 
    WHERE b.id = ?
");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    setFlashMessage('error', 'Book not found.');
    redirect('browse.php');
}

// Get recent transactions for this book
$stmt = $pdo->prepare("
    SELECT bi.*, c.customer_code, u.first_name, u.last_name
    FROM book_issues bi
    JOIN customers c ON bi.customer_id = c.id
    JOIN users u ON bi.issued_by = u.id
    WHERE bi.book_id = ?
    ORDER BY bi.created_at DESC
    LIMIT 10
");
$stmt->execute([$book_id]);
$transactions = $stmt->fetchAll();

// Get current reservations
$stmt = $pdo->prepare("
    SELECT r.*, c.customer_code, u.first_name, u.last_name
    FROM reservations r
    JOIN customers c ON r.customer_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE r.book_id = ? AND r.status = 'pending'
    ORDER BY r.created_at DESC
");
$stmt->execute([$book_id]);
$reservations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Library Management System</title>
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
        .book-cover {
            height: 300px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            border-radius: 15px;
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
                        
                        <?php if (isAdmin() || isLibrarian()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-book me-2"></i>Books
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../customers/">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="browse.php">
                                <i class="fas fa-search me-2"></i>Browse Books
                            </a>
                        </li>
                        
                        <?php if (isAdmin() || isLibrarian()): ?>
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
                        <?php endif; ?>
                        
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
                    <h1 class="h2">Book Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="browse.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Browse
                        </a>
                        <?php if (isAdmin() || isLibrarian()): ?>
                            <a href="edit.php?id=<?php echo $book['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Edit Book
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php 
                $flash = getFlashMessage();
                if ($flash): 
                ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Book Information -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-book me-2"></i>Book Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="book-cover mb-3">
                                            <i class="fas fa-book"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                        <p class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></p>
                                        
                                        <div class="row mb-3">
                                            <div class="col-sm-6">
                                                <strong>ISBN:</strong><br>
                                                <code><?php echo htmlspecialchars($book['isbn']); ?></code>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Publisher:</strong><br>
                                                <?php echo htmlspecialchars($book['publisher'] ?: 'N/A'); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-sm-6">
                                                <strong>Publication Year:</strong><br>
                                                <?php echo $book['publication_year'] ?: 'N/A'; ?>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Genre:</strong><br>
                                                <?php if ($book['genre']): ?>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($book['genre']); ?></span>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-sm-6">
                                                <strong>Location:</strong><br>
                                                <?php echo htmlspecialchars($book['location'] ?: 'N/A'); ?>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Price:</strong><br>
                                                <?php echo $book['price'] ? formatCurrency($book['price']) : 'N/A'; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($book['description']): ?>
                                            <div class="mb-3">
                                                <strong>Description:</strong><br>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Availability Status -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Availability</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-primary"><?php echo $book['total_copies']; ?></h4>
                                            <small class="text-muted">Total Copies</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-success"><?php echo $book['available_copies']; ?></h4>
                                            <small class="text-muted">Available</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-info"><?php echo $book['issued_count']; ?></h4>
                                            <small class="text-muted">Currently Issued</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3">
                                            <h4 class="<?php echo $book['available_copies'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo getBookStatus($book['available_copies'], $book['total_copies']); ?>
                                            </h4>
                                            <small class="text-muted">Status</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($book['available_copies'] > 0): ?>
                                    <div class="text-center mt-3">
                                        <a href="../transactions/issue.php?book_id=<?php echo $book['id']; ?>" 
                                           class="btn btn-success">
                                            <i class="fas fa-bookmark me-2"></i>Reserve This Book
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center mt-3">
                                        <button class="btn btn-secondary" disabled>
                                            <i class="fas fa-clock me-2"></i>Not Available
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Recent Transactions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($transactions)): ?>
                                    <p class="text-muted">No transactions yet.</p>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <small class="text-muted"><?php echo htmlspecialchars($transaction['customer_code']); ?></small><br>
                                                <small><?php echo formatDate($transaction['issue_date']); ?></small>
                                            </div>
                                            <div>
                                                <?php echo getIssueStatus($transaction['status']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Current Reservations -->
                        <?php if (!empty($reservations)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Current Reservations</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($reservations as $reservation): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <small class="text-muted"><?php echo htmlspecialchars($reservation['customer_code']); ?></small><br>
                                            <small><?php echo formatDate($reservation['reservation_date']); ?></small>
                                        </div>
                                        <div>
                                            <?php echo getReservationStatus($reservation['status']); ?>
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