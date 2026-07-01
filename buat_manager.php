<?php
require 'koneksi.php';

$username = 'manager1';
$password = 'admin123';

// Mengenkripsi password langsung dari server PHP kamu
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = 'manager';
$is_approved = 1;

// Hapus akun manager1 yang sebelumnya error (jika ada)
$conn->query("DELETE FROM users WHERE username = 'manager1'");

// Masukkan data manager yang baru
$stmt = $conn->prepare("INSERT INTO users (username, password, role, is_approved) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sssi", $username, $hashed_password, $role, $is_approved);

if ($stmt->execute()) {
    echo "<h3>Akun Manager berhasil dibuat ulang!</h3>";
    echo "<p>Silakan kembali ke halaman <a href='login.php'>Login</a> dan gunakan:</p>";
    echo "<p>Username: <b>manager1</b></p>";
    echo "<p>Password: <b>admin123</b></p>";
} else {
    echo "Gagal membuat akun: " . $conn->error;
}
?>