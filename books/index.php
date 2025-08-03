<?php
require_once '../includes/functions.php';
requireLibrarian();

$pdo = getConnection();

// Handle search
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$genre_filter = isset($_GET['genre']) ? sanitizeInput($_GET['genre']) : '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($genre_filter)) {
    $where_conditions[] = "b.genre = ?";
    $params[] = $genre_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get books with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$count_query = "SELECT COUNT(*) as total FROM books b $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_books = $stmt->fetch()['total'];
$total_pages = ceil($total_books / $per_page);

$query = "
    SELECT b.*, 
           (SELECT COUNT(*) FROM book_issues bi WHERE bi.book_id = b.id AND bi.status = 'issued') as issued_count
    FROM books b 
    $where_clause 
    ORDER BY b.title 
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get genres for filter
$genres = $pdo->query("SELECT DISTINCT genre FROM books WHERE genre IS NOT NULL ORDER BY genre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books Management - Library Management System</title>
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
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-book me-2"></i>Books
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../customers/">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="browse.php">
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
                    <h1 class="h2">Books Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Book
                        </a>
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

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by title, author, or ISBN">
                            </div>
                            <div class="col-md-3">
                                <label for="genre" class="form-label">Genre</label>
                                <select class="form-select" id="genre" name="genre">
                                    <option value="">All Genres</option>
                                    <?php foreach ($genres as $genre): ?>
                                        <option value="<?php echo htmlspecialchars($genre['genre']); ?>" 
                                                <?php echo $genre_filter === $genre['genre'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($genre['genre']); ?>
                                        </option>
                                    <?php endforeach; ?>
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

                <!-- Books Table -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Books (<?php echo $total_books; ?> total)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($books)): ?>
                            <p class="text-muted text-center py-4">No books found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Author</th>
                                            <th>ISBN</th>
                                            <th>Genre</th>
                                            <th>Copies</th>
                                            <th>Available</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($books as $book): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                                    <?php if ($book['publication_year']): ?>
                                                        <br><small class="text-muted"><?php echo $book['publication_year']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                                <td><code><?php echo htmlspecialchars($book['isbn']); ?></code></td>
                                                <td>
                                                    <?php if ($book['genre']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($book['genre']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $book['total_copies']; ?></td>
                                                <td><?php echo $book['available_copies']; ?></td>
                                                <td><?php echo getBookStatus($book['available_copies'], $book['total_copies']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?php echo $book['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $book['id']; ?>" 
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="delete.php?id=<?php echo $book['id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger" title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this book?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Books pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&genre=<?php echo urlencode($genre_filter); ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&genre=<?php echo urlencode($genre_filter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&genre=<?php echo urlencode($genre_filter); ?>">
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