<?php
session_start();
require 'koneksi.php';
$pesan = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Modifikasi: Nama tabel dan kolom disesuaikan dengan DB baru
    $stmt = $conn->prepare("SELECT id_pengguna, kata_sandi, peran, disetujui FROM pengguna WHERE nama_pengguna = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Modifikasi: Verifikasi menggunakan kolom kata_sandi
        if (password_verify($password, $row['kata_sandi'])) {
            
            // Modifikasi: Cek menggunakan kolom peran dan disetujui
            if ($row['peran'] == 'daman' && $row['disetujui'] == 0) {
                $pesan = "Akun Anda belum disetujui oleh Manager. Silakan hubungi Manager Anda.";
            } else {
                // Modifikasi: Set Session menggunakan nama kolom baru
                $_SESSION['id_pengguna'] = $row['id_pengguna'];
                $_SESSION['nama_pengguna'] = $username;
                $_SESSION['peran'] = $row['peran'];

                // =========================================================
                // PERBAIKAN LOGIKA REDIRECT BERDASARKAN PERAN
                // =========================================================
                if ($row['peran'] == 'manager') {
                    header("Location: manajemen_karyawan.php");
                } else if ($row['peran'] == 'daman') {
                    header("Location: dashboard_utama_karyawan.php"); // Diarahkan ke Dashboard Utama Karyawan
                } else {
                    header("Location: daftar_permintaan.php"); // Fallback ke daftar permintaan jika role tidak dikenal
                }
                exit();
            }
        } else {
            $pesan = "Password salah!";
        }
    } else {
        $pesan = "Username tidak ditemukan!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem Daman - Infra Exia</title>
    <link rel="stylesheet" href="css/style_login.css?<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>
<body>
    <div class="login-wrapper">
        <div class="login-left">
            <div class="form-container">
                <div class="logo-container">
                    <img src="css/assets/infranexia.png" alt="Infra Exia by Telkom Indonesia" class="logo">
                </div>
                
                <?php if($pesan != '') echo "<div class='error-msg'>$pesan</div>"; ?>
                
                <form method="POST" action="">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Password</label>
                        <div class="password-container">
                            <input type="password" name="password" id="password" required>
                            <span class="material-symbols-outlined toggle-password" onclick="togglePassword('password', this)">visibility</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">LOGIN</button>
                </form>
                
                <p class="register-link">Belum punya akun? <a href="register.php">Daftar</a></p>
            </div>
        </div>

        <div class="login-right">
        </div>
    </div>
    <script>
    function togglePassword(inputId, iconElement) {
        const passwordInput = document.getElementById(inputId);
        
        if (passwordInput.type === "password") {
            // Ubah input menjadi text biasa (terlihat)
            passwordInput.type = "text";
            // Ubah ikon mata menjadi ikon mata dicoret
            iconElement.textContent = "visibility_off";
        } else {
            // Kembalikan input menjadi password (tersembunyi)
            passwordInput.type = "password";
            // Kembalikan ikon menjadi mata terbuka
            iconElement.textContent = "visibility";
        }
    }
    </script>
</body>
</html>