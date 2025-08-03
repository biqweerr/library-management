<?php
// Installation script for Library Management System

// Check if already installed
if (file_exists('db/connection.php') && file_exists('includes/functions.php')) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<strong>Already Installed!</strong> The Library Management System appears to be already installed. ";
    echo "If you need to reinstall, please delete the existing files first.";
    echo "</div>";
    exit();
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Database configuration
        $db_host = $_POST['db_host'];
        $db_name = $_POST['db_name'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];
        
        if (empty($db_host) || empty($db_name) || empty($db_user)) {
            $error = 'Please fill in all required fields.';
        } else {
            // Test database connection
            try {
                $pdo = new PDO("mysql:host=$db_host;charset=utf8", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create database if it doesn't exist
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
                $pdo->exec("USE `$db_name`");
                
                // Import database schema
                $sql = file_get_contents('db/database.sql');
                $pdo->exec($sql);
                
                // Create connection file
                $connection_content = "<?php
// Database configuration
define('DB_HOST', '$db_host');
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');

// Create database connection
function getConnection() {
    try {
        \$pdo = new PDO(
            \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8\",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return \$pdo;
    } catch (PDOException \$e) {
        die(\"Connection failed: \" . \$e->getMessage());
    }
}

// Test connection
function testConnection() {
    try {
        \$pdo = getConnection();
        return true;
    } catch (Exception \$e) {
        return false;
    }
}
?>";
                
                file_put_contents('db/connection.php', $connection_content);
                
                $success = 'Database setup completed successfully!';
                $step = 2;
            } catch (Exception $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
        }
    } elseif ($step === 2) {
        // Admin account setup
        $admin_username = $_POST['admin_username'];
        $admin_email = $_POST['admin_email'];
        $admin_password = $_POST['admin_password'];
        $admin_first_name = $_POST['admin_first_name'];
        $admin_last_name = $_POST['admin_last_name'];
        
        if (empty($admin_username) || empty($admin_email) || empty($admin_password) || 
            empty($admin_first_name) || empty($admin_last_name)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($admin_password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            try {
                $pdo = getConnection();
                
                // Update admin user
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                    username = ?, email = ?, password = ?, 
                    first_name = ?, last_name = ?
                    WHERE role = 'admin' LIMIT 1
                ");
                $stmt->execute([$admin_username, $admin_email, $hashed_password, $admin_first_name, $admin_last_name]);
                
                $success = 'Admin account setup completed!';
                $step = 3;
            } catch (Exception $e) {
                $error = 'Failed to setup admin account: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .install-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .install-body {
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .step.pending {
            background: #e9ecef;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="install-card">
                    <div class="install-header">
                        <h3><i class="fas fa-book-open me-2"></i>Library Management System</h3>
                        <p class="mb-0">Installation Wizard</p>
                    </div>
                    <div class="install-body">
                        <!-- Step Indicator -->
                        <div class="step-indicator">
                            <div class="step <?php echo $step >= 1 ? 'active' : 'pending'; ?>">1</div>
                            <div class="step <?php echo $step >= 2 ? 'completed' : ($step > 1 ? 'active' : 'pending'); ?>">2</div>
                            <div class="step <?php echo $step >= 3 ? 'completed' : ($step > 2 ? 'active' : 'pending'); ?>">3</div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($step === 1): ?>
                            <!-- Step 1: Database Configuration -->
                            <h5 class="mb-3">Step 1: Database Configuration</h5>
                            <p class="text-muted mb-4">Please provide your MySQL database credentials.</p>
                            
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="db_host" class="form-label">Database Host *</label>
                                            <input type="text" class="form-control" id="db_host" name="db_host" 
                                                   value="<?php echo isset($_POST['db_host']) ? htmlspecialchars($_POST['db_host']) : 'localhost'; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="db_name" class="form-label">Database Name *</label>
                                            <input type="text" class="form-control" id="db_name" name="db_name" 
                                                   value="<?php echo isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : 'library_management'; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="db_user" class="form-label">Database Username *</label>
                                            <input type="text" class="form-control" id="db_user" name="db_user" 
                                                   value="<?php echo isset($_POST['db_user']) ? htmlspecialchars($_POST['db_user']) : 'root'; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="db_pass" class="form-label">Database Password</label>
                                            <input type="password" class="form-control" id="db_pass" name="db_pass" 
                                                   value="<?php echo isset($_POST['db_pass']) ? htmlspecialchars($_POST['db_pass']) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-arrow-right me-2"></i>Next Step
                                    </button>
                                </div>
                            </form>

                        <?php elseif ($step === 2): ?>
                            <!-- Step 2: Admin Account Setup -->
                            <h5 class="mb-3">Step 2: Admin Account Setup</h5>
                            <p class="text-muted mb-4">Create your administrator account.</p>
                            
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="admin_username" class="form-label">Username *</label>
                                            <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                                   value="<?php echo isset($_POST['admin_username']) ? htmlspecialchars($_POST['admin_username']) : 'admin'; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="admin_email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                                   value="<?php echo isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : 'admin@library.com'; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="admin_first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="admin_first_name" name="admin_first_name" 
                                                   value="<?php echo isset($_POST['admin_first_name']) ? htmlspecialchars($_POST['admin_first_name']) : 'System'; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="admin_last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="admin_last_name" name="admin_last_name" 
                                                   value="<?php echo isset($_POST['admin_last_name']) ? htmlspecialchars($_POST['admin_last_name']) : 'Administrator'; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                           value="<?php echo isset($_POST['admin_password']) ? htmlspecialchars($_POST['admin_password']) : ''; ?>" required>
                                    <small class="text-muted">Password must be at least 6 characters long.</small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="?step=1" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Previous
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-arrow-right me-2"></i>Next Step
                                    </button>
                                </div>
                            </form>

                        <?php elseif ($step === 3): ?>
                            <!-- Step 3: Installation Complete -->
                            <div class="text-center">
                                <div class="mb-4">
                                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                                </div>
                                <h5 class="mb-3">Installation Complete!</h5>
                                <p class="text-muted mb-4">Your Library Management System has been successfully installed.</p>
                                
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Important Information</h6>
                                    <ul class="mb-0 text-start">
                                        <li><strong>Admin Login:</strong> Use the credentials you just created</li>
                                        <li><strong>Demo Accounts:</strong> The system includes demo accounts (librarian/password, member/password)</li>
                                        <li><strong>Security:</strong> Delete this install.php file for security</li>
                                        <li><strong>Database:</strong> Your database has been created with sample data</li>
                                    </ul>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="index.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-rocket me-2"></i>Go to Library System
                                    </a>
                                    <a href="auth/login.php" class="btn btn-outline-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login to System
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 