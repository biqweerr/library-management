<?php
require_once 'includes/functions.php';
requireLogin();

$pdo = getConnection();

// Get statistics based on user role
$stats = [];

if (isAdmin()) {
    // Admin can see all statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM books");
    $stats['total_books'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
    $stats['total_customers'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM book_issues WHERE status = 'issued'");
    $stats['issued_books'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM book_issues WHERE status = 'overdue'");
    $stats['overdue_books'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'");
    $stats['pending_reservations'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fines WHERE status = 'pending'");
    $stats['pending_fines'] = $stmt->fetch()['total'];
    
} elseif (isLibrarian()) {
    // Librarian can see book and customer statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM books");
    $stats['total_books'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
    $stats['total_customers'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM book_issues WHERE status = 'issued'");
    $stats['issued_books'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM book_issues WHERE status = 'overdue'");
    $stats['overdue_books'] = $stmt->fetch()['total'];
    
} else {
    // Member can see their own statistics
    $customer_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM book_issues WHERE customer_id = ? AND status = 'issued'");
    $stmt->execute([$customer_id]);
    $stats['my_issued_books'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reservations WHERE customer_id = ? AND status = 'pending'");
    $stmt->execute([$customer_id]);
    $stats['my_pending_reservations'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM fines WHERE customer_id = ? AND status = 'pending'");
    $stmt->execute([$customer_id]);
    $result = $stmt->fetch();
    $stats['my_pending_fines'] = $result['total'] ?: 0;
}

// Get recent activities
$recent_activities = [];
if (isAdmin() || isLibrarian()) {
    $stmt = $pdo->query("
        SELECT bi.*, b.title, c.customer_code, u.first_name, u.last_name
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        JOIN customers c ON bi.customer_id = c.id
        JOIN users u ON bi.issued_by = u.id
        ORDER BY bi.created_at DESC
        LIMIT 5
    ");
    $recent_activities = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT bi.*, b.title
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        WHERE bi.customer_id = ?
        ORDER BY bi.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_activities = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Library Management System</title>
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
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
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
                            <a class="nav-link active" href="dashboard.php">
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
                        <li class="nav-item">
                            <a class="nav-link" href="reports/">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="badge bg-primary"><?php echo ucfirst($_SESSION['role']); ?></span>
                        </div>
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

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php if (isAdmin()): ?>
                        <div class="col-xl-2 col-md-4 mb-4">
                            <div class="card stat-card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Books</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_books']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-book stat-icon text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 mb-4">
                            <div class="card stat-card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Customers</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_customers']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users stat-icon text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 mb-4">
                            <div class="card stat-card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Issued Books</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['issued_books']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clipboard-list stat-icon text-info"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 mb-4">
                            <div class="card stat-card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Overdue</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['overdue_books']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-triangle stat-icon text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 mb-4">
                            <div class="card stat-card border-left-secondary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Reservations</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_reservations']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock stat-icon text-secondary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 mb-4">
                            <div class="card stat-card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Pending Fines</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_fines']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-dollar-sign stat-icon text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif (isLibrarian()): ?>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Books</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_books']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-book stat-icon text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Customers</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_customers']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users stat-icon text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Issued Books</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['issued_books']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clipboard-list stat-icon text-info"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Overdue</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['overdue_books']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-triangle stat-icon text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card stat-card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">My Issued Books</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['my_issued_books']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-book stat-icon text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card stat-card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Reservations</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['my_pending_reservations']; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock stat-icon text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card stat-card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Pending Fines</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['my_pending_fines']); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-dollar-sign stat-icon text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                    <p class="text-muted">No recent activities.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Book</th>
                                                    <?php if (isAdmin() || isLibrarian()): ?>
                                                        <th>Customer</th>
                                                    <?php endif; ?>
                                                    <th>Issue Date</th>
                                                    <th>Due Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_activities as $activity): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                                        <?php if (isAdmin() || isLibrarian()): ?>
                                                            <td><?php echo htmlspecialchars($activity['customer_code']); ?></td>
                                                        <?php endif; ?>
                                                        <td><?php echo formatDate($activity['issue_date']); ?></td>
                                                        <td><?php echo formatDate($activity['due_date']); ?></td>
                                                        <td><?php echo getIssueStatus($activity['status']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 