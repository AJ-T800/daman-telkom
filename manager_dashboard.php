<!-- <?php
session_start();
require 'koneksi.php';

// Cek apakah user sudah login dan rolenya adalah manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Menangani Aksi Approve Karyawan
if (isset($_GET['approve'])) {
    $id_user = intval($_GET['approve']);
    $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    header("Location: manager_dashboard.php?page=karyawan"); 
    exit();
}

// Menangani Aksi Hapus/Tolak Karyawan
if (isset($_GET['hapus'])) {
    $id_user = intval($_GET['hapus']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    header("Location: manager_dashboard.php?page=karyawan");
    exit();
}

// Menentukan halaman yang sedang aktif (Default: Manajemen Karyawan)
$page = isset($_GET['page']) ? $_GET['page'] : 'karyawan';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 14px; }
        th { background-color: #f2f2f2; }
        .btn-approve { background-color: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;}
        .btn-delete { background-color: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;}
        .btn-gambar { background-color: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 12px;}
        .status-selesai { color: green; font-weight: bold; }
        .status-valins { color: red; font-weight: bold; }
        .modal {
            display: none; 
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8); 
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.5);
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .close-btn:hover { color: #ccc; }
    </style>
</head>
<body>

    <?php include 'navbar_manager.php'; ?>

    <?php if ($page == 'karyawan'): ?>
        <h3>Daftar Karyawan (Pihak Daman)</h3>
        <table>
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
                $result = $conn->query("SELECT * FROM users WHERE role = 'daman' ORDER BY is_approved ASC, created_at DESC");
                while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['username']; ?></td>
                    <td>
                        <?php echo $row['is_approved'] == 1 ? '<span style="color:green;">Aktif</span>' : '<span style="color:red;">Menunggu Persetujuan</span>'; ?>
                    </td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td>
                        <?php if ($row['is_approved'] == 0): ?>
                            <a href="?approve=<?php echo $row['id']; ?>" class="btn-approve" onclick="return confirm('Setujui karyawan ini?')">Approve</a>
                        <?php endif; ?>
                        <a href="?hapus=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Yakin ingin menghapus akun ini?')">Hapus</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php elseif ($page == 'riwayat'): ?>
        <h3>Riwayat Pekerjaan</h3>

        <?php
        // Ambil daftar pekerja daman untuk dropdown
        $list_pekerja = $conn->query("SELECT id, username FROM users WHERE role = 'daman' ORDER BY username ASC");
        ?>

        <form id="filterFormManager" method="GET" action="" style="margin-bottom: 15px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
            <input type="hidden" name="page" value="riwayat">

            <div>
                <label style="font-size: 14px; font-weight: bold;">Jenis Permintaan:</label><br>
                <select name="filter_jenis" style="padding: 7px; border-radius: 4px; border: 1px solid #ccc; margin-top: 5px;">
                    <option value="">Semua</option>
                    <option value="pemunculan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pemunculan') echo 'selected'; ?>>Pemunculan</option>
                    <option value="pengosongan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pengosongan') echo 'selected'; ?>>Pengosongan</option>
                </select>
            </div>

            <div>
                <label style="font-size: 14px; font-weight: bold;">Dikerjakan Oleh:</label><br>
                <select name="filter_pekerja" style="padding: 7px; border-radius: 4px; border: 1px solid #ccc; margin-top: 5px;">
                    <option value="">Semua Pekerja</option>
                    <?php while($p = $list_pekerja->fetch_assoc()): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo (isset($_GET['filter_pekerja']) && $_GET['filter_pekerja'] == $p['id']) ? 'selected' : ''; ?>>
                            <?php echo $p['username']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label style="font-size: 14px; font-weight: bold;">Rentang Tanggal (Selesai):</label><br>
                <div style="display: flex; gap: 5px; margin-top: 5px; align-items: center;">
                    <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                    <span style="font-size: 14px;">s/d</span>
                    <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                </div>
            </div>

            <div>
                <label style="font-size: 14px; font-weight: bold;">Pencarian Umum (No Tiket/ODP/Pemohon):</label><br>
                <input type="text" name="search" placeholder="Cari..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>" style="padding: 6px; border-radius: 4px; border: 1px solid #ccc; width: 180px; margin-top: 5px; margin-bottom: 0;">
            </div>

            <div>
                <button type="submit" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Cari</button>
                <a href="manager_dashboard.php?page=riwayat" style="padding: 7px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 13.5px;">Reset</a>
                
                <button type="button" onclick="cetakLaporan()" style="padding: 7px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13.5px; margin-left: 10px;">
                    🖨️ Cetak PDF
                </button>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>No Tiket</th>
                    <th>Waktu Request</th>
                    <th>Pemohon</th>
                    <th>Jenis</th>
                    <th>Nama ODP</th>
                    <th>Valins ID</th>
                    <th>Lat & Long</th>
                    <th>Panel / Port</th>
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
                    $filter .= " AND t.taken_by = '$pekerja_id'";
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

                // 1. Logika Pencarian Umum dipisah ke $search_filter menggunakan HAVING
                $search_filter = "";
                if (isset($_GET['search']) && $_GET['search'] != "") {
                    $search = $conn->real_escape_string($_GET['search']);
                    $search_filter = " HAVING (t.no_tiket LIKE '%$search%' 
                                        OR t.nama_odp LIKE '%$search%' 
                                        OR t.nama_pemohon LIKE '%$search%' 
                                        OR t.valins_id LIKE '%$search%'
                                        OR CONCAT(t.latitude, ', ', t.longitude) LIKE '%$search%'
                                        OR t.waktu_request LIKE '%$search%'
                                        OR t.waktu_selesai LIKE '%$search%'
                                        OR t.realisasi_line LIKE '%$search%'
                                        OR REPLACE(t.status, '_', ' ') LIKE '%$search%'
                                        OR port_info LIKE '%$search%')";
                }

                // ==========================================
                // PERBAIKAN: Menggunakan LEFT JOIN dengan ticket_ports
                // dan menambahkan status 'sudah_golive' serta $search_filter
                // ==========================================
                $sql = "SELECT t.*, u.username AS daman_name,
                        GROUP_CONCAT(CONCAT('P:', tp.panel, ' / Pt:', tp.port) SEPARATOR ' | ') AS port_info
                        FROM tickets t 
                        LEFT JOIN users u ON t.taken_by = u.id 
                        LEFT JOIN ticket_ports tp ON t.id = tp.ticket_id
                        WHERE t.status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') $filter 
                        GROUP BY t.id
                        $search_filter
                        ORDER BY t.waktu_selesai DESC, t.id DESC";
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $row['no_tiket']; ?></td>
                    <td><?php echo $row['waktu_request']; ?></td>
                    <td><?php echo $row['nama_pemohon']; ?></td>
                    <td><?php echo ucfirst($row['jenis_permintaan']); ?></td>
                    <td><?php echo $row['nama_odp']; ?></td>
                    <td><?php echo $row['valins_id'] ? $row['valins_id'] : '-'; ?></td>
                    <td><?php echo $row['latitude'] ? $row['latitude'] . ', ' . $row['longitude'] : '-'; ?></td>
                    
                    <td><?php echo (!empty($row['port_info'])) ? $row['port_info'] : '-'; ?></td>
                    
                    <td style="text-align: center;"><?php echo $row['realisasi_line'] ? $row['realisasi_line'] : '-'; ?></td>
                    <td><b><?php echo $row['daman_name'] ? $row['daman_name'] : 'Tidak Diketahui'; ?></b></td>
                    <td><?php echo $row['waktu_selesai'] ? $row['waktu_selesai'] : '-'; ?></td>
                    
                    <td>
                        <?php 
                        if ($row['status'] == 'valins_ulang') {
                            echo '<span class="status-valins">Valins Ulang</span>';
                        } elseif ($row['status'] == 'selesai') {
                            echo '<span class="status-selesai">Selesai</span>';
                        } elseif ($row['status'] == 'belum_golive') {
                            echo '<span style="color:#d9534f; font-weight:bold;">Belum Go Live</span>';
                        } elseif ($row['status'] == 'sudah_golive') {
                            echo '<span style="color:#17a2b8; font-weight:bold;">Sudah Go Live</span>'; // PERBAIKAN: Menambahkan label status Sudah Go Live
                        } elseif ($row['status'] == 'full') {
                            echo '<span style="color:#6f42c1; font-weight:bold;">ODP Full</span>';
                        }
                        ?>
                    </td>

                    <td>
                        <?php if ($row['status'] == 'valins_ulang' && !empty($row['bukti_valins_ulang'])): ?>
                            <a href="#" onclick="bukaModal('<?php echo $row['bukti_valins_ulang']; ?>'); return false;" class="btn-gambar">Lihat Gambar</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <tr><td colspan="13" style="text-align:center;">Belum ada riwayat pekerjaan yang sesuai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div id="imageModal" class="modal" onclick="tutupModal()">
        <span class="close-btn" onclick="tutupModal()">&times;</span>
        <img class="modal-content" id="modalImage" onclick="event.stopPropagation()"> 
    </div>

    <script>
        // Skrip JS Pembersih Parameter URL
        document.getElementById("filterFormManager")?.addEventListener("submit", function (e) {
            let inputs = this.querySelectorAll("input, select");
            inputs.forEach(input => { if (!input.value) input.removeAttribute("name"); });
        });

        function bukaModal(imageSrc) {
            var modal = document.getElementById("imageModal");
            var modalImg = document.getElementById("modalImage");
            modal.style.display = "flex"; 
            modalImg.src = imageSrc;      
        }

        function tutupModal() {
            var modal = document.getElementById("imageModal");
            modal.style.display = "none"; 
        }

        function cetakLaporan() {
            const urlParams = new URLSearchParams(window.location.search);
            const destUrl = "cetak_laporan.php?" + urlParams.toString();
            window.open(destUrl, '_blank');
        }
    </script>
    
</body>
</html> -->