<?php
session_start();
require 'koneksi.php';

// Cek apakah user sudah login dan rolenya adalah manager[cite: 11]
if (!isset($_SESSION['id_pengguna']) || $_SESSION['peran'] !== 'manager') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Pekerjaan Tim Daman - Infranexia</title>
    <link rel="stylesheet" href="css/style_riwayat_pekerjaan.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        .btn-excel {
            background-color: #107c41; /* Warna hijau khas Excel */
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        .btn-excel:hover {
            background-color: #0c5e31;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-container">

        <div class="dashboard-header">
            <h2 class="greeting">RIWAYAT PEKERJAAN TIM DAMAN</h2>
            <img src="css/assets/infranexia.png" alt="Infranexia Logo" class="header-logo">
        </div>

        <form id="filterFormManager" method="GET" action="" class="filter-box">
            
            <div class="filter-group">
                <label>Jenis Permintaan:</label>
                <select name="filter_jenis">
                    <option value="">Semua</option>
                    <option value="pemunculan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pemunculan') echo 'selected'; ?>>Pemunculan</option>
                    <option value="pengosongan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pengosongan') echo 'selected'; ?>>Pengosongan</option>
                </select>
            </div>

            <!-- Tambahan Filter Status -->
            <div class="filter-group">
                <label>Status Akhir:</label>
                <select name="filter_status">
                    <option value="">Semua Status</option>
                    <option value="selesai" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='selesai') echo 'selected'; ?>>Selesai</option>
                    <option value="valins_ulang" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='valins_ulang') echo 'selected'; ?>>Valins Ulang</option>
                    <option value="belum_golive" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='belum_golive') echo 'selected'; ?>>Belum Go Live</option>
                    <option value="sudah_golive" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='sudah_golive') echo 'selected'; ?>>Sudah Go Live</option>
                    <option value="full" <?php if(isset($_GET['filter_status']) && $_GET['filter_status']=='full') echo 'selected'; ?>>ODP Full</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Dikerjakan Oleh:</label>
                <?php $list_pekerja = $conn->query("SELECT id_pengguna, nama_pengguna FROM pengguna WHERE peran = 'daman' ORDER BY nama_pengguna ASC"); ?>
                <select name="filter_pekerja">
                    <option value="">Semua Pekerja</option>
                    <?php while($p = $list_pekerja->fetch_assoc()): ?>
                        <option value="<?php echo $p['id_pengguna']; ?>" <?php echo (isset($_GET['filter_pekerja']) && $_GET['filter_pekerja'] == $p['id_pengguna']) ? 'selected' : ''; ?>>
                            <?php echo $p['nama_pengguna']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Rentang Tanggal (Selesai):</label>
                <div class="date-range-group">
                    <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                    <span>s/d</span>
                    <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                </div>
            </div>

            <div class="filter-group">
                <label>Pencarian Umum:</label>
                <input type="text" name="search" placeholder="Cari tiket, ODP, port..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-cari">
                    <span class="material-symbols-outlined">search</span> Cari
                </button>
                
                <a href="riwayat_pekerjaan.php" class="btn-reset">
                    <span class="material-symbols-outlined">sync</span> Reset
                </a>
                
                <button type="button" onclick="cetakLaporan()" class="btn-cetak">
                    <span class="material-symbols-outlined">picture_as_pdf</span> PDF
                </button>

                <!-- Tombol Cetak Excel -->
                <button type="button" onclick="cetakExcel()" class="btn-excel">
                    <span class="material-symbols-outlined">table_view</span> Excel
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="cursor: pointer; user-select: none;" onclick="sortTabelTiket(event)" title="Klik untuk mengurutkan Ascending/Descending">
                            No Tiket <span id="ikonSort" style="margin-left: 5px; color: #CC1F29;">&#8597;</span>
                        </th>
                        <th>Waktu Request</th>
                        <th>Pemohon</th>
                        <th>Jenis</th>
                        <th>Nama ODP</th>
                        <th>Valins ID</th>
                        <th>Lat & Long</th>
                        <th>Panel / Port</th>
                        <th>Req Line</th>
                        <th>Realisasi</th>
                        <th>Dikerjakan Oleh</th>
                        <th>Waktu Selesai</th>
                        <th>Status Akhir</th>
                        <th>Bukti Valins</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $filter = "";
                    
                    if (isset($_GET['filter_jenis']) && $_GET['filter_jenis'] != "") {
                        $jenis = $conn->real_escape_string($_GET['filter_jenis']);
                        $filter .= " AND t.jenis_permintaan = '$jenis'";
                    }

                    if (isset($_GET['filter_pekerja']) && $_GET['filter_pekerja'] != "") {
                        $pekerja_id = intval($_GET['filter_pekerja']);
                        $filter .= " AND t.diambil_oleh = '$pekerja_id'";
                    }

                    // Tambahan logika filter_status
                    if (isset($_GET['filter_status']) && $_GET['filter_status'] != "") {
                        $status_filter = $conn->real_escape_string($_GET['filter_status']);
                        $filter .= " AND t.status = '$status_filter'";
                    }

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
                                            OR t.permintaan_line LIKE '%$search%'
                                            OR t.realisasi_line LIKE '%$search%'
                                            OR REPLACE(t.status, '_', ' ') LIKE '%$search%'
                                            OR port_info LIKE '%$search%')";
                    }

                    $sql = "SELECT t.*, u.nama_pengguna AS daman_name,
                            tech.nama_pemohon, tv.id_valins, 
                            GROUP_CONCAT(DISTINCT tvi.lokasi_file SEPARATOR ',') AS daftar_gambar,
                            GROUP_CONCAT(DISTINCT CONCAT('P:', tp.panel, ' / Pt:', tp.port) SEPARATOR ' | ') AS port_info
                            FROM tiket t 
                            LEFT JOIN pengguna u ON t.diambil_oleh = u.id_pengguna 
                            LEFT JOIN port_tiket tp ON t.id_tiket = tp.id_tiket
                            LEFT JOIN teknisi tech ON t.id_teknisi = tech.id_teknisi
                            LEFT JOIN validasi_tiket tv ON t.id_tiket = tv.id_tiket
                            LEFT JOIN gambar_validasi_tiket tvi ON t.id_tiket = tvi.id_tiket
                            WHERE t.status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') $filter 
                            GROUP BY t.id_tiket
                            $search_filter
                            ORDER BY t.waktu_selesai DESC, t.id_tiket DESC";
                    $result = $conn->query($sql);
                    
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><b><?php echo $row['no_tiket']; ?></b></td>
                        <td><?php echo $row['waktu_permintaan']; ?></td>
                        <td><?php echo $row['nama_pemohon']; ?></td>
                        <td><?php echo ucfirst($row['jenis_permintaan']); ?></td>
                        <td><?php echo $row['nama_odp']; ?></td>
                        <td><?php echo $row['id_valins'] ? $row['id_valins'] : '-'; ?></td>
                        <td><?php echo $row['latitude'] ? $row['latitude'] . ', ' . $row['longitude'] : '-'; ?></td>
                        <td><?php echo (!empty($row['port_info'])) ? $row['port_info'] : '-'; ?></td>
                        <td style="text-align: center;"><?php echo $row['permintaan_line'] ? $row['permintaan_line'] : '-'; ?></td>
                        <td style="text-align: center;"><?php echo $row['realisasi_line'] ? $row['realisasi_line'] : '-'; ?></td>
                        <td><b><?php echo $row['daman_name'] ? $row['daman_name'] : 'Tidak Diketahui'; ?></b></td>
                        <td><?php echo $row['waktu_selesai'] ? $row['waktu_selesai'] : '-'; ?></td>
                        <td>
                            <?php 
                            if ($row['status'] == 'valins_ulang') {
                                echo '<span class="status-badge status-valins">Valins Ulang</span>';
                            } elseif ($row['status'] == 'selesai') {
                                echo '<span class="status-badge status-selesai">Selesai</span>';
                            } elseif ($row['status'] == 'belum_golive') {
                                echo '<span class="status-badge status-belum-golive">Belum Go Live</span>';
                            } elseif ($row['status'] == 'sudah_golive') {
                                echo '<span class="status-badge status-sudah-golive">Sudah Go Live</span>'; 
                            } elseif ($row['status'] == 'full') {
                                echo '<span class="status-badge status-full">ODP Full</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($row['status'] == 'valins_ulang' && !empty($row['daftar_gambar'])): ?>
                                <button type="button" class="btn-lihat-gambar" onclick="bukaModal('<?php echo $row['daftar_gambar']; ?>')">Lihat Gambar</button>
                            <?php else: ?> - <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="14" style="text-align:center;">Belum ada riwayat pekerjaan yang sesuai.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div> 
    
    <div id="imageModal" class="modal" onclick="tutupModal()">
        <span class="close-btn" onclick="tutupModal()">&times;</span>
        <span class="nav-btn prev" onclick="geserGambar(-1, event)">&#10094;</span>
        <img class="modal-content" id="modalImage" onclick="event.stopPropagation()"> 
        <span class="nav-btn next" onclick="geserGambar(1, event)">&#10095;</span>
        <div id="imageCounter"></div>
    </div>

    <script>
        let listGambarModal = [];
        let indexGambarAktif = 0;

        document.getElementById("filterFormManager")?.addEventListener("submit", function (e) {
            let inputs = this.querySelectorAll("input, select");
            inputs.forEach(input => { if (!input.value) input.removeAttribute("name"); });
        });

        function bukaModal(stringGambar) {
            listGambarModal = stringGambar.split(',');
            indexGambarAktif = 0;
            var modal = document.getElementById("imageModal");
            modal.style.display = "flex"; 
            tampilkanGambar();
        }

        function tampilkanGambar() {
            var modalImg = document.getElementById("modalImage");
            var counter = document.getElementById("imageCounter");
            modalImg.src = listGambarModal[indexGambarAktif];
            
            if(listGambarModal.length > 1) {
                counter.innerText = "Gambar " + (indexGambarAktif + 1) + " dari " + listGambarModal.length;
                document.querySelector('.prev').style.display = 'block';
                document.querySelector('.next').style.display = 'block';
            } else {
                counter.innerText = "";
                document.querySelector('.prev').style.display = 'none';
                document.querySelector('.next').style.display = 'none';
            }
        }

        function geserGambar(n, event) {
            event.stopPropagation();
            indexGambarAktif += n;
            if (indexGambarAktif >= listGambarModal.length) indexGambarAktif = 0;
            else if (indexGambarAktif < 0) indexGambarAktif = listGambarModal.length - 1;
            tampilkanGambar();
        }

        function tutupModal() {
            document.getElementById("imageModal").style.display = "none"; 
        }

        function cetakLaporan() {
            const urlParams = new URLSearchParams(window.location.search);
            window.open("cetak_laporan.php?" + urlParams.toString(), '_blank');
        }

        // Fungsi baru untuk eksekusi file Excel
        function cetakExcel() {
            const urlParams = new URLSearchParams(window.location.search);
            window.location.href = "excel_laporan.php?" + urlParams.toString();
        }

        // ==========================================
        // 1. SCRIPT RESIZE LEBAR KOLOM TABEL MANUAL
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('.table-custom');
            if(table) {
                const thElements = table.querySelectorAll('th');
                thElements.forEach(function(th) {
                    const resizer = document.createElement('div');
                    resizer.classList.add('resizer');
                    th.appendChild(resizer);
                    buatKolomBisaDigeser(th, resizer);
                });
            }
        });

        function buatKolomBisaDigeser(th, resizer) {
            let x = 0; let w = 0;
            const saatMouseDitekan = function(e) {
                x = e.clientX;
                w = parseInt(window.getComputedStyle(th).width, 10);
                document.addEventListener('mousemove', saatMouseBergerak);
                document.addEventListener('mouseup', saatMouseDilepas);
                resizer.classList.add('resizing');
            };
            const saatMouseBergerak = function(e) {
                const dx = e.clientX - x;
                th.style.width = `${w + dx}px`;
                th.style.minWidth = `${w + dx}px`;
            };
            const saatMouseDilepas = function() {
                resizer.classList.remove('resizing');
                document.removeEventListener('mousemove', saatMouseBergerak);
                document.removeEventListener('mouseup', saatMouseDilepas);
            };
            resizer.addEventListener('mousedown', saatMouseDitekan);
        }

        // ==========================================
        // 2. SCRIPT SORTING NO TIKET (ASC / DESC)
        // ==========================================
        let urutNaik = true; 

        function sortTabelTiket(event) {
            if (event.target.classList.contains('resizer')) return;

            const tbody = document.querySelector('.table-custom tbody');
            const baris = Array.from(tbody.querySelectorAll('tr'));

            if (baris.length === 1 && baris[0].cells.length <= 2) return;

            baris.sort((a, b) => {
                let nilaiA = a.cells[0].innerText.trim();
                let nilaiB = b.cells[0].innerText.trim();

                if (urutNaik) {
                    return nilaiA.localeCompare(nilaiB, undefined, {numeric: true});
                } else {
                    return nilaiB.localeCompare(nilaiA, undefined, {numeric: true});
                }
            });

            baris.forEach(b => tbody.appendChild(b));
            document.getElementById('ikonSort').innerHTML = urutNaik ? '&#9650;' : '&#9660;';
            urutNaik = !urutNaik;
        }
    </script>
</body>
</html>