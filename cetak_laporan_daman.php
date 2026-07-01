<?php
session_start();
require 'koneksi.php';

// Cek autentikasi (Hanya Daman yang boleh akses)
if (!isset($_SESSION['id_pengguna']) || $_SESSION['peran'] !== 'daman') {
    die("Akses ditolak. Anda bukan teknisi (Daman).");
}

$user_id = $_SESSION['id_pengguna'];
$filter = "";
$filter_teks = [];

// 1. FILTER JENIS PERMINTAAN
if (isset($_GET['filter_jenis']) && $_GET['filter_jenis'] != "") {
    $jenis = $conn->real_escape_string($_GET['filter_jenis']);
    $filter .= " AND t.jenis_permintaan = '$jenis'";
    $filter_teks[] = "Jenis: " . ucfirst($jenis);
}

// 2. FILTER STATUS AKHIR
if (isset($_GET['filter_status']) && $_GET['filter_status'] != "") {
    $status = $conn->real_escape_string($_GET['filter_status']);
    $filter .= " AND t.status = '$status'";
    $filter_teks[] = "Status: " . ucfirst(str_replace('_', ' ', $status));
}

// 3. FILTER RENTANG TANGGAL (WAKTU SELESAI)
if (isset($_GET['start_date']) && $_GET['start_date'] != "" && isset($_GET['end_date']) && $_GET['end_date'] != "") {
    $start = $conn->real_escape_string($_GET['start_date']);
    $end = $conn->real_escape_string($_GET['end_date']);
    $filter .= " AND DATE(t.waktu_selesai) BETWEEN '$start' AND '$end'";
    $filter_teks[] = "Periode Selesai: " . date('d/m/Y', strtotime($start)) . " s.d " . date('d/m/Y', strtotime($end));
} elseif (isset($_GET['start_date']) && $_GET['start_date'] != "") {
    $start = $conn->real_escape_string($_GET['start_date']);
    $filter .= " AND DATE(t.waktu_selesai) >= '$start'";
    $filter_teks[] = "Selesai Mulai: " . date('d/m/Y', strtotime($start));
} elseif (isset($_GET['end_date']) && $_GET['end_date'] != "") {
    $end = $conn->real_escape_string($_GET['end_date']);
    $filter .= " AND DATE(t.waktu_selesai) <= '$end'";
    $filter_teks[] = "Selesai Sampai: " . date('d/m/Y', strtotime($end));
}

// 4. PENCARIAN BEBAS (Termasuk Koordinat Lat Long, Tiket, ODP, dll)
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
                            OR t.realisasi_line LIKE '%$search%'
                            OR REPLACE(t.status, '_', ' ') LIKE '%$search%'
                            OR port_info LIKE '%$search%')";
    $filter_teks[] = "Pencarian: '$search'";
}

$teks_keterangan_filter = empty($filter_teks) ? "Semua Pekerjaan Anda" : implode(" | ", $filter_teks);

// Query utama (Ditambahkan DISTINCT agar tampilan Port rapi tidak duplikat)
$sql = "SELECT t.*, u.nama_pengguna AS daman_name, 
            tech.nama_pemohon, tv.id_valins,
            GROUP_CONCAT(DISTINCT CONCAT('P:', tp.panel, ' / Pt:', tp.port) SEPARATOR ' | ') AS port_info
            FROM tiket t 
            LEFT JOIN pengguna u ON t.diambil_oleh = u.id_pengguna 
            LEFT JOIN port_tiket tp ON t.id_tiket = tp.id_tiket
            LEFT JOIN teknisi tech ON t.id_teknisi = tech.id_teknisi
            LEFT JOIN validasi_tiket tv ON t.id_tiket = tv.id_tiket
            WHERE t.status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') 
            AND t.diambil_oleh = $user_id $filter 
            GROUP BY t.id_tiket
            $search_filter
            ORDER BY t.waktu_selesai DESC, t.id_tiket DESC";
        
$result = $conn->query($sql);

// Ambil Nama Daman untuk Judul
$stmt_user = $conn->query("SELECT nama_pengguna FROM pengguna WHERE id_pengguna = $user_id");
$nama_daman = $stmt_user->fetch_assoc()['nama_pengguna'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pekerjaan - <?php echo htmlspecialchars($nama_daman); ?></title>
    <link rel="stylesheet" href="css/cetak_laporan_daman.css">
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px;">
        <button class="btn btn-print" onclick="cetakPDF()">Print / Save as PDF</button>
        <button class="btn btn-close" onclick="window.close()">Tutup</button>
    </div>

    <div class="header">
        <img src="css/assets/infranexia.png" alt="Logo Perusahaan" class="header-logo">
        
        <div class="header-text">
            <h2>LAPORAN RIWAYAT PEKERJAAN DAMAN</h2>
            <h3>Dikerjakan Oleh: <?php echo strtoupper(htmlspecialchars($nama_daman)); ?></h3>
            <p>Filter Kriteria: <?php echo htmlspecialchars($teks_keterangan_filter); ?></p>
            <p>Dicetak pada: <?php echo date('d M Y H:i:s'); ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th>No Tiket</th>
                <th>Jenis</th>
                <th>Pemohon</th>
                <th>ODP</th>
                <th>Valins ID</th> 
                <th>Lat & Long</th>
                <th>Panel/Port</th>
                <th>Req Line</th>
                <th>Realisasi (Line)</th>
                <th>Waktu Request</th>
                <th>Waktu Selesai</th>
                <th>Status Akhir</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                $no = 1;
                while ($row = $result->fetch_assoc()) {
                    
                    $status_cetak = '';
                    if ($row['status'] == 'valins_ulang') $status_cetak = 'Valins Ulang';
                    elseif ($row['status'] == 'selesai') $status_cetak = 'Selesai';
                    elseif ($row['status'] == 'belum_golive') $status_cetak = 'Belum Go Live';
                    elseif ($row['status'] == 'full') $status_cetak = 'ODP Full';
                    elseif ($row['status'] == 'sudah_golive') $status_cetak = 'Sudah Go Live'; 

                    $port_info_display = ($row['port_info']) ? $row['port_info'] : "-";

                    echo "<tr>";
                    echo "<td style='text-align:center;'>".$no++."</td>";
                    echo "<td>".htmlspecialchars($row['no_tiket'])."</td>";
                    echo "<td>".htmlspecialchars(ucfirst($row['jenis_permintaan']))."</td>";
                    echo "<td>".htmlspecialchars($row['nama_pemohon'])."</td>";
                    echo "<td>".htmlspecialchars($row['nama_odp'])."</td>";
                    echo "<td>".htmlspecialchars($row['id_valins'] ? $row['id_valins'] : '-')."</td>";
                    echo "<td>".htmlspecialchars($row['latitude'] ? $row['latitude'].", ".$row['longitude'] : "-")."</td>";
                    echo "<td>".htmlspecialchars($port_info_display)."</td>";
                    echo "<td style='text-align:center;'>".htmlspecialchars($row['permintaan_line'] ?? '-')."</td>";
                    echo "<td style='text-align:center;'>".htmlspecialchars($row['realisasi_line'] ?? '-')."</td>";
                    echo "<td>".htmlspecialchars($row['waktu_permintaan'])."</td>";
                    echo "<td>".htmlspecialchars($row['waktu_selesai'])."</td>";
                    echo "<td><strong>".htmlspecialchars($status_cetak)."</strong></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='13' style='text-align:center;'>Tidak ada data laporan pekerjaan Anda.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div class="signature-container">
        <div class="signature-box left">
            <div class="signature-content">
                <div class="signature-date">Mengetahui,</div>
                <div class="signature-name"><?php echo strtoupper(htmlspecialchars($nama_daman)); ?></div>
                <div class="signature-title">Staf Daman</div>
            </div>
        </div>
        <div class="signature-box right">
            <div class="signature-content">
                <?php
                // Format tanggal Indonesia untuk Tanda Tangan
                $bulan = array(
                    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                );
                $tanggal_cetak = date('d') . ' ' . $bulan[(int)date('m')] . ' ' . date('Y');
                ?>
                <div class="signature-date">Palembang, <?php echo $tanggal_cetak; ?></div>
                
                <div class="signature-name">Fiko Ramadhan</div>
                <div class="signature-title">Mgr Asset Inventory & Data
                Management<br>Reg TIF Sumbagsel</div>
            </div>
        </div>
    </div>

    <script>
    // Trik paling ampuh: Mencegat browser TEPAT sebelum jendela print dirender
    window.addEventListener('beforeprint', function () {
        // Memaksa title halaman berubah menjadi nama file yang diinginkan
        document.title = "Laporan_Riwayat_Pekerjaan_Daman_<?php echo date('Ymd_His'); ?>";
    });

    // Fungsi jika user mengklik tombol "Print / Save as PDF" di halaman
    function cetakPDF() {
        window.print();
    }
</script>
</body>
</html>