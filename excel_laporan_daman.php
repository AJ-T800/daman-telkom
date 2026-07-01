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
date_default_timezone_set('Asia/Jakarta');

// Header untuk memaksa browser mendownload sebagai file Excel (.xls)
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Riwayat_Pekerjaan_Daman_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// MENGAMBIL PARAMETER FILTER DARI URL
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

// LOGIKA PENCARIAN 
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
        GROUP_CONCAT(DISTINCT CONCAT('P:', tp.panel, ' / Pt:', tp.port) SEPARATOR ' | ') AS port_info
        FROM tiket t 
        LEFT JOIN port_tiket tp ON t.id_tiket = tp.id_tiket
        LEFT JOIN teknisi tech ON t.id_teknisi = tech.id_teknisi
        LEFT JOIN validasi_tiket tv ON t.id_tiket = tv.id_tiket
        WHERE t.status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') 
        AND t.diambil_oleh = $user_id $filter 
        GROUP BY t.id_tiket
        $search_filter
        ORDER BY t.waktu_selesai DESC";

$result = $conn->query($sql);
?>
<table border="1" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <thead>
        <tr>
            <th colspan="12" style="text-align: center; font-size: 18px; height: 40px; vertical-align: middle;">
                LAPORAN RIWAYAT PEKERJAAN STAF DAMAN
            </th>
        </tr>
        <tr>
            <th colspan="12" style="text-align: center; font-weight: normal; height: 30px; vertical-align: middle;">
                Dicetak Oleh: <b><?php echo strtoupper(htmlspecialchars($nama_karyawan)); ?></b> | Tanggal Unduh: <?php echo date('d-m-Y H:i:s'); ?>
            </th>
        </tr>
        <tr><th colspan="12" style="border: none; height: 15px;"></th></tr> <tr style="background-color: #1D6F42; color: white; font-weight: bold; height: 25px; text-align: center;">
            <th style="white-space: nowrap;">No Tiket</th>
            <th style="white-space: nowrap;">Jenis</th>
            <th style="white-space: nowrap;">Pemohon</th>
            <th style="white-space: nowrap;">Nama ODP</th>
            <th style="white-space: nowrap;">Valins ID</th>
            <th style="white-space: nowrap;">Lat & Long</th>
            <th style="white-space: nowrap;">Panel / Port</th>
            <th style="white-space: nowrap;">Req Line</th>
            <th style="white-space: nowrap;">Realisasi</th>
            <th style="white-space: nowrap;">Waktu Request</th>
            <th style="white-space: nowrap;">Waktu Selesai</th>
            <th style="white-space: nowrap;">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                
                // Normalisasi penulisan status akhir
                $status_cetak = '';
                if ($row['status'] == 'selesai') $status_cetak = 'Selesai';
                elseif ($row['status'] == 'valins_ulang') $status_cetak = 'Valins Ulang';
                elseif ($row['status'] == 'belum_golive') $status_cetak = 'Belum Go Live';
                elseif ($row['status'] == 'sudah_golive') $status_cetak = 'Sudah Go Live';
                elseif ($row['status'] == 'full') $status_cetak = 'ODP Full';
                else $status_cetak = ucfirst($row['status']);

                $port_info_display = (!empty($row['port_info'])) ? $row['port_info'] : "-";
        ?>
        <tr>
            <td style="white-space: nowrap; mso-number-format:'\@';"><b><?php echo htmlspecialchars($row['no_tiket']); ?></b></td>
            <td style="white-space: nowrap;"><?php echo htmlspecialchars(ucfirst($row['jenis_permintaan'])); ?></td>
            <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['nama_pemohon']); ?></td>
            <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['nama_odp']); ?></td>
            <td style="white-space: nowrap; mso-number-format:'\@';"><?php echo htmlspecialchars($row['id_valins'] ? $row['id_valins'] : '-'); ?></td>
            <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['latitude'] ? $row['latitude'].", ".$row['longitude'] : "-"); ?></td>
            <td style="white-space: nowrap;"><?php echo htmlspecialchars($port_info_display); ?></td>
            <td style="text-align: center; white-space: nowrap;"><?php echo htmlspecialchars($row['permintaan_line'] ?? '-'); ?></td>
            <td style="text-align: center; white-space: nowrap;"><?php echo htmlspecialchars($row['realisasi_line'] ?? '-'); ?></td>
            <td style="white-space: nowrap; mso-number-format:'\@';"><?php echo htmlspecialchars($row['waktu_permintaan']); ?></td>
            <td style="white-space: nowrap; mso-number-format:'\@';"><?php echo htmlspecialchars($row['waktu_selesai']); ?></td>
            <td style="white-space: nowrap;"><?php echo htmlspecialchars($status_cetak); ?></td>
        </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="12" style="text-align:center; height:30px; vertical-align:middle;">Tidak ada riwayat pekerjaan yang sesuai kriteria filter.</td></tr>
        <?php endif; ?>
    </tbody>
</table>