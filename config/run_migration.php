<?php
include __DIR__ . '/config.php';

try {
    $cols = $conn->query("SHOW COLUMNS FROM user LIKE 'admin_id'")->fetchAll();
    if (count($cols) > 0) {
        echo "Kolom admin_id sudah ada. Tidak perlu migration.\n";
    } else {
        $conn->exec("ALTER TABLE `user` ADD COLUMN `admin_id` INT NULL DEFAULT NULL AFTER `role`");
        echo "Kolom admin_id berhasil ditambahkan!\n";
    }
    
    $stmt = $conn->query("SELECT COUNT(*) as c FROM user WHERE role = 'karyawan' AND admin_id IS NULL");
    $count = $stmt->fetch()['c'];
    if ($count > 0) {
        $admin = $conn->query("SELECT user_id FROM user WHERE role = 'admin' ORDER BY user_id ASC LIMIT 1")->fetch();
        if ($admin) {
            $conn->prepare("UPDATE user SET admin_id = ? WHERE role = 'karyawan' AND admin_id IS NULL")
                 ->execute([$admin['user_id']]);
            echo "$count karyawan dihubungkan ke admin (ID: {$admin['user_id']})\n";
        }
    }
    
    echo "\nMigration selesai!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
