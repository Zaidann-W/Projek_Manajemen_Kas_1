<?php
// Root index untuk Vercel
// Redirect ke dashboard atau halaman login

session_start();

if (isset($_SESSION['user_id'])) {
    // User sudah login, arahkan ke dashboard
    header('Location: /dashboard/index.php');
} else {
    // User belum login, arahkan ke login
    header('Location: /auth/login.php');
}
exit;
