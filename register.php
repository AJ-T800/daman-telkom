<?php
require 'koneksi.php';
$pesan = '';
$jenis_pesan = ''; // Menyimpan status apakah error atau success

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    // Cek apakah password dan konfirmasi sama
    if ($password !== $konfirmasi_password) {
        $pesan = "Password tidak cocok!";
        $jenis_pesan = "error";
    } else {
        // Cek apakah username sudah ada
        $stmt_cek = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE nama_pengguna = ?");
        $stmt_cek->bind_param("s", $username);
        $stmt_cek->execute();
        $stmt_cek->store_result();

        if ($stmt_cek->num_rows > 0) {
            $pesan = "Username sudah terdaftar, silakan gunakan yang lain.";
            $jenis_pesan = "error";
        } else {
            // Hash password agar aman
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'daman';
            $is_approved = 0; // Harus di-ACC manager dulu

            $stmt = $conn->prepare("INSERT INTO pengguna (nama_pengguna, kata_sandi, peran, disetujui) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $username, $hashed_password, $role, $is_approved);

            if ($stmt->execute()) {
                $pesan = "Registrasi berhasil! Silakan tunggu Manager menyetujui akun Anda.";
                $jenis_pesan = "success";
            } else {
                $pesan = "Terjadi kesalahan: " . $conn->error;
                $jenis_pesan = "error";
            }
            $stmt->close();
        }
        $stmt_cek->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Daman - Infranexia</title>
    <link rel="stylesheet" href="css/register.css?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>
<body>
    <div class="register-wrapper">
        <div class="register-left">
            <div class="form-container">
                <div class="logo-container">
                    <img src="css/assets/infranexia.png" alt="Infranexia Logo" class="logo">
                </div>
                
                <?php 
                // Menampilkan pesan error atau sukses
                if($pesan != '') {
                    $class_pesan = ($jenis_pesan == 'success') ? 'success-msg' : 'error-msg';
                    echo "<div class='$class_pesan'>$pesan</div>"; 
                }
                ?>
                
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

                    <div class="input-group">
                        <label>Konfirmasi Password</label>
                        <div class="password-container">
                            <input type="password" name="konfirmasi_password" id="konfirmasi_password" required>
                            <span class="material-symbols-outlined toggle-password" onclick="togglePassword('konfirmasi_password', this)">visibility</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-register">DAFTAR</button>
                </form>

                <div class="login-link">
                    Sudah punya akun? <a href="login.php">Masuk di sini</a>
                </div>
            </div>
        </div>

        <div class="register-right"></div>
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