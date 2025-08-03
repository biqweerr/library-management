<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('auth/login.php');
}
?> 