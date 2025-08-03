<?php
require_once '../includes/functions.php';
requireLibrarian();

$pdo = getConnection();

// Handle search
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(lp.pass_number LIKE ? OR c.customer_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "lp.status = ?";
    $params[] = $status_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get passes with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$count_query = "
    SELECT COUNT(*) as total 
    FROM library_passes lp
    JOIN customers c ON lp.customer_id = c.id
    LEFT JOIN users u ON c.user_id = u.id
    $where_clause
";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_passes = $stmt->fetch()['total'];
$total_pages = ceil($total_passes / $per_page);

$query = "
    SELECT lp.*, c.customer_code, c.membership_type, c.membership_expiry,
           u.first_name, u.last_name, u.email,
           (SELECT COUNT(*) FROM book_issues bi WHERE bi.customer_id = c.id AND bi.status = 'issued') as active_issues
    FROM library_passes lp
    JOIN customers c ON lp.customer_id = c.id
    LEFT JOIN users u ON c.user_id = u.id
    $where_clause 
    ORDER BY lp.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$passes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Passes - Library Management System</title>
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
                            <a class="nav-link" href="../transactions/">
                                <i class="fas fa-exchange-alt me-2"></i>Transactions
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
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
                    <h1 class="h2">Library Passes</h1>
                    
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

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Pass number, customer code, or name">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Passes Table -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Library Passes (<?php echo $total_passes; ?> total)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($passes)): ?>
                            <p class="text-muted text-center py-4">No library passes found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Pass Number</th>
                                            <th>Customer</th>
                                            <th>Membership Type</th>
                                            <th>Issue Date</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                            <th>Active Issues</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($passes as $pass): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($pass['pass_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($pass['first_name']): ?>
                                                        <?php echo htmlspecialchars($pass['first_name'] . ' ' . $pass['last_name']); ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($pass['customer_code']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?php echo htmlspecialchars($pass['customer_code']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $pass['membership_type'] === 'faculty' ? 'primary' : 
                                                            ($pass['membership_type'] === 'student' ? 'success' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($pass['membership_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($pass['issue_date']); ?></td>
                                                <td>
                                                    <?php echo formatDate($pass['expiry_date']); ?>
                                                    <?php if ($pass['expiry_date'] < date('Y-m-d')): ?>
                                                        <br><small class="text-danger">Expired</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo getPassStatus($pass['status']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $pass['active_issues']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?php echo $pass['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $pass['id']; ?>" 
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($pass['status'] === 'active'): ?>
                                                            <a href="suspend.php?id=<?php echo $pass['id']; ?>" 
                                                               class="btn btn-sm btn-outline-danger" title="Suspend"
                                                               onclick="return confirm('Are you sure you want to suspend this pass?')">
                                                                <i class="fas fa-ban"></i>
                                                            </a>
                                                        <?php elseif ($pass['status'] === 'suspended'): ?>
                                                            <a href="activate.php?id=<?php echo $pass['id']; ?>" 
                                                               class="btn btn-sm btn-outline-success" title="Activate">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Passes pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                    Next
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 