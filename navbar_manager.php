<?php
// Memastikan variabel menyesuaikan nama session yang baru
$username = isset($_SESSION['nama_pengguna']) ? $_SESSION['nama_pengguna'] : 'User';
$role = isset($_SESSION['peran']) ? $_SESSION['peran'] : '';
?>

<nav class="top-navbar">
    <div class="nav-left">
        <?php if ($role === 'manager'): ?>
            <a href="manajemen_karyawan.php" class="nav-link">MANAJEMEN KARYAWAN</a>
            <a href="riwayat_pekerjaan.php" class="nav-link">RIWAYAT KERJAAN</a>
        <?php elseif ($role === 'daman'): ?>
            <a href="daftar_permintaan.php" class="nav-link">PERMINTAAN</a>
            <a href="riwayat_pekerjaan_karyawan.php" class="nav-link">RIWAYAT</a>
        <?php endif; ?>
    </div>
    
    <div class="nav-right">
        <div class="dropdown">
            <button class="dropbtn" onclick="toggleDropdown()">
                &#9776; </button>
            <div class="dropdown-content" id="menuDropdown">
                <a href="ganti_password.php">Ganti Password</a>
                <a href="logout.php" style="color: #dc3545;" onclick="return confirm('Yakin ingin logout?')">Logout</a>
            </div>
        </div>
    </div>
</nav>

<script>
    // Fungsi untuk memunculkan/menyembunyikan menu dropdown
    function toggleDropdown() {
        document.getElementById("menuDropdown").classList.toggle("show");
    }

    // Menutup dropdown jika user mengklik di luar area menu
    window.onclick = function(event) {
        if (!event.target.matches('.dropbtn')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }
</script>