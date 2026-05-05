<?php
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
}


function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['user_name'] ?? 'User';
}

function getUserRole() {
    return $_SESSION['user_role'] ?? 'karyawan';
}
function isAdmin() {
    return getUserRole() === 'admin';
}

function getOwnerUserId() {
    if (isAdmin()) {
        return getUserId();
    }

    global $conn;
    $stmt = $conn->prepare("SELECT admin_id FROM user WHERE user_id = ?");
    $stmt->execute([getUserId()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['admin_id'] ?? getUserId();
}

function getBusinessUserIds() {
    global $conn;
    $ownerId = getOwnerUserId();
    // Ambil owner + semua karyawan di bawah owner
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE user_id = ? OR admin_id = ?");
    $stmt->execute([$ownerId, $ownerId]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id');
}


function buildInClause($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return ['placeholders' => $placeholders, 'values' => $ids];
}


function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}


function requireAdmin() {
    if (!isAdmin()) {
        $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini.';
        header("Location: ../dashboard/index.php");
        exit;
    }
}


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
        $_SESSION['user_role'] = $user['role'];

        return 'success';
    } catch (Exception $e) {
        return 'error';
    }
}

function registerUser($conn, $nama, $email, $password) {
    try {
        // Cek email sudah ada belum
        $cek = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            return 'email_exists';
        }

        // Insert user baru — pemilik UMKM = admin
        $stmt = $conn->prepare("INSERT INTO user (nama, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$nama, $email, $password]);

        return 'success';
    } catch (Exception $e) {
        return 'error';
    }
}


function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}


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
