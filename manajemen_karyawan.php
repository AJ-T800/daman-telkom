<?php
session_start();
require 'koneksi.php';

// Cek apakah user sudah login dan rolenya adalah manager
if (!isset($_SESSION['id_pengguna']) || $_SESSION['peran'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Menangani Aksi Approve Karyawan
if (isset($_GET['approve'])) {
    $id_user = intval($_GET['approve']);
    // Modifikasi: users -> pengguna, is_approved -> disetujui, id -> id_pengguna
    $stmt = $conn->prepare("UPDATE pengguna SET disetujui = 1 WHERE id_pengguna = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    header("Location: manajemen_karyawan.php"); 
    exit();
}

// Menangani Aksi Hapus/Tolak Karyawan
if (isset($_GET['hapus'])) {
    $id_user = intval($_GET['hapus']);
    // Modifikasi: users -> pengguna, id -> id_pengguna
    $stmt = $conn->prepare("DELETE FROM pengguna WHERE id_pengguna = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    header("Location: manajemen_karyawan.php");
    exit();
}

// Menangani Aksi Reset Password Karyawan (Ketik Sendiri)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_reset_pass'])) {
    $id_user = intval($_POST['reset_user_id']);
    $password_baru = trim($_POST['password_baru']);
    
    if (strlen($password_baru) < 6) {
        echo "<script>alert('Gagal! Password baru minimal harus 6 karakter.'); window.location.href='manajemen_karyawan.php';</script>";
        exit();
    }
    
    // 1. Mengubah password teks biasa menjadi HASH menggunakan bcrypt (default PHP)
    $password_hashed = password_hash($password_baru, PASSWORD_DEFAULT);
    
    // 2. Gunakan $password_hashed di dalam query SQL
    // Modifikasi: users -> pengguna, password -> kata_sandi, id -> id_pengguna
    $stmt = $conn->prepare("UPDATE pengguna SET kata_sandi = ? WHERE id_pengguna = ?");
    $stmt->bind_param("si", $password_hashed, $id_user);
    $stmt->execute();
    
    echo "<script>alert('Berhasil! Password karyawan telah di-hash dan diperbarui.'); window.location.href='manajemen_karyawan.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Karyawan - Manager</title>
    <link rel="stylesheet" href="css/manajemen_karyawan.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <div class="dashboard-header">
    <h2 class="greeting">HALO MANAGER, <?php echo strtoupper($username); ?>!</h2>
    <img src="css/assets/infranexia.png" alt="Logo" class="header-logo" style="height: 40px;">
</div>

        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Status Akun</th>
                        <th>Waktu Daftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM pengguna WHERE peran = 'daman' ORDER BY disetujui ASC, dibuat_pada DESC");
                    if ($result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo $row['id_pengguna']; ?></td>
                        <td><b><?php echo $row['nama_pengguna']; ?></b></td>
                        <td>
                            <?php echo $row['disetujui'] == 1 ? '<span style="color:green; font-weight:bold;">Aktif</span>' : '<span style="color:red; font-weight:bold;">Menunggu</span>'; ?>
                        </td>
                        <td><?php echo $row['dibuat_pada']; ?></td>
                        <td>
                            <?php if ($row['disetujui'] == 0): ?>
                                <a href="?approve=<?php echo $row['id_pengguna']; ?>" class="btn-ambil" style="background-color:#28a745;">Approve</a>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-reset-yellow" onclick="bukaPromptReset('<?php echo $row['id_pengguna']; ?>', '<?php echo $row['nama_pengguna']; ?>')">
    Reset Pass
</button>
                            
                            <a href="?hapus=<?php echo $row['id_pengguna']; ?>" class="btn-reset" style="background-color:#dc3545; color:white;" onclick="return confirm('Yakin ingin menghapus akun ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align:center;">Belum ada data karyawan Daman.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <form id="formResetPass" method="POST" action="" style="display:none;">
        <input type="hidden" name="action_reset_pass" value="1">
        <input type="hidden" id="reset_user_id" name="reset_user_id" value="">
        <input type="hidden" id="password_baru" name="password_baru" value="">
    </form>

    <script>
        function bukaPromptReset(userId, username) {
            // Menampilkan kotak input bawaan browser (Prompt)
            let passwordInput = prompt("Masukkan Password Baru untuk karyawan '" + username + "':\n(Minimal 6 karakter)");
            
            // Jika manager menekan tombol Cancel atau membatalkan input
            if (passwordInput === null) {
                return;
            }
            
            // Validasi spasi kosong
            passwordInput = passwordInput.trim();
            if (passwordInput === "") {
                alert("Password tidak boleh kosong!");
                return;
            }
            
            if (passwordInput.length < 6) {
                alert("Password minimal harus 6 karakter!");
                return;
            }
            
            // Konfirmasi akhir sebelum disubmit
            let konfirmasi = confirm("Apakah Anda yakin ingin mengganti password '" + username + "' menjadi:\n" + passwordInput);
            if (konfirmasi) {
                // Masukkan data ke form tersembunyi lalu submit otomatis
                document.getElementById('reset_user_id').value = userId;
                document.getElementById('password_baru').value = passwordInput;
                document.getElementById('formResetPass').submit();
            }
        }
    </script>

</body>
</html>