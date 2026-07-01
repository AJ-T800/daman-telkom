<?php
session_start();
require 'koneksi.php';

// Cek sesi
if (!isset($_SESSION['id_pengguna']) || $_SESSION['peran'] !== 'daman') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id_pengguna'];
$nama_karyawan = isset($_SESSION['nama_pengguna']) ? $_SESSION['nama_pengguna'] : 'Karyawan';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Pekerjaan Saya - Infranexia</title>
    <link class="no-print" rel="stylesheet" href="css/style_riwayat_pekerjaan_karyawan.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        .btn-excel {
            background-color: #1D6F42; color: white; border: none; padding: 10px 15px;
            border-radius: 4px; font-weight: 600; cursor: pointer; display: inline-flex;
            align-items: center; gap: 5px; transition: background-color 0.2s; font-size: 14px;
        }
        .btn-excel:hover { background-color: #144d2e; }
        
        /* PENGATURAN SCROLL HORIZONTAL FILTER SAAT DI-ZOOM */
        .filter-box {
            display: flex; 
            flex-wrap: nowrap; /* Paksa elemen tetap sebaris (tidak turun ke bawah) */
            overflow-x: auto;  /* Munculkan scroll bar jika melebihi lebar layar */
            gap: 15px; 
            align-items: flex-end;
            padding-bottom: 15px; /* Ruang nafas untuk scrollbar */
        }
        
        /* Mencegah isi filter menyusut/gepeng saat layar sempit */
        .filter-group, .filter-actions {
            flex-shrink: 0; 
        }

        /* Merapikan tampilan scrollbar webkit agar estetik */
        .filter-box::-webkit-scrollbar { height: 8px; }
        .filter-box::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .filter-box::-webkit-scrollbar-thumb { background: #CC1F29; border-radius: 4px; }
        .filter-box::-webkit-scrollbar-thumb:hover { background: #a81720; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-container">
        <div class="dashboard-header">
            <h2 class="greeting">RIWAYAT PEKERJAAN <?php echo strtoupper($nama_karyawan); ?></h2>
            <img src="css/assets/infranexia.png" alt="Infranexia Logo" class="header-logo">
        </div>

        <form id="filterFormDaman" method="GET" action="" class="filter-box">
            <div class="filter-group">
                <label>Jenis:</label>
                <select name="filter_jenis">
                    <option value="">Semua</option>
                    <option value="pemunculan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pemunculan') echo 'selected'; ?>>Pemunculan</option>
                    <option value="pengosongan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pengosongan') echo 'selected'; ?>>Pengosongan</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Status Akhir:</label>
                <select name="filter_status">
                    <option value="">Semua</option>
                    <option value="selesai" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='selesai') echo 'selected'; ?>>Selesai</option>
                    <option value="valins_ulang" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='valins_ulang') echo 'selected'; ?>>Valins Ulang</option>
                    <option value="belum_golive" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='belum_golive') echo 'selected'; ?>>Belum Go Live</option>
                    <option value="sudah_golive" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='sudah_golive') echo 'selected'; ?>>Sudah Go Live</option>
                    <option value="full" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='full') echo 'selected'; ?>>ODP Full</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Mulai Tgl (Selesai):</label>
                <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
            </div>

            <div class="filter-group">
                <label>Sampai Tgl (Selesai):</label>
                <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
            </div>

            <div class="filter-group">
                <label>Pencarian Bebas:</label>
                <input type="text" name="search" placeholder="Cari No Tiket, Lat Long, ODP..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
            </div>

            <div class="filter-actions" style="margin-top: 0;">
                <button type="submit" class="btn-cari"><span class="material-symbols-outlined">search</span> Cari</button>
                <a href="riwayat_pekerjaan_karyawan.php" class="btn-reset"><span class="material-symbols-outlined">sync</span> Reset</a>
                <button type="button" onclick="cetakLaporanDaman()" class="btn-cetak"><span class="material-symbols-outlined">print</span> PDF</button>
                <button type="button" onclick="eksporExcelDaman()" class="btn-excel"><span class="material-symbols-outlined">description</span> Excel</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table-custom">
            <thead>
                <tr>
                    <th style="cursor: pointer; user-select: none;" onclick="sortTabelTiket(event)">No Tiket <span id="ikonSort" style="margin-left: 5px; color: #CC1F29;">&#8597;</span></th>
                    <th>Jenis</th>
                    <th>Pemohon</th>
                    <th>Nama ODP</th>
                    <th>Valins ID</th>
                    <th>Lat & Long</th>
                    <th>Panel / Port</th>
                    <th>Req Line</th>
                    <th>Realisasi</th>
                    <th>Waktu Request</th>
                    <th>Waktu Selesai</th>
                    <th>Status</th>
                    <th>Bukti</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // MENGUMPULKAN SEMUA PARAMETER FILTER
                $filter = "";
                
                if (isset($_GET['filter_jenis']) && $_GET['filter_jenis'] != "") {
                    $jenis = $conn->real_escape_string($_GET['filter_jenis']);
                    $filter .= " AND t.jenis_permintaan = '$jenis'";
                }
                
                if (isset($_GET['filter_status']) && $_GET['filter_status'] != "") {
                    $status = $conn->real_escape_string($_GET['filter_status']);
                    $filter .= " AND t.status = '$status'";
                }

                // FILTER RENTANG TANGGAL (WAKTU SELESAI)
                if (isset($_GET['start_date']) && $_GET['start_date'] != "" && isset($_GET['end_date']) && $_GET['end_date'] != "") {
                    $start = $conn->real_escape_string($_GET['start_date']);
                    $end = $conn->real_escape_string($_GET['end_date']);
                    $filter .= " AND DATE(t.waktu_selesai) BETWEEN '$start' AND '$end'";
                } elseif (isset($_GET['start_date']) && $_GET['start_date'] != "") {
                    $start = $conn->real_escape_string($_GET['start_date']);
                    $filter .= " AND DATE(t.waktu_selesai) >= '$start'";
                } elseif (isset($_GET['end_date']) && $_GET['end_date'] != "") {
                    $end = $conn->real_escape_string($_GET['end_date']);
                    $filter .= " AND DATE(t.waktu_selesai) <= '$end'";
                }

                // PENCARIAN DIPERLUAS
                $search_filter = "";
                if (isset($_GET['search']) && $_GET['search'] != "") {
                    $search = $conn->real_escape_string($_GET['search']);
                    $search_filter = " HAVING (t.no_tiket LIKE '%$search%' 
                                    OR t.nama_odp LIKE '%$search%' 
                                    OR tech.nama_pemohon LIKE '%$search%' 
                                    OR tv.id_valins LIKE '%$search%' 
                                    OR CONCAT(t.latitude, ', ', t.longitude) LIKE '%$search%' 
                                    OR t.waktu_permintaan LIKE '%$search%' 
                                    OR t.waktu_selesai LIKE '%$search%' 
                                    OR REPLACE(t.status, '_', ' ') LIKE '%$search%' 
                                    OR port_info LIKE '%$search%')";
                }

                $sql = "SELECT t.*, tech.nama_pemohon, tv.id_valins, 
                        GROUP_CONCAT(DISTINCT tvi.lokasi_file SEPARATOR ',') AS daftar_gambar,
                        GROUP_CONCAT(DISTINCT CONCAT('P:', tp.panel, ' / Pt:', tp.port) SEPARATOR ' | ') AS port_info
                        FROM tiket t 
                        LEFT JOIN port_tiket tp ON t.id_tiket = tp.id_tiket
                        LEFT JOIN teknisi tech ON t.id_teknisi = tech.id_teknisi
                        LEFT JOIN validasi_tiket tv ON t.id_tiket = tv.id_tiket
                        LEFT JOIN gambar_validasi_tiket tvi ON t.id_tiket = tvi.id_tiket
                        WHERE t.status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') 
                        AND t.diambil_oleh = $user_id $filter 
                        GROUP BY t.id_tiket
                        $search_filter
                        ORDER BY t.waktu_selesai DESC";
                
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><b><?php echo $row['no_tiket']; ?></b></td>
                    <td><?php echo ucfirst($row['jenis_permintaan']); ?></td>
                    <td><?php echo $row['nama_pemohon']; ?></td>
                    <td><?php echo $row['nama_odp']; ?></td>
                    <td><?php echo $row['id_valins'] ?: '-'; ?></td>
                    <td><?php echo $row['latitude'] ? $row['latitude'].", ".$row['longitude'] : "-"; ?></td>
                    <td><?php echo (!empty($row['port_info'])) ? $row['port_info'] : '-'; ?></td>
                    <td style="text-align: center;"><?php echo $row['permintaan_line'] ?? '-'; ?></td>
                    <td style="text-align: center;"><?php echo $row['realisasi_line'] ?? '-'; ?></td>
                    <td><?php echo $row['waktu_permintaan']; ?></td>
                    <td><?php echo $row['waktu_selesai']; ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></td>
                    <td>
                        <?php if ($row['status'] == 'valins_ulang' && !empty($row['daftar_gambar'])): ?>
                            <button type="button" class="btn-ambil" onclick="bukaModal('<?php echo $row['daftar_gambar']; ?>')">Lihat</button>
                        <?php else: ?> - <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="13" style="text-align:center;">Tidak ada riwayat pekerjaan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div> 

    <div id="imageModal" class="modal" onclick="tutupModal()">
        <span class="close-btn" onclick="tutupModal()">&times;</span>
        <span class="nav-btn prev" onclick="gantiGambar(-1, event)">&#10094;</span>
        <img class="modal-content" id="img01" onclick="event.stopPropagation()">
        <span class="nav-btn next" onclick="gantiGambar(1, event)">&#10095;</span>
    </div>

    <script>
        let daftarGambarAktif = [];
        let indexGambarSaatIni = 0;

        function bukaModal(stringGambar) {
            daftarGambarAktif = stringGambar.split(',');
            indexGambarSaatIni = 0;
            document.getElementById("imageModal").style.display = "flex";
            document.getElementById("img01").src = daftarGambarAktif[indexGambarSaatIni];
            document.querySelector(".nav-btn.prev").style.display = daftarGambarAktif.length > 1 ? "block" : "none";
            document.querySelector(".nav-btn.next").style.display = daftarGambarAktif.length > 1 ? "block" : "none";
        }

        function gantiGambar(arah, event) {
            event.stopPropagation(); 
            indexGambarSaatIni += arah;
            if (indexGambarSaatIni >= daftarGambarAktif.length) indexGambarSaatIni = 0;
            if (indexGambarSaatIni < 0) indexGambarSaatIni = daftarGambarAktif.length - 1;
            document.getElementById("img01").src = daftarGambarAktif[indexGambarSaatIni];
        }

        function tutupModal() { document.getElementById("imageModal").style.display = "none"; }

        function cetakLaporanDaman() {
            const urlParams = new URLSearchParams(window.location.search);
            window.open("cetak_laporan_daman.php?" + urlParams.toString(), '_blank');
        }

        function eksporExcelDaman() {
            const urlParams = new URLSearchParams(window.location.search);
            window.open("excel_laporan_daman.php?" + urlParams.toString(), '_blank');
        }

        // Script resize kolom 
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.table-custom th').forEach(function(th) {
                const resizer = document.createElement('div');
                resizer.classList.add('resizer'); th.appendChild(resizer);
                let x = 0; let w = 0;
                resizer.addEventListener('mousedown', function(e) {
                    x = e.clientX; w = parseInt(window.getComputedStyle(th).width, 10);
                    document.addEventListener('mousemove', drag); document.addEventListener('mouseup', drop);
                    resizer.classList.add('resizing');
                });
                function drag(e) { th.style.width = `${w + (e.clientX - x)}px`; th.style.minWidth = `${w + (e.clientX - x)}px`; }
                function drop() { resizer.classList.remove('resizing'); document.removeEventListener('mousemove', drag); document.removeEventListener('mouseup', drop); }
            });
        });

        // Script Sort tabel
        let urutNaik = true; 
        function sortTabelTiket(event) {
            if (event.target.classList.contains('resizer')) return;
            const tbody = document.querySelector('.table-custom tbody');
            const baris = Array.from(tbody.querySelectorAll('tr'));
            if (baris.length === 1 && baris[0].cells.length === 1) return;
            baris.sort((a, b) => {
                let nilaiA = a.cells[0].innerText.trim(); let nilaiB = b.cells[0].innerText.trim();
                return urutNaik ? nilaiA.localeCompare(nilaiB, undefined, {numeric: true}) : nilaiB.localeCompare(nilaiA, undefined, {numeric: true});
            });
            baris.forEach(b => tbody.appendChild(b));
            document.getElementById('ikonSort').innerHTML = urutNaik ? '&#9650;' : '&#9660;';
            urutNaik = !urutNaik;
        }
    </script>
</body>
</html>