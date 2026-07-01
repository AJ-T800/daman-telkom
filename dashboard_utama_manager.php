<?php
session_start();
require 'koneksi.php';

// 1. PROTEKSI HALAMAN: Pastikan sudah login dan rolenya adalah 'manager'
if (!isset($_SESSION['id_pengguna']) || $_SESSION['peran'] !== 'manager') {
    header("Location: login.php");
    exit();
}

$nama_user = $_SESSION['nama_pengguna'];
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 2. QUERY REAL-TIME UNTUK SUMMARY CARDS (SEMUA KARYAWAN)
// =========================================================================

// Antrean Tiket Pending (Semua)
$q_pending = $conn->query("SELECT COUNT(*) as total FROM tiket WHERE status = 'pending'");
$total_pending = $q_pending->fetch_assoc()['total'];

// Tiket Selesai Hari Ini (Semua Karyawan)
$q_selesai_hari = $conn->query("SELECT COUNT(*) as total FROM tiket WHERE status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') AND DATE(waktu_selesai) = CURDATE()");
$total_selesai_hari = $q_selesai_hari->fetch_assoc()['total'];

// Tiket Selesai Bulan Ini (Semua Karyawan)
$q_selesai_bulan = $conn->query("SELECT COUNT(*) as total FROM tiket WHERE status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') AND MONTH(waktu_selesai) = MONTH(CURDATE()) AND YEAR(waktu_selesai) = YEAR(CURDATE())");
$total_selesai_bulan = $q_selesai_bulan->fetch_assoc()['total'];


// =========================================================================
// 3. DATA UNTUK GRAFIK 1: PIE CHART (KOMPOSISI STATUS SELURUH TIKET)
// =========================================================================
$status_counts = ['pending' => 0, 'on_progress' => 0, 'selesai' => 0];
$q_pie = $conn->query("SELECT status, COUNT(*) as jumlah FROM tiket GROUP BY status");
while ($row = $q_pie->fetch_assoc()) {
    $status_asli = $row['status'];
    if (in_array($status_asli, ['selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full'])) {
        $status_counts['selesai'] += $row['jumlah'];
    } elseif (array_key_exists($status_asli, $status_counts)) {
        $status_counts[$status_asli] += $row['jumlah'];
    }
}

// =========================================================================
// 4. DATA UNTUK GRAFIK 2: BAR CHART (TREN 7 HARI TERAKHIR - SEMUA TIKET)
// =========================================================================
$label_tren = [];
$data_tren = [];
$hari_ini = new DateTime();

for ($i = 6; $i >= 0; $i--) {
    $d = clone $hari_ini;
    $d->modify("-$i days");
    $tanggal_target = $d->format('Y-m-d');
    $nama_hari_eng = $d->format('D');
    
    // Mapping nama hari ke Bahasa Indonesia
    $map_hari = ['Mon'=>'Senin', 'Tue'=>'Selasa', 'Wed'=>'Rabu', 'Thu'=>'Kamis', 'Fri'=>'Jumat', 'Sat'=>'Sabtu', 'Sun'=>'Minggu'];
    $label_tren[] = $map_hari[$nama_hari_eng];
    
    // Hitung semua tiket selesai pada tanggal tersebut
    $q_tren = $conn->prepare("SELECT COUNT(*) as total FROM tiket WHERE DATE(waktu_selesai) = ? AND status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full')");
    $q_tren->bind_param("s", $tanggal_target);
    $q_tren->execute();
    $data_tren[] = $q_tren->get_result()->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Utama Manager - Infranexia</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    
    <link rel="stylesheet" href="css/dashboard_utama_manager.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-manager">
    <div class="main-content">
        <div class="header-title">
            <h1>Dashboard Manager</h1>
            <p>Selamat datang, <strong><?= htmlspecialchars($nama_user); ?></strong>. Berikut adalah ringkasan performa penyelesaian tiket seluruh tim saat ini.</p>
        </div>

        <div class="cards-grid">
            <div class="card card-pending">
                <div class="card-details">
                    <h3><?= $total_pending; ?></h3>
                    <p>TOTAL TIKET PENDING</p>
                </div>
                <div class="card-icon"><span class="material-symbols-outlined" style="font-size:40px">hourglass_empty</span></div>
            </div>
            <div class="card card-today">
                <div class="card-details">
                    <h3><?= $total_selesai_hari; ?></h3>
                    <p>DISELESAIKAN HARI INI (SEMUA TIM)</p>
                </div>
                <div class="card-icon"><span class="material-symbols-outlined" style="font-size:40px">check_circle</span></div>
            </div>
            <div class="card card-month">
                <div class="card-details">
                    <h3><?= $total_selesai_bulan; ?></h3>
                    <p>DISELESAIKAN BULAN INI (SEMUA TIM)</p>
                </div>
                <div class="card-icon"><span class="material-symbols-outlined" style="font-size:40px">calendar_month</span></div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-box">
                <h2>Komposisi Status Tiket (Keseluruhan)</h2>
                <div class="chart-wrapper">
                    <canvas id="pieStatusChart" 
                            data-pending="<?= $status_counts['pending']; ?>" 
                            data-progress="<?= $status_counts['on_progress']; ?>" 
                            data-selesai="<?= $status_counts['selesai']; ?>"></canvas>
                </div>
            </div>
            <div class="chart-box">
                <h2>Tren Penyelesaian Tiket Tim (7 Hari Terakhir)</h2>
                <div class="chart-wrapper">
                    <canvas id="barTrenChart" data-tren='<?= json_encode($data_tren); ?>'></canvas>
                </div>
            </div>
        </div>

        <div class="table-box">
            <h2>Daftar Seluruh Tiket Pending (Antrean)</h2>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>No Tiket</th>
                        <th>Waktu Permintaan</th>
                        <th>Teknisi / Pemohon</th>
                        <th>Jenis Permintaan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Mengambil data seluruh tiket yang berstatus 'pending'
                    $q_tanggungan = $conn->query("SELECT t.*, tk.nama_pemohon FROM tiket t LEFT JOIN teknisi tk ON t.id_teknisi = tk.id_teknisi WHERE t.status = 'pending' ORDER BY t.waktu_permintaan ASC");

                    if ($q_tanggungan->num_rows > 0) {
                        while ($row = $q_tanggungan->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td><strong>" . htmlspecialchars($row['no_tiket']) . "</strong></td>";
                            echo "<td>" . date('d M Y, H:i', strtotime($row['waktu_permintaan'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nama_pemohon'] ?? 'Bot Telegram') . "</td>";
                            echo "<td>" . htmlspecialchars(ucfirst($row['jenis_permintaan'])) . "</td>";
                            echo "<td><span class='badge badge-pending'>PENDING</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center; color:#27ae60; padding: 20px;'><strong>🎉 Luar Biasa!</strong> Tidak ada antrean tiket pending saat ini.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div> 
<script>
    // --- INISIALISASI PIE CHART ---
    const canvasPie = document.getElementById('pieStatusChart');
    const ctxPie = canvasPie.getContext('2d');
    const myPieChart = new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: ['Pending', 'On Progress', 'Selesai'],
            datasets: [{
                data: [
                    parseInt(canvasPie.getAttribute('data-pending')), 
                    parseInt(canvasPie.getAttribute('data-progress')), 
                    parseInt(canvasPie.getAttribute('data-selesai'))
                ],
                backgroundColor: ['#f39c12', '#00c0ef', '#00a65a'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // --- INISIALISASI BAR CHART ---
    const canvasBar = document.getElementById('barTrenChart');
    const ctxBar = canvasBar.getContext('2d');
    const myBarChart = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?= json_encode($label_tren); ?>,
            datasets: [{
                label: 'Total Tiket Diselesaikan',
                data: JSON.parse(canvasBar.getAttribute('data-tren')),
                backgroundColor: '#0073b7',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });

    // =========================================================================
    // SCRIPT REAL-TIME AUTO REFRESH (SETIAP 5 DETIK) UNTUK SEMUA ELEMEN
    // =========================================================================
    setInterval(function() {
        fetch(window.location.href)
            .then(response => {
                if (!response.ok) throw new Error('Jaringan bermasalah');
                return response.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // 1. Update Angka di Summary Cards
                document.querySelector('.card-pending h3').innerHTML = doc.querySelector('.card-pending h3').innerHTML;
                document.querySelector('.card-today h3').innerHTML = doc.querySelector('.card-today h3').innerHTML;
                document.querySelector('.card-month h3').innerHTML = doc.querySelector('.card-month h3').innerHTML;

                // 2. Update Isi Tabel Pending
                const tbodyLama = document.querySelector('.table-custom tbody');
                const tbodyBaru = doc.querySelector('.table-custom tbody');
                if (tbodyLama && tbodyBaru) {
                    tbodyLama.innerHTML = tbodyBaru.innerHTML;
                }

                // 3. Update Grafik Pie Chart secara Halus
                const newCanvasPie = doc.getElementById('pieStatusChart');
                if (newCanvasPie) {
                    myPieChart.data.datasets[0].data = [
                        parseInt(newCanvasPie.getAttribute('data-pending')),
                        parseInt(newCanvasPie.getAttribute('data-progress')),
                        parseInt(newCanvasPie.getAttribute('data-selesai'))
                    ];
                    myPieChart.update();
                }

                // 4. Update Grafik Bar Chart secara Halus
                const newCanvasBar = doc.getElementById('barTrenChart');
                if (newCanvasBar) {
                    myBarChart.data.datasets[0].data = JSON.parse(newCanvasBar.getAttribute('data-tren'));
                    myBarChart.update();
                }
            })
            .catch(err => console.warn('Gagal memuat auto-refresh:', err));
    }, 5000);
</script>
</body>
</html>