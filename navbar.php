<?php
// 1. Deteksi nama file halaman yang sedang aktif saat ini
$current_page = basename($_SERVER['PHP_SELF']);

// 2. Ambil data session
$username = isset($_SESSION['nama_pengguna']) ? $_SESSION['nama_pengguna'] : 'User';
$role = isset($_SESSION['peran']) ? $_SESSION['peran'] : '';
?>

<?php if ($role === 'manager'): ?>
    <nav class="sidebar-navbar">
        <div class="sidebar-brand">
            <h2>Manager Panel</h2>
        </div>
        
        <div class="sidebar-menu">
        <a href="dashboard_utama_manager.php" class="nav-link <?php echo ($current_page == 'dashboard_utama_manager.php') ? 'active' : ''; ?>">DASHBOARD UTAMA</a>
            <a href="manajemen_karyawan.php" class="nav-link <?php echo ($current_page == 'manajemen_karyawan.php') ? 'active' : ''; ?>">MANAJEMEN KARYAWAN</a>
            <a href="riwayat_pekerjaan.php" class="nav-link <?php echo ($current_page == 'riwayat_pekerjaan.php') ? 'active' : ''; ?>">RIWAYAT KERJAAN</a>
        </div>
        
        <div class="sidebar-bottom">
            <a href="ganti_password.php" class="nav-link <?php echo ($current_page == 'ganti_password.php') ? 'active' : ''; ?>">Ganti Password</a>
            <a href="logout.php" class="nav-link logout-link" onclick="return confirm('Yakin ingin logout?')">Logout</a>
        </div>
    </nav>
    
    <style>
        body { padding-left: 250px; } 
    </style>

<?php else: ?>
    <nav class="top-navbar">
        <div class="nav-left">
            <?php if ($role === 'daman'): ?>
                <a href="dashboard_utama_karyawan.php" class="nav-link <?php echo ($current_page == 'dashboard_utama_karyawan.php') ? 'active' : ''; ?>">DASHBOARD</a>
                <a href="daftar_permintaan.php" class="nav-link <?php echo ($current_page == 'daftar_permintaan.php') ? 'active' : ''; ?>">PERMINTAAN</a>
                <a href="riwayat_pekerjaan_karyawan.php" class="nav-link <?php echo ($current_page == 'riwayat_pekerjaan_karyawan.php') ? 'active' : ''; ?>">RIWAYAT</a>
            <?php endif; ?>
        </div>
        
        <div class="nav-right">
            <div class="dropdown">
                <button class="dropbtn" onclick="toggleDropdown()">&#9776;</button>
                <div class="dropdown-content" id="menuDropdown">
                    <a href="ganti_password.php" class="<?php echo ($current_page == 'ganti_password.php') ? 'dropdown-active' : ''; ?>">Ganti Password</a>
                    <a href="logout.php" style="color: #dc3545;" onclick="return confirm('Yakin ingin logout?')">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleDropdown() {
            document.getElementById("menuDropdown").classList.toggle("show");
        }
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
<?php endif; ?>


<style>
/* 1. Tambahkan import font Montserrat di bagian paling atas style navbar */
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap');

/* --- A. STYLE TOP NAVBAR (Daman) --- */
.top-navbar {
    background-color: #CC1F29;
    padding: 15px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 999;
    position: sticky;  /* Membuat elemen melayang/menempel saat di-scroll */
    top: 0;            /* Menentukan posisi menempelnya tepat di paling atas layar */
    
}
.top-navbar .nav-left { display: flex; gap: 10px; }
.top-navbar .nav-link {
    font-family: 'Montserrat', sans-serif; /* 2. BARU: Paksa navbar selalu pakai Montserrat */
    color: #FFFFFF;
    text-decoration: none;
    font-weight: 800;
    font-size: 16px;
    padding: 8px 15px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

/* 3. BARU: Pindahkan efek hover dari daftar_permintaan ke sini */
.top-navbar .nav-link:hover:not(.active) {
    background-color: rgba(255, 255, 255, 0.15);
    transform: translateY(-1px);
    cursor: pointer;
}

.top-navbar .nav-link.active {
    font-family: 'Montserrat', sans-serif; /* Pastikan menu aktif juga menggunakan font yang sama */
    background-color: #FFFFFF !important;      
    color: #CC1F29 !important;                 
    font-weight: 800;
    border-radius: 30px;                       
    padding: 8px 22px;                         
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);  
}
.top-navbar .dropbtn { background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
.dropdown { position: relative; display: inline-block; }
.dropdown-content { display: none; position: absolute; right: 0; background: white; min-width: 160px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000; border-radius: 6px; overflow: hidden; }
.dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 14px; font-weight: 600; }
.dropdown-content a:hover { background-color: #f1f1f1; }
.dropdown-content a.dropdown-active { background-color: #f8d7da; color: #CC1F29; font-weight: 700; }
.show { display: block; }

/* --- B. STYLE SIDEBAR NAVBAR (Manager) --- */
.sidebar-navbar {
    position: fixed;
    top: 0; left: 0; width: 250px; height: 100vh;
    background-color: #2c446b; color: white;
    display: flex; flex-direction: column;
    box-sizing: border-box; z-index: 1000;
}
.sidebar-brand { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 20px; }
.sidebar-brand h2 { margin: 0; font-size: 20px; color: #FFFFFF; font-weight: 800; }
.sidebar-menu { display: flex; flex-direction: column; gap: 10px; padding: 0 20px; flex-grow: 1; }
.sidebar-navbar .nav-link {
    font-family: 'Montserrat', sans-serif; /* Opsional: Jika panel manager juga ingin menggunakan Montserrat */
    color: #FFFFFF; text-decoration: none; font-weight: 700;
    padding: 12px 15px; border-radius: 4px; transition: all 0.3s ease;
    border-left: 5px solid transparent;       
}
.sidebar-navbar .nav-link.active {
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.02)) !important; 
    color: #FFFFFF !important;
    font-weight: 800;
    border-left: 5px solid #FFC107 !important; 
    padding-left: 22px !important;             
    border-radius: 0 10px 10px 0;              
}
.sidebar-bottom { padding: 20px; border-top: 1px solid rgba(255, 255, 255, 0.2); display: flex; flex-direction: column; gap: 10px; }
.sidebar-bottom .logout-link { background-color: #dc3545; text-align: center; }
.sidebar-bottom .logout-link:hover { background-color: #c82333; }
</style>