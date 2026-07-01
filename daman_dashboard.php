<?php
session_start();
require 'koneksi.php';

// Cek apakah user sudah login dan rolenya adalah daman
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'daman') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
date_default_timezone_set('Asia/Jakarta');

// ==========================================
// 1. LOGIKA AMBIL TIKET
// ==========================================
if (isset($_GET['ambil'])) {
    $id_tiket = intval($_GET['ambil']);
    $cek = $conn->prepare("SELECT status FROM tickets WHERE id = ? AND status = 'pending'");
    $cek->bind_param("i", $id_tiket);
    $cek->execute();
    
    if ($cek->get_result()->num_rows > 0) {
        $update = $conn->prepare("UPDATE tickets SET status = 'on_progress', taken_by = ? WHERE id = ?");
        $update->bind_param("ii", $user_id, $id_tiket);
        $update->execute();
        header("Location: daman_dashboard.php?page=form&id=" . $id_tiket);
    } else {
        echo "<script>alert('Maaf, tiket ini sudah diambil rekan lain!'); window.location.href='daman_dashboard.php';</script>";
    }
    exit();
}

// ==========================================
// 2. LOGIKA BATAL TIKET
// ==========================================
if (isset($_GET['batal'])) {
    $id_tiket = intval($_GET['batal']);
    $update = $conn->prepare("UPDATE tickets SET status = 'pending', taken_by = NULL WHERE id = ? AND taken_by = ?");
    $update->bind_param("ii", $id_tiket, $user_id);
    $update->execute();
    header("Location: daman_dashboard.php");
    exit();
}

// ==========================================
// 3. LOGIKA SUBMIT FORM (SELESAI / STATUS KHUSUS)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['selesaikan_tiket'])) {
    $id_tiket = $_POST['id_tiket'];
    $waktu_selesai = date('Y-m-d H:i:s');
    
    $stmt_get = $conn->prepare("SELECT chat_id, tg_message_id, jenis_permintaan, req_line FROM tickets WHERE id = ?");
    $stmt_get->bind_param("i", $id_tiket);
    $stmt_get->execute();
    $ticket_data = $stmt_get->get_result()->fetch_assoc();
    
    $jenis = $ticket_data['jenis_permintaan'];
    $req_line = (int)$ticket_data['req_line'];
    
    $bot_token = "8725441277:AAHJ_hWS0DcCxv_ZjBBqRNQJSun8ywSNlTk"; 
    $chat_id = $ticket_data['chat_id'];
    $reply_to = $ticket_data['tg_message_id'];

    // --- CEK STATUS KHUSUS (BELUM GOLIVE / FULL / SUDAH GOLIVE) ---
    $special_status = isset($_POST['special_status']) ? $_POST['special_status'] : '';
    
    if ($special_status === 'belum_golive' || $special_status === 'full' || $special_status === 'sudah_golive') {
        // VALIDASI: Full dan Sudah Go Live HANYA untuk Pemunculan
        if (($special_status === 'full' || $special_status === 'sudah_golive') && $jenis != 'pemunculan') {
            $label_error = ($special_status === 'full') ? 'ODP Full' : 'Sudah Go Live';
            echo "<script>alert('Sistem Menolak: Status $label_error hanya berlaku untuk tiket Pemunculan!'); window.history.back();</script>";
            exit();
        }
        
        $update = $conn->prepare("UPDATE tickets SET status = ?, waktu_selesai = ? WHERE id = ? AND taken_by = ?");
        $update->bind_param("ssii", $special_status, $waktu_selesai, $id_tiket, $user_id);
        
        if ($update->execute()) {
            if ($special_status === 'belum_golive') {
                $pesan_balasan = "ODP belum golive";
            } elseif ($special_status === 'sudah_golive') {
                $pesan_balasan = "ODP sudah golive";
            } else {
                $pesan_balasan = "Port ODP full atau penuh silahkan melakukan permintaan pengosongan";
            }
            
            $data = ['chat_id' => $chat_id, 'text' => $pesan_balasan];
            if (!empty($reply_to) && $reply_to != 0) {
                $data['reply_to_message_id'] = $reply_to;
            }
            
            $url = "https://api.telegram.org/bot$bot_token/sendMessage";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
            $response = curl_exec($ch);
            curl_close($ch);
            
            echo "<script>alert('Tiket diakhiri dengan status: ".strtoupper(str_replace('_', ' ', $special_status)).". Bot berhasil membalas!'); window.location.href='daman_dashboard.php';</script>";
            exit();
        } else {
            echo "<script>alert('ERROR SISTEM: Gagal mengupdate tiket!'); window.history.back();</script>";
            exit();
        }
    }

    // --- JIKA BUKAN STATUS KHUSUS, LANJUT PROSES NORMAL ---
    $lat = $_POST['latitude'];
    $long = $_POST['longitude'];
    $realisasi = (int)$_POST['realisasi_line'];
    
    $panels = isset($_POST['panel']) ? $_POST['panel'] : [];
    $ports = isset($_POST['port']) ? $_POST['port'] : [];
    
    $telegram_panel_port = "";
    
    // Validasi input Pengosongan
    if ($jenis == 'pengosongan') {
        if (empty(trim($panels[0])) || empty(trim($ports[0]))) {
            echo "<script>alert('Sistem Menolak: Tiket Pengosongan WAJIB mengisi Panel dan Port!'); window.history.back();</script>";
            exit();
        }
        if ($realisasi != $req_line) {
            echo "<script>alert('Sistem Menolak: Pengosongan harus sama persis dengan Request Line!'); window.history.back();</script>";
            exit();
        }
    }
    // Validasi input Pemunculan
    if ($jenis == 'pemunculan' && $realisasi > $req_line) {
        echo "<script>alert('Sistem Menolak: Realisasi Pemunculan tidak boleh lebih dari Request Line!'); window.history.back();</script>";
        exit();
    }
    
    // UPDATE tabel tickets tanpa kolom panel dan port
    $update = $conn->prepare("UPDATE tickets SET status = 'selesai', latitude = ?, longitude = ?, realisasi_line = ?, waktu_selesai = ? WHERE id = ? AND taken_by = ?");
    $update->bind_param("ssisii", $lat, $long, $realisasi, $waktu_selesai, $id_tiket, $user_id);
    
    if ($update->execute()) {
        // Hapus data port lama (mencegah duplikat jika proses ulang)
        $conn->query("DELETE FROM ticket_ports WHERE ticket_id = $id_tiket");
        
        // Simpan data panel dan port ke tabel terpisah (ticket_ports)
        $stmt_port = $conn->prepare("INSERT INTO ticket_ports (ticket_id, panel, port) VALUES (?, ?, ?)");
        
        for ($i = 0; $i < count($panels); $i++) {
            $pnl = trim($panels[$i]);
            $prt = trim($ports[$i]);
            if ($pnl !== '' && $prt !== '') {
                $stmt_port->bind_param("iss", $id_tiket, $pnl, $prt);
                $stmt_port->execute();
                
                $telegram_panel_port .= " panel $pnl port $prt";
                if ($i < count($panels) - 1) $telegram_panel_port .= ",";
            }
        }
        
        $pesan_balasan = "";
        if ($jenis == 'pemunculan') {
            $pesan_balasan = "$lat, $long";
            if ($req_line > 1 && $realisasi < $req_line) $pesan_balasan .= " $realisasi line";
        } else if ($jenis == 'pengosongan') {
            $pesan_balasan = "$lat, $long" . $telegram_panel_port; 
        }
        
        $url = "https://api.telegram.org/bot$bot_token/sendMessage";
        $data = ['chat_id' => $chat_id, 'text' => $pesan_balasan, 'reply_to_message_id' => $reply_to];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        $response = curl_exec($ch);
        curl_close($ch);
        
        echo "<script>alert('Mantap! Tiket berhasil diselesaikan.'); window.location.href='daman_dashboard.php';</script>";
    }
}

// ==========================================
// 4. LOGIKA VALIDASI ULANG
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['validasi_ulang'])) {
    $id_tiket = $_POST['id_tiket'];
    $catatan = trim($_POST['catatan_valins']);
    
    $stmt_get = $conn->prepare("SELECT chat_id, tg_message_id, no_tiket FROM tickets WHERE id = ?");
    $stmt_get->bind_param("i", $id_tiket);
    $stmt_get->execute();
    $ticket_data = $stmt_get->get_result()->fetch_assoc();
    
    $bot_token = "8725441277:AAHJ_hWS0DcCxv_ZjBBqRNQJSun8ywSNlTk"; 
    $chat_id = $ticket_data['chat_id'];
    $reply_to = $ticket_data['tg_message_id'];
    
    $pesan_caption = "❌ *VALIDASI ULANG PENGOSONGAN*\nTiket: ".$ticket_data['no_tiket']."\nCatatan Daman: " . $catatan . "\nMohon perbaiki gambar valins pada ODP terkait.";
    
    if (isset($_FILES['foto_valins']) && $_FILES['foto_valins']['error'] == 0) {
        $file_tmp = $_FILES['foto_valins']['tmp_name'];
        $file_type = $_FILES['foto_valins']['type'];
        $file_name = $_FILES['foto_valins']['name'];
        
        $ekstensi = pathinfo($file_name, PATHINFO_EXTENSION);
        $nama_file_baru = "bukti_valins_" . $id_tiket . "_" . time() . "." . $ekstensi;
        $target_dir = "uploads/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $target_file = $target_dir . $nama_file_baru;
        $bukti_path = null;
        
        if (move_uploaded_file($file_tmp, $target_file)) {
            $bukti_path = $target_file;
        }
        
        $file_to_send = $bukti_path ? realpath($bukti_path) : $file_tmp;
        $cfile = new CURLFile($file_to_send, $file_type, $file_name);
        
        $data = [
            'chat_id' => $chat_id,
            'photo' => $cfile,
            'caption' => $pesan_caption,
            'parse_mode' => 'Markdown',
            'reply_to_message_id' => $reply_to
        ];
        
        $url = "https://api.telegram.org/bot$bot_token/sendPhoto";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($bukti_path) {
            $update = $conn->prepare("UPDATE tickets SET status = 'valins_ulang', bukti_valins_ulang = ?, waktu_selesai = NOW() WHERE id = ?");
            $update->bind_param("si", $bukti_path, $id_tiket);
        } else {
            $update = $conn->prepare("UPDATE tickets SET status = 'valins_ulang', waktu_selesai = NOW() WHERE id = ?");
            $update->bind_param("i", $id_tiket);
        }
        $update->execute();
        
        echo "<script>alert('Perintah Validasi Ulang beserta gambar telah dikirim ke Telegram dan disimpan di sistem!'); window.location.href='daman_dashboard.php';</script>";
        exit();
    } else {
        echo "<script>alert('Gagal! Anda wajib melampirkan gambar bukti untuk Validasi Ulang.'); window.history.back();</script>";
        exit();
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'list';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Daman</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 14px;}
        th { background-color: #f2f2f2; }
        .btn-ambil { background-color: #28a745; color: white; padding: 7px 12px; text-decoration: none; border-radius: 3px; cursor: pointer; border: none; font-size: 14px;}
        .btn-batal { background-color: #ffc107; color: black; padding: 7px 12px; text-decoration: none; border-radius: 3px; font-size: 14px; display: inline-block; text-align: center;}
        .form-container { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="text"], input[type="number"], textarea { width: 95%; padding: 8px; margin: 5px 0 15px 0; border: 1px solid #ccc; border-radius: 4px;}
        .img-preview { max-width: 200px; height: auto; border: 2px solid #ddd; border-radius: 4px; padding: 5px; margin-right: 10px; transition: 0.3s; }
        .img-preview:hover { border-color: #007bff; cursor: pointer; }    
        .status-selesai { color: green; font-weight: bold; }
        .status-valins { color: red; font-weight: bold; }
        .btn-gambar { background-color: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 12px;}
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <?php if ($page == 'list'): ?>
        <h3>Daftar Tiket Tersedia</h3>
        <form id="filterFormList" method="GET" action="" style="margin-bottom: 15px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
            <div>
                <label style="font-size: 14px; font-weight: bold;">Jenis Permintaan:</label><br>
                <select name="filter_jenis" style="padding: 7px; border-radius: 4px; border: 1px solid #ccc; margin-top: 5px;">
                    <option value="">Semua</option>
                    <option value="pemunculan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pemunculan') echo 'selected'; ?>>Pemunculan</option>
                    <option value="pengosongan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pengosongan') echo 'selected'; ?>>Pengosongan</option>
                </select>
            </div>
            <div>
                <label style="font-size: 14px; font-weight: bold;">Tanggal Request:</label><br>
                <input type="date" name="filter_tanggal" value="<?php echo isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : ''; ?>" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc; margin-top: 5px;">
            </div>
            <div>
                <label style="font-size: 14px; font-weight: bold;">Pencarian:</label><br>
                <input type="text" name="search" placeholder="Cari No Tiket, ODP, Pemohon..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>" style="padding: 6px; border-radius: 4px; border: 1px solid #ccc; width: 200px; margin-top: 5px; margin-bottom: 0;">
            </div>
            <div>
                <button type="submit" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Cari</button>
                <a href="daman_dashboard.php" style="padding: 7px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 13.5px;">Reset</a>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Nomor Tiket</th>
                    <th>Waktu Request</th>
                    <th>Pemohon</th>
                    <th>Jenis</th>
                    <th>ODP</th>
                    <th>Valins ID</th>
                    <th>Req Line</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $filter = "";
                if (isset($_GET['filter_jenis']) && $_GET['filter_jenis'] != "") {
                    $jenis = $conn->real_escape_string($_GET['filter_jenis']);
                    $filter .= " AND jenis_permintaan = '$jenis'"; 
                }
                if (isset($_GET['filter_tanggal']) && $_GET['filter_tanggal'] != "") {
                    $tanggal = $conn->real_escape_string($_GET['filter_tanggal']);
                    $filter .= " AND DATE(waktu_request) = '$tanggal'"; 
                }
                if (isset($_GET['search']) && $_GET['search'] != "") {
                    $search = $conn->real_escape_string($_GET['search']);
                    $filter .= " AND (no_tiket LIKE '%$search%' OR nama_odp LIKE '%$search%' OR nama_pemohon LIKE '%$search%')";
                }
                
                $sql = "SELECT * FROM tickets WHERE (status = 'pending' OR (status = 'on_progress' AND taken_by = $user_id)) $filter ORDER BY waktu_request ASC";
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><b><?php echo $row['no_tiket']; ?></b></td>
                    <td><?php echo $row['waktu_request']; ?></td>
                    <td><?php echo $row['nama_pemohon']; ?></td>
                    <td><b><?php echo strtoupper($row['jenis_permintaan']); ?></b></td>
                    <td><?php echo $row['nama_odp']; ?></td>
                    <td><?php echo $row['valins_id'] ? $row['valins_id'] : '-'; ?></td>
                    <td><?php echo $row['req_line']; ?></td>
                    <td>
                        <?php if ($row['status'] == 'pending'): ?>
                            <a href="?ambil=<?php echo $row['id']; ?>" class="btn-ambil" onclick="return confirm('Ambil tiket ini untuk dikerjakan?')">Ambil</a>
                        <?php elseif ($row['status'] == 'on_progress' && $row['taken_by'] == $user_id): ?>
                            <a href="?page=form&id=<?php echo $row['id']; ?>" class="btn-ambil" style="background-color:#007bff;">Lanjutkan</a>
                            <a href="?batal=<?php echo $row['id']; ?>" class="btn-batal" onclick="return confirm('Yakin ingin membatalkan pengerjaan tiket ini?')">Batal</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" style="text-align:center;">Tidak ada permintaan saat ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    <?php 
    elseif ($page == 'form' && isset($_GET['id'])): 
        $id_tiket = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ? AND taken_by = ?");
        $stmt->bind_param("ii", $id_tiket, $user_id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        
        if (!$ticket) {
            echo "Tiket tidak ditemukan atau bukan milik Anda.";
            exit();
        }
    ?>
        <h3>Pengerjaan Tiket #<?php echo $ticket['no_tiket']; ?></h3>
        <p style="background: #e9ecef; padding: 10px; border-radius: 4px;">
            <b>Jenis:</b> <span style="color:#d9534f; font-weight:bold;"><?php echo strtoupper($ticket['jenis_permintaan']); ?></span> | 
            <b>ODP:</b> <?php echo $ticket['nama_odp']; ?> | 
            <b>Req Line:</b> <?php echo $ticket['req_line']; ?>
        </p>
        
        <div style="margin-bottom: 20px; padding: 15px; border: 1px dashed #adb5bd; background: #fff; border-radius: 5px;">
            <h4 style="margin-top: 0;">📸 Lampiran Gambar dari Teknisi:</h4>
            <div>
            <?php
            $stmt_img = $conn->query("SELECT file_path FROM ticket_images WHERE ticket_id = $id_tiket");
            if ($stmt_img->num_rows > 0) {
                while ($img = $stmt_img->fetch_assoc()) {
                    echo "<a href='".$img['file_path']."' target='_blank'><img src='".$img['file_path']."' class='img-preview'></a>";
                }
            } else {
                echo "<p style='color: #6c757d; font-style: italic;'>Teknisi tidak melampirkan gambar pada tiket ini.</p>";
            }
            ?>
            </div>
        </div>

        <div style="display: flex; gap: 20px;">
            <div class="form-container" style="flex: 1.5;">
                <h4 style="margin-top: 0;">✅ Selesaikan Tiket</h4>
                <label style="color:#007bff; font-size: 14px;"><b>[Auto-Fill] Paste format balasan di sini:</b></label><br>
                <textarea id="raw_input" rows="3" placeholder="Contoh: -4.119934456, 104.16210061 panel 1 port 1, 4, 5 ATAU ketik 'odp full' / 'belum golive' / 'sudah golive'..." oninput="parseInput()"></textarea>                
                <hr style="border:0; border-top:1px solid #ddd; margin: 15px 0;">                
                
                <form method="POST" action="" onsubmit="return validasiForm()">
                    <input type="hidden" name="id_tiket" value="<?php echo $ticket['id']; ?>">
                    <input type="hidden" id="special_status" name="special_status" value="">                    

                    <div id="latlong_container" style="display: flex; gap: 10px;">
                        <div style="flex:1;">
                            <label>Latitude:</label><br>
                            <input type="text" id="latitude" name="latitude" required>
                        </div>
                        <div style="flex:1;">
                            <label>Longitude:</label><br>
                            <input type="text" id="longitude" name="longitude" required>
                        </div>
                    </div>

                    <div id="panel_port_container">
                        <div class="panel-port-row">
                            <label>Panel:</label><br>
                            <input type="text" name="panel[]" class="panel-input"><br>
                            <label>Port:</label><br>
                            <input type="text" name="port[]" class="port-input" placeholder="Contoh: 1, 4, 5"><br>
                        </div>
                    </div>
                    
                    <div id="realisasi_container">
                        <label>Realisasi Line (Sesuai yang tersedia):</label><br>
                        <input type="number" id="realisasi_line" name="realisasi_line" value="<?php echo $ticket['req_line']; ?>" required><br>
                    </div>                    

                    <div style="margin-top: 15px;">
                        <button type="submit" id="btn_submit_selesai" name="selesaikan_tiket" class="btn-ambil" style="width: 100%; margin-bottom: 10px;">Submit Selesai</button>
                        <a href="?batal=<?php echo $ticket['id']; ?>" class="btn-batal" style="width: 95%; background-color:#e2e6ea; color:#333;" onclick="return confirm('Batal mengerjakan?')">Tinggalkan Form</a>
                    </div>
                </form>
            </div>

            <?php if ($ticket['jenis_permintaan'] == 'pengosongan'): ?>
            <div class="form-container" style="flex: 1; background-color: #fff3cd; border: 1px solid #ffeeba;">
                <h4 style="color: #856404; margin-top: 0;">⚠️ Minta Validasi Ulang</h4>
                <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Gunakan form ini jika gambar Valins tidak sesuai. Bot akan mengirim gambar penolakan Anda ke grup Telegram teknisi.</p>                
                
                <form method="POST" action="" enctype="multipart/form-data" onsubmit="return confirm('Yakin ingin menolak dan meminta teknisi melakukan Validasi Ulang?')">
                    <input type="hidden" name="id_tiket" value="<?php echo $ticket['id']; ?>">
                    <label style="font-size: 14px; font-weight: bold;">Upload Bukti Gambar Salah:</label><br>
                    <input type="file" name="foto_valins" accept="image/*" required style="margin: 10px 0; font-size: 14px;"><br>                    
                    <label style="font-size: 14px; font-weight: bold;">Catatan untuk Teknisi:</label><br>
                    <textarea name="catatan_valins" rows="4" placeholder="Contoh: Valins buram, tag ODP tidak terlihat jelas, tolong foto ulang bagian port..." required></textarea><br>                    
                    <button type="submit" name="validasi_ulang" class="btn-batal" style="background-color: #dc3545; color: white; border:none; cursor:pointer; width: 100%; margin-top: 10px;">Kirim Permintaan Validasi Ulang</button>
                </form>
            </div>
        <?php endif; ?>
        </div>

        <script>
            function parseInput() {
                const raw = document.getElementById('raw_input').value;
                const rawLower = raw.toLowerCase().trim(); 
                const reqLine = parseInt("<?php echo $ticket['req_line']; ?>");
                const container = document.getElementById('panel_port_container');
                const latLongContainer = document.getElementById('latlong_container');
                const realisasiContainer = document.getElementById('realisasi_container');
                const specialInput = document.getElementById('special_status');
                const submitBtn = document.getElementById('btn_submit_selesai');

                // 1. Cek Mode Khusus (Belum Go Live / Full / SUDAH GO LIVE) STRICT MATCH
                let isBelumGolive = (
                    rawLower === 'belum golive' || rawLower === 'blm golive' || 
                    rawLower === 'blum golive' || rawLower === 'blum go live' || 
                    rawLower === 'belm golive' || rawLower === 'belm go live' || 
                    rawLower === 'belum go live' || rawLower === 'blm go live'
                );
                
                let isSudahGolive = (
                    rawLower === 'sudah golive' || rawLower === 'sdh golive' || 
                    rawLower === 'sudah go live' || rawLower === 'sdh go live'
                );
            
                let isOdpFull = (rawLower === 'full' || rawLower === 'odp full');

                if (isBelumGolive) {
                    specialInput.value = 'belum_golive';
                    document.getElementById('latitude').required = false;
                    document.getElementById('longitude').required = false;
                    document.getElementById('realisasi_line').required = false;
                    latLongContainer.style.display = 'none';
                    realisasiContainer.style.display = 'none';
                    container.innerHTML = '<p style="color:#d9534f; background:#fdfbfa; padding:10px; border-left:4px solid #d9534f;"><b>🚨 MODE KHUSUS: ODP Belum Go Live.</b><br><small>Sistem otomatis menutup tiket sebagai "Belum Go Live". Form lain diabaikan.</small></p>';
                    submitBtn.innerText = "Akhiri dengan Status Belum Go Live";
                    submitBtn.style.backgroundColor = "#dc3545";
                    return;
                } else if (isSudahGolive) {
                    specialInput.value = 'sudah_golive';
                    document.getElementById('latitude').required = false;
                    document.getElementById('longitude').required = false;
                    document.getElementById('realisasi_line').required = false;
                    latLongContainer.style.display = 'none';
                    realisasiContainer.style.display = 'none';
                    container.innerHTML = '<p style="color:#17a2b8; background:#f8f9fa; padding:10px; border-left:4px solid #17a2b8;"><b>✅ MODE KHUSUS: ODP Sudah Go Live.</b><br><small>Sistem otomatis menutup tiket sebagai "Sudah Go Live". Form lain diabaikan.</small></p>';
                    submitBtn.innerText = "Akhiri dengan Status Sudah Go Live";
                    submitBtn.style.backgroundColor = "#17a2b8";
                    return;
                } else if (isOdpFull) {
                    specialInput.value = 'full';
                    document.getElementById('latitude').required = false;
                    document.getElementById('longitude').required = false;
                    document.getElementById('realisasi_line').required = false;
                    latLongContainer.style.display = 'none';
                    realisasiContainer.style.display = 'none';
                    container.innerHTML = '<p style="color:#6f42c1; background:#f8f9fa; padding:10px; border-left:4px solid #6f42c1;"><b>🚨 MODE KHUSUS: ODP Full.</b><br><small>Sistem otomatis menutup tiket sebagai "Full". Form lain diabaikan.</small></p>';
                    submitBtn.innerText = "Akhiri dengan Status ODP Full";
                    submitBtn.style.backgroundColor = "#6f42c1";
                    return;
                } else {
                    specialInput.value = '';
                    document.getElementById('latitude').required = true;
                    document.getElementById('longitude').required = true;
                    document.getElementById('realisasi_line').required = true;
                    latLongContainer.style.display = 'flex';
                    realisasiContainer.style.display = 'block';
                    submitBtn.innerText = "Submit Selesai";
                    submitBtn.style.backgroundColor = "#28a745";
                }

                // 2. Parsing Normal (Mengekstrak Lat/Long dan Panel/Port)
                document.getElementById('latitude').value = '';
                document.getElementById('longitude').value = '';
                document.getElementById('realisasi_line').value = reqLine;
                container.innerHTML = ''; 
                
                let isLineSetFromPort = false;
                let totalPorts = 0;
                
                // Mencegah duplikat kombinasi Panel dan Port (contoh: "P1-PT2")
                let usedCombinations = new Set(); 
                
                const coordRegex = /(-?\d+\.\d+)[\s,]+(-?\d+\.\d+)/;
                const coords = raw.match(coordRegex);
                if (coords) {
                    document.getElementById('latitude').value = coords[1];
                    document.getElementById('longitude').value = coords[2];
                    document.getElementById('realisasi_line').value = 1; 
                }                
                
                const panelPortRegex = /p[a-e]nel\s+(\d+)\s+port\s+([\d,\s]+)/gi;
                let match;
                let foundPanelPort = false;                
                
                while ((match = panelPortRegex.exec(raw)) !== null) {
                    foundPanelPort = true;
                    let panelNum = match[1];
                    let rawPorts = match[2];                    
                    
                    let portArray = rawPorts.split(',').map(item => item.trim()).filter(item => item !== "");
                    let uniquePortsInMatch = [];

                    // Mengecek setiap port pada panel ini, apakah sudah ada di Set usedCombinations
                    portArray.forEach(prt => {
                        let comboKey = `P${panelNum}-PT${prt}`; // Kunci unik misal: P1-PT2
                        if (!usedCombinations.has(comboKey)) {
                            usedCombinations.add(comboKey);
                            uniquePortsInMatch.push(prt);
                            totalPorts++; // Tambah total port rill
                        }
                    });

                    // Hanya tambahkan ke layar jika ada port unik yang baru ditemukan
                    if (uniquePortsInMatch.length > 0) {
                        let formattedPorts = uniquePortsInMatch.join(', ');
                        container.innerHTML += `
                            <div class="panel-port-row" style="background-color: #f1f5f9; padding: 10px; margin-bottom: 10px; border-left: 4px solid #17a2b8; border-radius: 4px;">
                                <label><b>Panel ${panelNum}</b></label><br>
                                <input type="hidden" name="panel[]" value="${panelNum}" class="panel-input">
                                <label style="font-size: 14px; color: #555;">Port:</label><br>
                                <input type="text" name="port[]" value="${formattedPorts}" class="port-input"><br>
                            </div>
                        `;
                    }
                }                
                
                if (foundPanelPort) {
                    document.getElementById('realisasi_line').value = totalPorts;
                    isLineSetFromPort = true;
                } else {
                    // Fallback jika teknisi tidak mengetik Panel/Port sama sekali
                    container.innerHTML = `
                        <div class="panel-port-row">
                            <label>Panel:</label><br>
                            <input type="text" name="panel[]" class="panel-input"><br>
                            <label>Port:</label><br>
                            <input type="text" name="port[]" class="port-input" placeholder="Contoh: 1, 4, 5"><br>
                        </div>
                    `;
                }                
                
                if (!isLineSetFromPort) {
                    const lineRegex = /(\d+)\s*line/i;
                    const lineMatch = raw.match(lineRegex);
                    if (lineMatch) document.getElementById('realisasi_line').value = lineMatch[1];
                }
            }

            function validasiForm() {
                const jenisPermintaan = "<?php echo $ticket['jenis_permintaan']; ?>";
                const specialStatus = document.getElementById('special_status').value;
                
                if (specialStatus === 'belum_golive' || specialStatus === 'full' || specialStatus === 'sudah_golive') {
                    // VALIDASI HANYA UNTUK PEMUNCULAN (Full & Sudah Golive)
                    if ((specialStatus === 'full' || specialStatus === 'sudah_golive') && jenisPermintaan !== 'pemunculan') {
                        let lblError = (specialStatus === 'full') ? 'ODP Full' : 'Sudah Go Live';
                        alert(`GAGAL: Status Khusus (${lblError}) hanya berlaku untuk tiket PEMUNCULAN!`);
                        return false;
                    }
                    
                    let lbl = '';
                    if (specialStatus === 'belum_golive') lbl = 'BELUM GO LIVE';
                    else if (specialStatus === 'sudah_golive') lbl = 'SUDAH GO LIVE';
                    else lbl = 'FULL';
                    
                    return confirm(`Yakin ingin mengakhiri tiket ini dengan status: ${lbl}?`);
                }

                const reqLine = parseInt("<?php echo $ticket['req_line']; ?>");
                const realisasiLine = parseInt(document.getElementById('realisasi_line').value);
                const panelInputs = document.querySelectorAll('.panel-input');
                const portInputs = document.querySelectorAll('.port-input');                
                
                if (jenisPermintaan === 'pengosongan') {
                    let adaKosong = false;
                    for (let i = 0; i < panelInputs.length; i++) {
                        if (panelInputs[i].value.trim() === '' || portInputs[i].value.trim() === '') {
                            adaKosong = true; break;
                        }
                    }
                    if (adaKosong) {
                        alert(`GAGAL: Untuk tiket Pengosongan, kolom Panel dan Port WAJIB diisi!`);
                        return false; 
                    }
                    if (realisasiLine !== reqLine) {
                        alert(`GAGAL: Ini tiket Pengosongan untuk ${reqLine} line. Anda mengisi ${realisasiLine} line. Jumlahnya HARUS SAMA!`);
                        return false; 
                    }
                }                
                
                if (jenisPermintaan === 'pemunculan') {
                    if (realisasiLine > reqLine) {
                        alert(`GAGAL: Request Pemunculan hanya ${reqLine} line. Anda tidak boleh mengisi lebih dari itu (Anda mengisi ${realisasiLine})!`);
                        return false;
                    }
                }
                
                return confirm('Data sudah sesuai. Yakin ingin submit dan selesaikan tiket ini?');
            }
        </script>

    <?php elseif ($page == 'riwayat'): ?>
        <h3>Riwayat Pekerjaan Saya</h3>
        
        <form id="filterFormDaman" method="GET" action="daman_dashboard.php" style="margin-bottom: 15px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
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
                <label style="font-size: 14px; font-weight: bold;">Tanggal Request:</label><br>
                <input type="date" name="filter_tanggal" value="<?php echo isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : ''; ?>" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc; margin-top: 5px;">
            </div>
            <div>
                <label style="font-size: 14px; font-weight: bold;">Pencarian:</label><br>
                <input type="text" name="search" placeholder="Cari No Tiket, ODP, Pemohon..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>" style="padding: 6px; border-radius: 4px; border: 1px solid #ccc; width: 200px; margin-top: 5px; margin-bottom: 0;">
            </div>
            <div>
                <button type="submit" style="padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Cari</button>
                <a href="daman_dashboard.php?page=riwayat" style="padding: 7px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 13.5px;">Reset</a>
                
                <button type="button" onclick="cetakLaporanDaman()" style="padding: 7px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13.5px; margin-left: 10px;">
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
                    <th>Waktu Selesai</th>
                    <th>Status Akhir</th>
                    <th>Bukti Valins</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $filter_riwayat = "";
                if (isset($_GET['filter_jenis']) && $_GET['filter_jenis'] != "") {
                    $jenis = $conn->real_escape_string($_GET['filter_jenis']);
                    $filter_riwayat .= " AND t.jenis_permintaan = '$jenis'"; 
                }
                if (isset($_GET['filter_tanggal']) && $_GET['filter_tanggal'] != "") {
                    $tanggal = $conn->real_escape_string($_GET['filter_tanggal']);
                    $filter_riwayat .= " AND DATE(t.waktu_request) = '$tanggal'"; 
                }
                // 3. Query Utama: Gunakan $search_filter setelah GROUP BY
                // 1. Definisikan variabel penampung filter HAVING
                $search_filter = "";
                if (isset($_GET['search']) && $_GET['search'] != "") {
                    $search = $conn->real_escape_string($_GET['search']);
                    // Gunakan CONCAT agar bisa mencari gabungan Lat & Long seperti yang tampil di tabel
                    // Gunakan REPLACE agar bisa mencari status dengan spasi (misal: "belum golive")
                    $search_filter = " HAVING (no_tiket LIKE '%$search%' 
                                        OR nama_odp LIKE '%$search%' 
                                        OR nama_pemohon LIKE '%$search%' 
                                        OR valins_id LIKE '%$search%'
                                        OR CONCAT(latitude, ', ', longitude) LIKE '%$search%'
                                        OR waktu_request LIKE '%$search%'
                                        OR waktu_selesai LIKE '%$search%'
                                        OR realisasi_line LIKE '%$search%'
                                        OR REPLACE(status, '_', ' ') LIKE '%$search%'
                                        OR port_info LIKE '%$search%')";
                }                
                
                // 2. Query Utama: Masukkan $search_filter SETELAH GROUP BY
                $sql_riwayat = "SELECT t.*, 
                                GROUP_CONCAT(CONCAT('P:', tp.panel, ' / Pt:', tp.port) SEPARATOR ' | ') AS port_info 
                                FROM tickets t 
                                LEFT JOIN ticket_ports tp ON t.id = tp.ticket_id 
                                WHERE t.taken_by = $user_id 
                                AND t.status IN ('selesai', 'valins_ulang', 'belum_golive', 'sudah_golive', 'full') 
                                $filter_riwayat
                                GROUP BY t.id 
                                $search_filter
                                ORDER BY t.waktu_selesai DESC";            
                
                $res_riwayat = $conn->query($sql_riwayat);                
                
                if ($res_riwayat->num_rows > 0):
                    while ($row = $res_riwayat->fetch_assoc()):
                ?>
                    <tr>
                        <td><b><?php echo $row['no_tiket']; ?></b></td>
                        <td><?php echo $row['waktu_request']; ?></td>
                        <td><?php echo $row['nama_pemohon']; ?></td>
                        <td><?php echo ucfirst($row['jenis_permintaan']); ?></td>
                        <td><?php echo $row['nama_odp']; ?></td>
                        <td><?php echo $row['valins_id'] ? $row['valins_id'] : '-'; ?></td>
                        <td><?php echo $row['latitude'] ? $row['latitude'] . ', ' . $row['longitude'] : '-'; ?></td>
                        <td><?php echo $row['port_info'] ? $row['port_info'] : '-'; ?></td> <td style="text-align: center;"><?php echo $row['realisasi_line'] ? $row['realisasi_line'] : '-'; ?></td>
                        <td><?php echo $row['waktu_selesai'] ? $row['waktu_selesai'] : '-'; ?></td>
                        <td>
                            <?php 
                            if($row['status'] == 'selesai') {
                                echo '<span class="status-selesai">Selesai</span>';
                            } elseif($row['status'] == 'valins_ulang') {
                                echo '<span class="status-valins">Valins Ulang</span>';
                            } elseif($row['status'] == 'belum_golive') {
                                echo '<span style="color:#d9534f; font-weight:bold;">Belum Go Live</span>';
                            } elseif($row['status'] == 'sudah_golive') {
                                echo '<span style="color:#17a2b8; font-weight:bold;">Sudah Go Live</span>';
                            } elseif($row['status'] == 'full') {
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
                <?php endwhile; else: ?>
                    <tr><td colspan="12" style="text-align:center;">Belum ada riwayat pekerjaan yang sesuai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>    

    <div id="imageModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8);">
        <span onclick="tutupModal()" style="position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
        <img class="modal-content" id="img01" style="margin: auto; display: block; width: 80%; max-width: 700px; margin-top: 5%;">
    </div>    
    
    <script>
        document.getElementById("filterFormList")?.addEventListener("submit", function (e) {
            let inputs = this.querySelectorAll("input, select");
            inputs.forEach(input => { if (!input.value) input.removeAttribute("name"); });
        });
        document.getElementById("filterFormDaman")?.addEventListener("submit", function (e) {
            let inputs = this.querySelectorAll("input, select");
            inputs.forEach(input => { if (!input.value) input.removeAttribute("name"); });
        });

        function bukaModal(imgSrc) {
            document.getElementById("imageModal").style.display = "block";
            document.getElementById("img01").src = imgSrc;
        }        
        function tutupModal() {
            document.getElementById("imageModal").style.display = "none";
        }        
        
        window.onclick = function(event) {
            var modal = document.getElementById("imageModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        function cetakLaporanDaman() {
            const urlParams = new URLSearchParams(window.location.search);
            const destUrl = "cetak_laporan_daman.php?" + urlParams.toString();
            window.open(destUrl, '_blank');
        }
    </script>
</body>
</html>