<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id_pengguna'];
$pesan = "";
$status_class = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ganti_pass'])) {
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $konfirmasi = $_POST['konfirmasi'];

    $stmt = $conn->prepare("SELECT kata_sandi FROM pengguna WHERE id_pengguna = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!password_verify($pass_lama, $user['kata_sandi'])) {
        $pesan = "Gagal: Password lama Anda salah!";
        $status_class = "alert-error";
    } elseif ($pass_baru !== $konfirmasi) {
        $pesan = "Gagal: Konfirmasi password tidak cocok!";
        $status_class = "alert-error";
    } elseif (strlen($pass_baru) < 6) {
        $pesan = "Gagal: Password baru minimal 6 karakter!";
        $status_class = "alert-error";
    } else {
        $pass_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE pengguna SET kata_sandi = ? WHERE id_pengguna = ?");
        $update->bind_param("si", $pass_hash, $user_id);
        
        if ($update->execute()) {
            $pesan = "Sukses: Password berhasil diperbarui!";
            $status_class = "alert-success";
        } else {
            $pesan = "Terjadi kesalahan sistem.";
            $status_class = "alert-error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ganti Password - Infranexia</title>
    <link rel="stylesheet" href="css/ganti_password.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-container">

        <div class="dashboard-header">
            <h2 class="greeting">GANTI PASSWORD</h2>
            <img src="css/assets/infranexia.png" alt="Infranexia Logo" class="header-logo">
        </div>

        <div class="password-container">
            <div class="form-card">
                
                <div class="card-header">
                    <span class="material-symbols-outlined icon-lock">lock_reset</span>
                    <h3>Keamanan Akun</h3>
                    <p>Perbarui kata sandi Anda secara berkala untuk menjaga keamanan akun Infranexia Anda.</p>
                </div>

                <?php if ($pesan != ""): ?>
                    <div class="alert <?php echo $status_class; ?>">
                        <?php if($status_class == 'alert-success'): ?>
                            <span class="material-symbols-outlined">check_circle</span>
                        <?php else: ?>
                            <span class="material-symbols-outlined">error</span>
                        <?php endif; ?>
                        <?php echo $pesan; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" class="form-control" value="<?php echo $_SESSION['nama_pengguna']; ?>" readonly style="background-color: #f1f5f9; color: #64748b; cursor: not-allowed; border-color: #e2e8f0;">
                    </div>

                    <div class="form-group">
                        <label>Password Lama:</label>
                        <input type="password" name="pass_lama" id="pass_lama" class="form-control" placeholder="Masukkan password saat ini..." required>
                    </div>

                    <div class="form-group">
                        <label>Password Baru:</label>
                        <input type="password" name="pass_baru" id="pass_baru" class="form-control" placeholder="Minimal 6 karakter" required>
                    </div>

                    <div class="form-group">
                        <label>Konfirmasi Password Baru:</label>
                        <input type="password" name="konfirmasi" id="konfirmasi" class="form-control" placeholder="Ketik ulang password baru..." required>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="showPassword">
                        <label for="showPassword">Tampilkan Password</label>
                    </div>

                    <button type="submit" name="ganti_pass" class="btn-submit">
                        <span class="material-symbols-outlined">save</span> SIMPAN PASSWORD
                    </button>
                </form>

            </div>
        </div>

    </div> <script>
        // Fitur Tampilkan Password
        document.getElementById('showPassword').addEventListener('change', function() {
            const type = this.checked ? 'text' : 'password';
            document.getElementById('pass_lama').type = type;
            document.getElementById('pass_baru').type = type;
            document.getElementById('konfirmasi').type = type;
        });
    </script>
</body>
</html>