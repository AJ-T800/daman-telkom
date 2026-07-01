<?php
$host     = "localhost";
$user     = "root"; // Default username XAMPP
$password = "";     // Default password XAMPP kosong
$database = "db_daman_telkom"; // Sesuai nama database yang kita buat

// Membuat koneksi menggunakan MySQLi
$conn = new mysqli($host, $user, $password, $database);

// Mengecek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Hapus atau comment baris echo di bawah ini jika aplikasi sudah berjalan
// echo "Koneksi ke database berhasil!";
?>