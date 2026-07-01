<?php
session_start();
require 'koneksi.php';

// Cek autentikasi (Hanya Manager yang boleh akses)
if (!isset($_SESSION['id_pengguna']) || $_SESSION['peran'] !== 'manager') {
    die("Akses ditolak. Anda bukan Manager.");
}

$manager_id = $_SESSION['id_pengguna'];

// Ambil Nama Manager yang sedang login untuk di Tanda Tangan
$stmt_manager = $conn->query("SELECT nama_pengguna FROM pengguna WHERE id_pengguna = $manager_id");
$nama_manager = "";
if ($stmt_manager && $stmt_manager->num_rows > 0) {
    $nama_manager = $stmt_manager->fetch_assoc()['nama_pengguna'];
}

// Menangkap filter yang dikirim dari dashboard manager
$filter = "";
$filter_teks = [];
$nama_pekerja_singel = "Semua Staf Daman";

// 1. FILTER JENIS PERMINTAAN
if (isset($_GET['filter_jenis']) && $_GET['filter_jenis'] != "") {
    $jenis = $conn->real_escape_string($_GET['filter_jenis']);
    $filter .= " AND t.jenis_permintaan = '$jenis'";
    $filter_teks[] = "Jenis: " . ucfirst($jenis);
}

// 2. FILTER PEKERJA (Dikerjakan Oleh) - Berdasarkan ID
if (isset($_GET['filter_pekerja']) && $_GET['filter_pekerja'] != "") {
    $pekerja_id = intval($_GET['filter_pekerja']);
    $filter .= " AND t.diambil_oleh = '$pekerja_id'";
    
    // Ambil nama pekerja untuk ditampilkan di header laporan & TTD sebelah Kiri
    $res_p = $conn->query("SELECT nama_pengguna FROM pengguna WHERE id_pengguna = $pekerja_id");
    if($data_p = $res_p->fetch_assoc()){
        $nama_pekerja_singel = $data_p['nama_pengguna'];
        $filter_teks[] = "Pekerja: " . $data_p['nama_pengguna'];
    }
}

// 3. FILTER STATUS AKHIR
if (isset($_GET['filter_status']) && $_GET['filter_status'] != "") {
    $status_filter = $conn->real_escape_string($_GET['filter_status']);
    $filter .= " AND t.status = '$status_filter'";
    
    // Konversi nama status agar rapi di text header pemisah
    $status_label = str_replace('_', ' ', $status_filter);
    $filter_teks[] = "Status: " . ucwords($status_label);
}

// 4. FILTER RENTANG TANGGAL (Berdasarkan Waktu Selesai)
if (isset($_GET['start_date']) && $_GET['start_date'] != "" && isset($_GET['end_date']) && $_GET['end_date'] != "") {
    $start = $conn->real_escape_string($_GET['start_date']);
    $end = $conn->real_escape_string($_GET['end_date']);
    $filter .= " AND DATE(t.waktu_selesai) BETWEEN '$start' AND '$end'";
    $filter_teks[] = "Periode: " . date('d/m/Y', strtotime($start)) . " s/d " . date('d/m/Y', strtotime($end));
} elseif (isset($_GET['start_date']) && $_GET['start_date'] != "") {
    $start = $conn->real_escape_string($_GET['start_date']);
    $filter .= " AND DATE(t.waktu_selesai) >= '$start'";
    $filter_teks[] = "Mulai Tanggal: " . date('d/m/Y', strtotime($start));
} elseif (isset($_GET['end_date']) && $_GET['end_date'] != "") {
    $end = $conn->real_escape_string($_GET['end_date']);
    $filter .= " AND DATE(t.waktu_selesai) <= '$end'";
    $filter_teks[] = "Sampai Tanggal: " . date('d/m/Y', strtotime($end));
}

// 5. PENCARIAN UMUM
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
    $filter_teks[] = "Pencarian: '" . $search . "'";
}

// Menyusun string teks filter untuk judul halaman cetak
$teks_keterangan_filter = empty($filter_teks) ? "Semua Data Riwayat" : implode(" | ", $filter_teks);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan_Seluruh_Riwayat_Pekerjaan_Daman_<?php echo date('Ymd_His'); ?></title>
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
            <h2>LAPORAN SELURUH RIWAYAT PEKERJAAN DAMAN</h2>
            <h3>Otorisasi / Peran: MANAGER</h3>
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
                <th>Nama ODP</th>
                <th>Valins ID</th>
                <th>Koordinat (Lat, Long)</th>
                <th>Panel / Port</th>
                <th>Req Line</th>
                <th>Real. Line</th>
                <th>Waktu Req</th>
                <th>Waktu Selesai</th>
                <th>Pekerja</th>
                <th>Status Akhir</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // QUERY UTAMA VERSI MANAGER
            $sql = "SELECT t.*, u.nama_pengguna AS daman_name,
                    tech.nama_pemohon, tv.id_valins,
                    GROUP_CONCAT(CONCAT('P:', tp.panel, ' / Pt:', tp.port) SEPARATOR ' | ') AS port_info
                    FROM tiket t 
                    LEFT JOIN pengguna u ON t.diambil_oleh = u.id_pengguna 
                    LEFT JOIN port_tiket tp ON t.id_tiket = tp.id_tiket
                    LEFT JOIN teknisi tech ON t.id_teknisi = tech.id_teknisi
                    LEFT JOIN validasi_tiket tv ON t.id_tiket = tv.id_tiket
                    WHERE t.status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') $filter 
                    GROUP BY t.id_tiket
                    $search_filter
                    ORDER BY t.waktu_selesai DESC, t.id_tiket DESC";
            
            $result = $conn->query($sql);
            $no = 1;

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    
                    // Terjemahan status teks agar rapi saat dicetak
                    $status_cetak = '';
                    if ($row['status'] == 'selesai') $status_cetak = 'Selesai';
                    elseif ($row['status'] == 'valins_ulang') $status_cetak = 'Valins Ulang';
                    elseif ($row['status'] == 'belum_golive') $status_cetak = 'Belum Go Live';
                    elseif ($row['status'] == 'sudah_golive') $status_cetak = 'Sudah Go Live';
                    elseif ($row['status'] == 'full') $status_cetak = 'ODP Full';
                    else $status_cetak = ucfirst($row['status']);

                    $port_info_display = (!empty($row['port_info'])) ? $row['port_info'] : "-";

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
                    echo "<td>".htmlspecialchars($row['daman_name'] ? $row['daman_name'] : '-')."</td>";
                    echo "<td><strong>".htmlspecialchars($status_cetak)."</strong></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='14' style='text-align:center;'>Tidak ada data laporan yang sesuai filter.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div class="signature-container">
        <div class="signature-box right">
            <div class="signature-content">
                <?php
                $bulan = array(
                    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                );
                $tanggal_cetak = date('d') . ' ' . $bulan[(int)date('m')] . ' ' . date('Y');
                ?>
                <div class="signature-date">Palembang, <?php echo $tanggal_cetak; ?></div>
                <div class="signature-name"><?php echo strtoupper(htmlspecialchars($nama_manager)); ?></div>
                <div class="signature-title">Mgr Asset Inventory & Data Management<br>Reg TIF Sumbagsel</div>
            </div>
        </div>
    </div>

    <script>
        // Memaksa browser mengubah nama dokumen tepat sebelum jendela print muncul
        window.addEventListener('beforeprint', function () {
            document.title = "Laporan_Seluruh_Riwayat_Pekerjaan_Daman_<?php echo date('Ymd_His'); ?>";
        });

        function cetakPDF() {
            window.print();
        }
    </script>
</body>
</html>