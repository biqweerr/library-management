<?php
require_once '../includes/functions.php';
requireLibrarian();

$pdo = getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isbn = sanitizeInput($_POST['isbn']);
    $title = sanitizeInput($_POST['title']);
    $author = sanitizeInput($_POST['author']);
    $publisher = sanitizeInput($_POST['publisher']);
    $publication_year = sanitizeInput($_POST['publication_year']);
    $genre = sanitizeInput($_POST['genre']);
    $description = sanitizeInput($_POST['description']);
    $total_copies = (int)$_POST['total_copies'];
    $location = sanitizeInput($_POST['location']);
    $price = (float)$_POST['price'];
    
    // Validation
    if (empty($isbn) || empty($title) || empty($author)) {
        $error = 'Please fill in all required fields.';
    } elseif ($total_copies < 1) {
        $error = 'Total copies must be at least 1.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } else {
        try {
            // Check if ISBN already exists
            $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
            $stmt->execute([$isbn]);
            if ($stmt->fetch()) {
                $error = 'A book with this ISBN already exists.';
            } else {
                // Insert new book
                $stmt = $pdo->prepare("
                    INSERT INTO books (isbn, title, author, publisher, publication_year, genre, description, total_copies, available_copies, location, price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $isbn, $title, $author, $publisher, $publication_year, $genre, 
                    $description, $total_copies, $total_copies, $location, $price
                ]);
                
                setFlashMessage('success', 'Book added successfully!');
                redirect('index.php');
            }
        } catch (Exception $e) {
            $error = 'Failed to add book. Please try again.';
        }
    }
}

// Get genres for dropdown
$genres = [
    'Fiction', 'Non-Fiction', 'Mystery', 'Thriller', 'Romance', 'Science Fiction', 
    'Fantasy', 'Horror', 'Biography', 'Autobiography', 'History', 'Science', 
    'Technology', 'Philosophy', 'Religion', 'Self-Help', 'Business', 'Economics',
    'Politics', 'Education', 'Children', 'Young Adult', 'Poetry', 'Drama', 'Comedy'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book - Library Management System</title>
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
                    <h1 class="h2">Add New Book</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Books
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
                        <h6 class="m-0 font-weight-bold text-primary">Book Information</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="isbn" class="form-label">ISBN *</label>
                                        <input type="text" class="form-control" id="isbn" name="isbn" 
                                               value="<?php echo isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : ''; ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="author" class="form-label">Author *</label>
                                        <input type="text" class="form-control" id="author" name="author" 
                                               value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : ''; ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="publisher" class="form-label">Publisher</label>
                                        <input type="text" class="form-control" id="publisher" name="publisher" 
                                               value="<?php echo isset($_POST['publisher']) ? htmlspecialchars($_POST['publisher']) : ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="publication_year" class="form-label">Publication Year</label>
                                        <input type="number" class="form-control" id="publication_year" name="publication_year" 
                                               value="<?php echo isset($_POST['publication_year']) ? htmlspecialchars($_POST['publication_year']) : ''; ?>" 
                                               min="1800" max="<?php echo date('Y'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="genre" class="form-label">Genre</label>
                                        <select class="form-select" id="genre" name="genre">
                                            <option value="">Select Genre</option>
                                            <?php foreach ($genres as $g): ?>
                                                <option value="<?php echo $g; ?>" 
                                                        <?php echo (isset($_POST['genre']) && $_POST['genre'] === $g) ? 'selected' : ''; ?>>
                                                    <?php echo $g; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="total_copies" class="form-label">Total Copies *</label>
                                        <input type="number" class="form-control" id="total_copies" name="total_copies" 
                                               value="<?php echo isset($_POST['total_copies']) ? htmlspecialchars($_POST['total_copies']) : '1'; ?>" 
                                               min="1" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" 
                                               placeholder="e.g., Shelf A1, Section 2">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price ($)</label>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" 
                                               min="0" step="0.01">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" 
                                          placeholder="Enter book description..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Add Book
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