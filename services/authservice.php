<?php
// =============================================
//   AUTH SERVICE
//   Kumpulan fungsi untuk autentikasi user
//   Cara pakai: include '../services/authservice.php';
// =============================================

/**
 * Cek apakah user sudah login
 * Kalau belum, redirect ke login
 */
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
}

/**
 * Ambil user_id dari session
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Ambil nama user dari session
 */
function getUserName() {
    return $_SESSION['user_name'] ?? 'User';
}

/**
 * Cek apakah user sudah login (return true/false)
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

/**
 * Login user — cek email & password, buat session
 * Return: 'success' | 'email_not_found' | 'wrong_password' | 'error'
 */
function loginUser($conn, $email, $password) {
    try {
        $stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return 'email_not_found';
        }

        if ($password !== $user['password']) {
            return 'wrong_password';
        }

        // Buat session
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['user_name'] = $user['nama'];

        return 'success';
    } catch (Exception $e) {
        return 'error';
    }
}

/**
 * Register user baru
 * Return: 'success' | 'email_exists' | 'error'
 */
function registerUser($conn, $nama, $email, $password) {
    try {
        // Cek email sudah ada belum
        $cek = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            return 'email_exists';
        }

        // Insert user baru
        $stmt = $conn->prepare("INSERT INTO user (nama, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $email, $password]);

        return 'success';
    } catch (Exception $e) {
        return 'error';
    }
}

/**
 * Logout user — hapus session
 */
function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

/**
 * Ganti password user
 * Return: 'success' | 'wrong_old_password' | 'error'
 */
function gantiPassword($conn, $userId, $passwordLama, $passwordBaru) {
    try {
        $stmt = $conn->prepare("SELECT password FROM user WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user['password'] !== $passwordLama) {
            return 'wrong_old_password';
        }

        $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?")
             ->execute([$passwordBaru, $userId]);

        return 'success';
    } catch (Exception $e) {
        return 'error';
    }
}