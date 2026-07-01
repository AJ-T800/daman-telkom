<?php
session_start();
require 'koneksi.php';

// 1. PROTEKSI HALAMAN: Pastikan sudah login dan rolenya adalah 'daman'
if (!isset($_SESSION['id_pengguna']) || $_SESSION['peran'] !== 'daman') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id_pengguna'];
$nama_user = $_SESSION['nama_pengguna'];
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 2. QUERY REAL-TIME UNTUK SUMMARY CARDS (SELECT COUNT)
// =========================================================================

// Antrean Tiket Pending (Semua Tim)
$q_pending = $conn->query("SELECT COUNT(*) as total FROM tiket WHERE status = 'pending'");
$total_pending = $q_pending->fetch_assoc()['total'];

// Tiket yang sedang dikerjakan oleh Karyawan ini (Personal On-Progress)
$q_progress = $conn->prepare("SELECT COUNT(*) as total FROM tiket WHERE status = 'on_progress' AND diambil_oleh = ?");
$q_progress->bind_param("i", $user_id);
$q_progress->execute();
$total_progress = $q_progress->get_result()->fetch_assoc()['total'];

// Tiket Selesai Hari Ini (Termasuk semua status akhir: selesai, valins_ulang, golive, full)
$q_selesai_hari = $conn->prepare("SELECT COUNT(*) as total FROM tiket WHERE status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') AND diambil_oleh = ? AND DATE(waktu_selesai) = CURDATE()");
$q_selesai_hari->bind_param("i", $user_id);
$q_selesai_hari->execute();
$total_selesai_hari = $q_selesai_hari->get_result()->fetch_assoc()['total'];

// Tiket Selesai Bulan Ini (Termasuk semua status akhir)
$q_selesai_bulan = $conn->prepare("SELECT COUNT(*) as total FROM tiket WHERE status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') AND diambil_oleh = ? AND MONTH(waktu_selesai) = MONTH(CURDATE()) AND YEAR(waktu_selesai) = YEAR(CURDATE())");
$q_selesai_bulan->bind_param("i", $user_id);
$q_selesai_bulan->execute();
$total_selesai_bulan = $q_selesai_bulan->get_result()->fetch_assoc()['total'];


// =========================================================================
// 3. DATA UNTUK GRAFIK 1: PIE CHART (STATUS TIKET SAYA)
// =========================================================================
$status_counts = ['pending' => 0, 'on_progress' => 0, 'selesai' => 0];
$q_pie = $conn->prepare("SELECT status, COUNT(*) as jumlah FROM tiket WHERE diambil_oleh = ? OR status = 'pending' GROUP BY status");
$q_pie->bind_param("i", $user_id);
$q_pie->execute();
$res_pie = $q_pie->get_result();
while ($row = $res_pie->fetch_assoc()) {
    $status_asli = $row['status'];
    if (in_array($status_asli, ['selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full'])) {
        $status_counts['selesai'] += $row['jumlah'];
    } elseif (array_key_exists($status_asli, $status_counts)) {
        $status_counts[$status_asli] += $row['jumlah'];
    }
}

// =========================================================================
// 4. DATA UNTUK GRAFIK 2: BAR CHART (TREN 7 HARI TERAKHIR)
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
    
    // Hitung tiket selesai milik user ini pada tanggal tersebut (semua status selesai)
    $q_tren = $conn->prepare("SELECT COUNT(*) as total FROM tiket WHERE diambil_oleh = ? AND DATE(waktu_selesai) = ? AND status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full')");
    $q_tren->bind_param("is", $user_id, $tanggal_target);
    $q_tren->execute();
    $data_tren[] = $q_tren->get_result()->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Utama DAMAN - Infranexia</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    
    <link rel="stylesheet" href="css/dashboard_utama_karyawan.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-daman">
    <div class="main-content">
        <div class="header-title">
            <h1>Dashboard Utama Karyawan</h1>
            <p>Selamat datang kembali, <strong><?= htmlspecialchars($nama_user); ?></strong>. Berikut adalah ringkasan performa pemrosesan tiket Anda hari ini.</p>
        </div>

        <div class="cards-grid">
            <div class="card card-pending">
                <div class="card-details">
                    <h3><?= $total_pending; ?></h3>
                    <p>TIKET PENDING (SEMUA)</p>
                </div>
                <div class="card-icon"><span class="material-symbols-outlined" style="font-size:40px">hourglass_empty</span></div>
            </div>
            <div class="card card-progress">
                <div class="card-details">
                    <h3><?= $total_progress; ?></h3>
                    <p>TIKET SAYA (PROGRESS)</p>
                </div>
                <div class="card-icon"><span class="material-symbols-outlined" style="font-size:40px">build</span></div>
            </div>
            <div class="card card-today">
                <div class="card-details">
                    <h3><?= $total_selesai_hari; ?></h3>
                    <p>SAYA SELESAIKAN HARI INI</p>
                </div>
                <div class="card-icon"><span class="material-symbols-outlined" style="font-size:40px">check_circle</span></div>
            </div>
            <div class="card card-month">
                <div class="card-details">
                    <h3><?= $total_selesai_bulan; ?></h3>
                    <p>SAYA SELESAIKAN BULAN INI</p>
                </div>
                <div class="card-icon"><span class="material-symbols-outlined" style="font-size:40px">calendar_month</span></div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-box">
                <h2>Komposisi Status Tiket</h2>
                <div class="chart-wrapper">
                    <canvas id="pieStatusChart" 
                            data-pending="<?= $status_counts['pending']; ?>" 
                            data-progress="<?= $status_counts['on_progress']; ?>" 
                            data-selesai="<?= $status_counts['selesai']; ?>"></canvas>
                </div>
            </div>
            <div class="chart-box">
                <h2>Tren Penyelesaian Tiket Anda (7 Hari Terakhir)</h2>
                <div class="chart-wrapper">
                    <canvas id="barTrenChart" data-tren='<?= json_encode($data_tren); ?>'></canvas>
                </div>
            </div>
        </div>

        <div class="table-box">
            <h2>Tanggungan Pekerjaan Anda (On-Progress)</h2>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>No Tiket</th>
                        <th>Teknisi / Pemohon</th>
                        <th>Jenis Permintaan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $q_tanggungan = $conn->prepare("SELECT t.*, tk.nama_pemohon FROM tiket t LEFT JOIN teknisi tk ON t.id_teknisi = tk.id_teknisi WHERE t.diambil_oleh = ? AND t.status = 'on_progress' ORDER BY t.id_tiket DESC");
                    $q_tanggungan->bind_param("i", $user_id);
                    $q_tanggungan->execute();
                    $res_tanggungan = $q_tanggungan->get_result();

                    if ($res_tanggungan->num_rows > 0) {
                        while ($row = $res_tanggungan->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td><strong>" . htmlspecialchars($row['no_tiket']) . "</strong></td>";
                            echo "<td>" . htmlspecialchars($row['nama_pemohon'] ?? 'Bot Telegram') . "</td>";
                            echo "<td>" . htmlspecialchars($row['jenis_permintaan']) . "</td>";
                            echo "<td><span class='badge badge-progress'>ON PROGRESS</span></td>";
                            echo "<td><a href='daftar_permintaan.php?page=form&id=" . $row['id_tiket'] . "' class='btn-kerjakan'>Lanjutkan</a></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center; color:#27ae60; padding: 20px;'><strong>🎉 Hebat!</strong> Tidak ada tanggungan pekerjaan On-Progress saat ini. Semua tiket sudah Anda selesaikan.</td></tr>";
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
                    backgroundColor: ['#f39c12', '#00c0ef', '#00a65a', '#dd4b39'],
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
                    label: 'Tiket Sukses Diselesaikan',
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
                    document.querySelector('.card-progress h3').innerHTML = doc.querySelector('.card-progress h3').innerHTML;
                    document.querySelector('.card-today h3').innerHTML = doc.querySelector('.card-today h3').innerHTML;
                    document.querySelector('.card-month h3').innerHTML = doc.querySelector('.card-month h3').innerHTML;

                    // 2. Update Isi Tabel On-Progress
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
                        myPieChart.update(); // Refresh grafik dengan animasi bawaan Chart.js
                    }

                    // 4. Update Grafik Bar Chart secara Halus
                    const newCanvasBar = doc.getElementById('barTrenChart');
                    if (newCanvasBar) {
                        myBarChart.data.datasets[0].data = JSON.parse(newCanvasBar.getAttribute('data-tren'));
                        myBarChart.update(); // Refresh grafik dengan animasi bawaan Chart.js
                    }
                })
                .catch(err => console.warn('Gagal memuat auto-refresh:', err));
        }, 5000);
    </script>
</body>
</html>