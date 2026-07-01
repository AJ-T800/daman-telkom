<?php
session_start();
require 'koneksi.php';

// Modifikasi: Sesuaikan pengecekan session berdasarkan login.php baru
if (!isset($_SESSION['id_pengguna']) || $_SESSION['peran'] !== 'daman') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id_pengguna']; // Modifikasi variabel session
date_default_timezone_set('Asia/Jakarta');

// ==========================================
// 1. LOGIKA AMBIL TIKET
// ==========================================
if (isset($_GET['ambil'])) {
    $id_tiket = intval($_GET['ambil']);
    // Modifikasi: tabel tiket, id_tiket
    $cek = $conn->prepare("SELECT status FROM tiket WHERE id_tiket = ? AND status = 'pending'");
    $cek->bind_param("i", $id_tiket);
    $cek->execute();
    
    if ($cek->get_result()->num_rows > 0) {
        // Modifikasi: tabel tiket, diambil_oleh, id_tiket
        $update = $conn->prepare("UPDATE tiket SET status = 'on_progress', diambil_oleh = ? WHERE id_tiket = ?");
        $update->bind_param("ii", $user_id, $id_tiket);
        $update->execute();
        header("Location: daftar_permintaan.php?page=form&id=" . $id_tiket);
    } else {
        echo "<script>alert('Maaf, tiket ini sudah diambil rekan lain!'); window.location.href='daftar_permintaan.php';</script>";
    }
    exit();
}

// ==========================================
// 2. LOGIKA BATAL TIKET
// ==========================================
if (isset($_GET['batal'])) {
    $id_tiket = intval($_GET['batal']);
    // Modifikasi: tabel tiket, diambil_oleh, id_tiket
    $update = $conn->prepare("UPDATE tiket SET status = 'pending', diambil_oleh = NULL WHERE id_tiket = ? AND diambil_oleh = ?");
    $update->bind_param("ii", $id_tiket, $user_id);
    $update->execute();
    header("Location: daftar_permintaan.php");
    exit();
}

// ==========================================
// 3. LOGIKA SUBMIT FORM (SELESAI / STATUS KHUSUS)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['selesaikan_tiket'])) {
    $id_tiket = $_POST['id_tiket'];
    $waktu_selesai = date('Y-m-d H:i:s');
    
    // Modifikasi: Query Join menggunakan nama tabel dan kolom baru
    $stmt_get = $conn->prepare("
        SELECT t.jenis_permintaan, t.permintaan_line, tech.id_chat, log.id_pesan_tg 
        FROM tiket t
        JOIN teknisi tech ON t.id_teknisi = tech.id_teknisi
        LEFT JOIN log_telegram log ON t.id_tiket = log.id_tiket
        WHERE t.id_tiket = ?
    ");
    $stmt_get->bind_param("i", $id_tiket);
    $stmt_get->execute();
    $ticket_data = $stmt_get->get_result()->fetch_assoc();
    
    $jenis = $ticket_data['jenis_permintaan'];
    $req_line = (int)$ticket_data['permintaan_line']; 
    
    $bot_token = "8725441277:AAHJ_hWS0DcCxv_ZjBBqRNQJSun8ywSNlTk"; 
    $chat_id = $ticket_data['id_chat']; 
    $reply_to = $ticket_data['id_pesan_tg']; 

    // --- CEK STATUS KHUSUS (BELUM GOLIVE / FULL / SUDAH GOLIVE) ---
    $special_status = isset($_POST['special_status']) ? $_POST['special_status'] : '';
    
    if ($special_status === 'belum_golive' || $special_status === 'full' || $special_status === 'sudah_golive') {
        if (($special_status === 'full' || $special_status === 'sudah_golive') && $jenis != 'pemunculan') {
            $label_error = ($special_status === 'full') ? 'ODP Full' : 'Sudah Go Live';
            echo "<script>alert('Sistem Menolak: Status $label_error hanya berlaku untuk tiket Pemunculan!'); window.history.back();</script>";
            exit();
        }
        
        $update = $conn->prepare("UPDATE tiket SET status = ?, waktu_selesai = ? WHERE id_tiket = ? AND diambil_oleh = ?");
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
            
            echo "<script>alert('Tiket diakhiri dengan status: ".strtoupper(str_replace('_', ' ', $special_status)).". Bot berhasil membalas!'); window.location.href='daftar_permintaan.php';</script>";
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
    
    // Modifikasi: UPDATE tabel tiket
    $update = $conn->prepare("UPDATE tiket SET status = 'selesai', latitude = ?, longitude = ?, realisasi_line = ?, waktu_selesai = ? WHERE id_tiket = ? AND diambil_oleh = ?");
    $update->bind_param("ssisii", $lat, $long, $realisasi, $waktu_selesai, $id_tiket, $user_id);
    
    if ($update->execute()) {
        // --- KODE PERBAIKAN MULAI DARI SINI ---
        $stmt_hapus = $conn->prepare("DELETE FROM port_tiket WHERE id_tiket = ?");
        $stmt_hapus->bind_param("i", $id_tiket);
        $stmt_hapus->execute();
        // --- BATAS KODE PERBAIKAN ---
        
        $stmt_port = $conn->prepare("INSERT INTO port_tiket (id_tiket, panel, port) VALUES (?, ?, ?)");
        
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
        
        echo "<script>alert('Mantap! Tiket berhasil diselesaikan.'); window.location.href='daftar_permintaan.php';</script>";
    }
}

// ==========================================
// 4. LOGIKA VALIDASI ULANG
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['validasi_ulang'])) {
    $id_tiket = $_POST['id_tiket'];
    $catatan = trim($_POST['catatan_valins']);
    
    $stmt_get = $conn->prepare("
        SELECT t.no_tiket, tech.id_chat, log.id_pesan_tg 
        FROM tiket t
        JOIN teknisi tech ON t.id_teknisi = tech.id_teknisi
        LEFT JOIN log_telegram log ON t.id_tiket = log.id_tiket
        WHERE t.id_tiket = ?
    ");
    $stmt_get->bind_param("i", $id_tiket);
    $stmt_get->execute();
    $ticket_data = $stmt_get->get_result()->fetch_assoc();
    
    $bot_token = "8725441277:AAHJ_hWS0DcCxv_ZjBBqRNQJSun8ywSNlTk"; 
    $chat_id = $ticket_data['id_chat']; 
    $reply_to = $ticket_data['id_pesan_tg']; 
    
    $pesan_caption = "❌ *VALIDASI ULANG PENGOSONGAN*\nTiket: ".$ticket_data['no_tiket']."\nCatatan Daman: " . $catatan . "\nMohon perbaiki gambar valins pada ODP terkait.";
    
    if (isset($_FILES['foto_valins']) && !empty($_FILES['foto_valins']['name'][0])) {
        $files = $_FILES['foto_valins'];
        $uploaded_paths = [];
        $attachments = [];
        $media = [];
        
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $img_count = 0;
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] == 0) {
                $file_tmp = $files['tmp_name'][$i];
                $file_type = $files['type'][$i];
                $file_name = $files['name'][$i];
                
                $ekstensi = pathinfo($file_name, PATHINFO_EXTENSION);
                $nama_file_baru = "bukti_valins_" . $id_tiket . "_" . time() . "_" . $i . "." . $ekstensi;
                $target_file = $target_dir . $nama_file_baru;
                
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $uploaded_paths[] = $target_file;
                    
                    $attachments["photo_$img_count"] = new CURLFile(realpath($target_file), $file_type, $file_name);
                    $media[] = [
                        'type' => 'photo',
                        'media' => "attach://photo_$img_count",
                        'caption' => $img_count === 0 ? $pesan_caption : '', 
                        'parse_mode' => 'Markdown'
                    ];
                    $img_count++;
                }
            }
        }
        
        if (count($uploaded_paths) > 0) {
            if (count($uploaded_paths) === 1) {
                $url = "https://api.telegram.org/bot$bot_token/sendPhoto";
                $data = [
                    'chat_id' => $chat_id,
                    'photo' => $attachments["photo_0"],
                    'caption' => $pesan_caption,
                    'parse_mode' => 'Markdown'
                ];
                if (!empty($reply_to) && $reply_to != 0) {
                    $data['reply_to_message_id'] = $reply_to;
                }
            } else {
                $url = "https://api.telegram.org/bot$bot_token/sendMediaGroup";
                $data = [
                    'chat_id' => $chat_id,
                    'media' => json_encode($media)
                ];
                if (!empty($reply_to) && $reply_to != 0) {
                    $data['reply_to_message_id'] = $reply_to;
                }
                $data = array_merge($data, $attachments);
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $stmt_ins = $conn->prepare("INSERT INTO gambar_validasi_tiket (id_tiket, lokasi_file) VALUES (?, ?)");
            foreach ($uploaded_paths as $path) {
                $stmt_ins->bind_param("is", $id_tiket, $path);
                $stmt_ins->execute();
            }
            
            $update = $conn->prepare("UPDATE tiket SET status = 'valins_ulang', waktu_selesai = NOW() WHERE id_tiket = ?");
            $update->bind_param("i", $id_tiket);
            $update->execute();
            
            echo "<script>alert('Perintah Validasi Ulang beserta seluruh gambar telah dikirim ke Telegram dan disimpan di sistem!'); window.location.href='daftar_permintaan.php';</script>";
            exit();
        } else {
            echo "<script>alert('Gagal! Gambar tidak valid atau gagal diunggah ke server.'); window.history.back();</script>";
            exit();
        }
    } else {
        echo "<script>alert('Gagal! Anda wajib melampirkan minimal 1 gambar bukti untuk Validasi Ulang.'); window.history.back();</script>";
        exit();
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'list';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Daman - Infranexia</title>
    <link rel="stylesheet" href="css/daftar_permintaan.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="main-container">
    <?php if ($page == 'list'): ?>
        <div class="dashboard-header">
            <h2 class="greeting">HALO DAMAN, <?php echo strtoupper(isset($_SESSION['nama_pengguna']) ? $_SESSION['nama_pengguna'] : 'RAFLY'); ?>!</h2>
            <img src="css/assets/infranexia.png" alt="Infranexia Logo" class="header-logo">
        </div>

        <form id="filterFormList" method="GET" action="" class="filter-box">
            <div class="filter-group">
                <label>Jenis Permintaan:</label>
                <select name="filter_jenis">
                    <option value="">Semua</option>
                    <option value="pemunculan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pemunculan') echo 'selected'; ?>>Pemunculan</option>
                    <option value="pengosongan" <?php if(isset($_GET['filter_jenis']) && $_GET['filter_jenis']=='pengosongan') echo 'selected'; ?>>Pengosongan</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Tanggal Request:</label>
                <input type="date" name="filter_tanggal" value="<?php echo isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : ''; ?>">
            </div>
            
            <div class="filter-group">
                <label>Pencarian:</label>
                <input type="text" name="search" placeholder="Cari No Tiket, ODP, Pemohon..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-cari">
                    <span class="material-symbols-outlined">search</span> Cari
                </button>
                
                <a href="daftar_permintaan.php" class="btn-reset">
                    <span class="material-symbols-outlined">sync</span> Reset
                </a>
            </div>
        </form>

        <table class="table-custom">
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
                    $filter .= " AND t.jenis_permintaan = '$jenis'"; 
                }
                if (isset($_GET['filter_tanggal']) && $_GET['filter_tanggal'] != "") {
                    $tanggal = $conn->real_escape_string($_GET['filter_tanggal']);
                    $filter .= " AND DATE(t.waktu_permintaan) = '$tanggal'"; 
                }
                if (isset($_GET['search']) && $_GET['search'] != "") {
                    $search = $conn->real_escape_string($_GET['search']);
                    $filter .= " AND (t.no_tiket LIKE '%$search%' OR t.nama_odp LIKE '%$search%' OR tech.nama_pemohon LIKE '%$search%' OR t.waktu_permintaan LIKE '%$search%' OR v.id_valins LIKE '%$search%' OR t.permintaan_line LIKE '%$search%')";
                }
                
                $sql = "SELECT t.*, tech.nama_pemohon, v.id_valins 
                        FROM tiket t 
                        JOIN teknisi tech ON t.id_teknisi = tech.id_teknisi 
                        LEFT JOIN validasi_tiket v ON t.id_tiket = v.id_tiket 
                        WHERE (t.status = 'pending' OR (t.status = 'on_progress' AND t.diambil_oleh = $user_id)) $filter 
                        ORDER BY t.waktu_permintaan ASC";
                        
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><b><?php echo $row['no_tiket']; ?></b></td>
                    <td><?php echo $row['waktu_permintaan']; ?></td> 
                    <td><?php echo $row['nama_pemohon']; ?></td>
                    <td><b><?php echo strtoupper($row['jenis_permintaan']); ?></b></td>
                    <td>
                        <?php 
                        if (strpos($row['nama_odp'], '(CEK GOLIVE)') !== false) {
                            // Hilangkan teks penanda di DB saat tampil, lalu gantikan dengan badge visual cyan/biru muda
                            echo str_replace(' (CEK GOLIVE)', '', $row['nama_odp']) . ' <span class="badge-jenis" style="background-color: #00bcd4; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px; display: inline-block;">CEK GOLIVE</span>';
                        } else {
                            echo $row['nama_odp']; 
                        }
                        ?>
                    </td>
                    <td><?php echo isset($row['id_valins']) ? $row['id_valins'] : '-'; ?></td>
                    <td><?php echo $row['permintaan_line']; ?></td>
                    <td>
                        <?php if ($row['status'] == 'pending'): ?>
                            <a href="?ambil=<?php echo $row['id_tiket']; ?>" class="btn-ambil" onclick="return confirm('Ambil tiket ini untuk dikerjakan?')">Ambil</a>
                        <?php elseif ($row['status'] == 'on_progress' && $row['diambil_oleh'] == $user_id): ?>
                            <a href="?page=form&id=<?php echo $row['id_tiket']; ?>" class="btn-ambil" style="background-color:#3B5B8D;">Lanjutkan</a>
                            <a href="?batal=<?php echo $row['id_tiket']; ?>" class="btn-reset" onclick="return confirm('Yakin ingin membatalkan pengerjaan tiket ini?')">Batal</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" style="text-align:center; padding: 30px; color: #777;">Tidak ada permintaan saat ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php 
    elseif ($page == 'form' && isset($_GET['id'])): 
        $id_tiket = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM tiket WHERE id_tiket = ? AND diambil_oleh = ?");
        $stmt->bind_param("ii", $id_tiket, $user_id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        
        if (!$ticket) {
            echo "Tiket tidak ditemukan atau bukan milik Anda.";
            exit();
        }
    ?>
        <h3 class="page-title">Pengerjaan Tiket #<?php echo $ticket['no_tiket']; ?></h3>
        
        <div class="ticket-info-banner">
            <div><b>Jenis:</b> <span class="badge-jenis"><?php echo strtoupper($ticket['jenis_permintaan']); ?></span></div>
            <div><b>Nama ODP:</b> 
                <?php 
                if (strpos($ticket['nama_odp'], '(CEK GOLIVE)') !== false) {
                    echo str_replace(' (CEK GOLIVE)', '', $ticket['nama_odp']) . ' <span class="badge-jenis" style="background-color: #00bcd4; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px;">CEK GOLIVE</span>';
                } else {
                    echo $ticket['nama_odp']; 
                }
                ?>
            </div>
            <div><b>Req Line:</b> <?php echo $ticket['permintaan_line']; ?> Line</div>
        </div>
        
        <div class="attachment-box">
            <h4>📸 Lampiran Gambar dari Teknisi:</h4>
            <div class="img-gallery">
            <?php
            $stmt_img = $conn->query("SELECT lokasi_file FROM gambar_tiket WHERE id_tiket = $id_tiket");
            if ($stmt_img->num_rows > 0) {
                while ($img = $stmt_img->fetch_assoc()) {
                    echo "<img src='".$img['lokasi_file']."' class='img-preview' onclick='bukaModal(this.src)'>";
                }
            } else {
                echo "<p style='color: #6c757d; font-style: italic; font-size: 14px;'>Teknisi tidak melampirkan gambar pada tiket ini.</p>";
            }
            ?>
            </div>
        </div>

        <div class="form-wrapper">
            <div class="form-card main-form">
                <h4>✅ Selesaikan Tiket</h4>
                
                <div class="auto-fill-box">
                    <label>⚡ [Auto-Fill] Paste format balasan Telegram di sini:</label>
                    <textarea id="raw_input" rows="3" class="form-control" placeholder="Contoh: -4.119934456, 104.16210061 panel 1 port 1, 4, 5 ATAU ketik 'odp full' / 'belum golive' / 'sudah golive'..." oninput="parseInput()"></textarea>                
                </div>
                
                <form method="POST" action="" onsubmit="return validasiForm()">
                    <input type="hidden" name="id_tiket" value="<?php echo $ticket['id_tiket']; ?>">
                    <input type="hidden" id="special_status" name="special_status" value="">                    

                    <div id="latlong_container" class="latlong-container">
                        <div>
                            <label class="form-label">Latitude:</label>
                            <input type="text" id="latitude" name="latitude" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Longitude:</label>
                            <input type="text" id="longitude" name="longitude" class="form-control" required>
                        </div>
                    </div>

                    <div id="panel_port_container">
                        <div class="panel-port-row">
                            <label class="form-label">Panel:</label>
                            <input type="text" name="panel[]" class="form-control panel-input">
                            <label class="form-label">Port:</label>
                            <input type="text" name="port[]" class="form-control port-input" placeholder="Contoh: 1, 4, 5">
                        </div>
                    </div>
                    
                    <div id="realisasi_container">
                        <label class="form-label">Realisasi Line (Sesuai yang tersedia):</label>
                        <input type="number" id="realisasi_line" name="realisasi_line" class="form-control" value="<?php echo $ticket['permintaan_line']; ?>" required>
                    </div>                    

                    <div style="margin-top: 25px; display: flex; flex-direction: column; gap: 10px;">
                        <button type="submit" id="btn_submit_selesai" name="selesaikan_tiket" class="btn-ambil btn-block btn-lg">Submit Selesai</button>
                        <a href="?batal=<?php echo $ticket['id_tiket']; ?>" class="btn-secondary btn-block" onclick="return confirm('Batal mengerjakan?')">Tinggalkan Form Sementara</a>
                    </div>
                </form>
            </div>

            <?php if ($ticket['jenis_permintaan'] == 'pengosongan'): ?>
            <div class="form-card warning-form">
                <h4 style="color: #856404;">⚠️ Minta Validasi Ulang</h4>
                <p style="font-size: 13px; color: #666; margin-bottom: 20px; line-height: 1.5;">Gunakan form ini jika gambar Valins yang diunggah teknisi salah/buram. Anda bisa melampirkan banyak gambar langsung.</p>                
                
                <form method="POST" action="" enctype="multipart/form-data" onsubmit="return confirm('Yakin ingin menolak dan meminta teknisi melakukan Validasi Ulang?')">
                    <input type="hidden" name="id_tiket" value="<?php echo $ticket['id_tiket']; ?>">
                    
                    <label class="form-label">Upload Bukti Gambar Salah:</label>
                    
                    <div id="container-foto-valins" style="margin-top: 5px;">
                        <div class="foto-input-row" style="margin-bottom: 15px; background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e9ecef;">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="file" name="foto_valins[]" accept="image/*" class="form-control" style="margin-bottom: 0; padding: 8px;" required multiple onchange="previewGambarValins(this)">
                            </div>
                            <div class="preview-container-valins"></div>
                        </div>
                    </div>
                    
                    <button type="button" id="btn-tambah-gambar" class="btn-secondary" style="font-size: 12px; padding: 6px 12px; margin-bottom: 20px;">+ Tambah File Input Gambar</button>
                    
                    <label class="form-label">Catatan untuk Teknisi:</label>
                    <textarea name="catatan_valins" rows="4" class="form-control" placeholder="Contoh: Valins buram, tag ODP tidak terlihat jelas, tolong foto ulang..." required></textarea>                    
                    
                    <button type="submit" name="validasi_ulang" class="btn-danger btn-block btn-lg" style="margin-top: 10px;">Kirim Permintaan Validasi Ulang</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <script>
            function parseInput() {
                const raw = document.getElementById('raw_input').value;
                const rawLower = raw.toLowerCase().trim(); 
                const reqLine = parseInt("<?php echo $ticket['permintaan_line']; ?>");
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
                    container.innerHTML = '<div style="color:#721c24; background:#f8d7da; padding:15px; border-left:4px solid #dc3545; border-radius: 4px; margin-bottom: 15px;"><b>🚨 MODE KHUSUS: ODP Belum Go Live.</b><br><span style="font-size: 13px;">Sistem otomatis menutup tiket sebagai "Belum Go Live". Form lain diabaikan.</span></div>';
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
                    container.innerHTML = '<div style="color:#0c5460; background:#d1ecf1; padding:15px; border-left:4px solid #17a2b8; border-radius: 4px; margin-bottom: 15px;"><b>✅ MODE KHUSUS: ODP Sudah Go Live.</b><br><span style="font-size: 13px;">Sistem otomatis menutup tiket sebagai "Sudah Go Live". Form lain diabaikan.</span></div>';
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
                    container.innerHTML = '<div style="color:#383d41; background:#e2e3e5; padding:15px; border-left:4px solid #6c757d; border-radius: 4px; margin-bottom: 15px;"><b>🚨 MODE KHUSUS: ODP Full.</b><br><span style="font-size: 13px;">Sistem otomatis menutup tiket sebagai "Full". Form lain diabaikan.</span></div>';
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
                    submitBtn.style.backgroundColor = "#28A745";
                }

                // 2. Parsing Normal (Mengekstrak Lat/Long dan Panel/Port)
                document.getElementById('latitude').value = '';
                document.getElementById('longitude').value = '';
                document.getElementById('realisasi_line').value = reqLine;
                container.innerHTML = ''; 
                
                let isLineSetFromPort = false;
                let totalPorts = 0;
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

                    portArray.forEach(prt => {
                        let comboKey = `P${panelNum}-PT${prt}`;
                        if (!usedCombinations.has(comboKey)) {
                            usedCombinations.add(comboKey);
                            uniquePortsInMatch.push(prt);
                            totalPorts++;
                        }
                    });

                    if (uniquePortsInMatch.length > 0) {
                        let formattedPorts = uniquePortsInMatch.join(', ');
                        container.innerHTML += `
                            <div class="panel-port-row" style="border-left: 4px solid #17a2b8;">
                                <label class="form-label" style="color: #0056b3; font-size: 14px;">Panel ${panelNum}</label>
                                <input type="hidden" name="panel[]" value="${panelNum}" class="panel-input">
                                <label class="form-label" style="margin-top: 8px;">Port:</label>
                                <input type="text" name="port[]" value="${formattedPorts}" class="form-control port-input" style="margin-bottom:0;">
                            </div>
                        `;
                    }
                }                
                
                if (foundPanelPort) {
                    document.getElementById('realisasi_line').value = totalPorts;
                    isLineSetFromPort = true;
                } else {
                    container.innerHTML = `
                        <div class="panel-port-row">
                            <label class="form-label">Panel:</label>
                            <input type="text" name="panel[]" class="form-control panel-input">
                            <label class="form-label">Port:</label>
                            <input type="text" name="port[]" class="form-control port-input" placeholder="Contoh: 1, 4, 5">
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

                const reqLine = parseInt("<?php echo $ticket['permintaan_line']; ?>");
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

            // Fitur Tambah / Hapus Input File Validasi Ulang secara Dinamis
            // Fitur Tambah / Hapus Input File Validasi Ulang secara Dinamis (Dengan Fitur Preview)
            document.getElementById('btn-tambah-gambar')?.addEventListener('click', function() {
                const container = document.getElementById('container-foto-valins');
                const newRow = document.createElement('div');
                newRow.className = 'foto-input-row';
                newRow.style.marginBottom = '15px';
                newRow.style.background = '#fff';
                newRow.style.padding = '12px';
                newRow.style.borderRadius = '6px';
                newRow.style.border = '1px solid #e9ecef';
                
                newRow.innerHTML = `
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="file" name="foto_valins[]" accept="image/*" class="form-control" style="margin-bottom: 0; padding: 8px;" multiple onchange="previewGambarValins(this)">
                        <button type="button" class="btn-hapus-gambar btn-danger" style="padding: 9px 12px; font-size: 13px;">Hapus</button>
                    </div>
                    <div class="preview-container-valins"></div>
                `;
                container.appendChild(newRow);
            });

            // FUNGSI BARU: Untuk membaca file gambar lokal dan menampilkannya secara instan
            function previewGambarValins(input) {
                // Cari elemen pembungkus row terdekat
                const row = input.closest('.foto-input-row');
                const previewContainer = row.querySelector('.preview-container-valins');
                
                // Bersihkan pratinjau lama jika ada perubahan file baru
                previewContainer.innerHTML = ''; 
                
                if (input.files && input.files.length > 0) {
                    Array.from(input.files).forEach(file => {
                        // Validasi pengaman agar yang diproses hanya file gambar
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            
                            // Saat file berhasil dibaca oleh browser, cetak tag <img>
                            reader.onload = function(e) {
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.className = 'preview-thumb-valins';
                                
                                // Jika di-klik, gambar preview juga ikut memanfaatkan Modal Utama kita
                                img.onclick = function() { bukaModal(this.src); };
                                
                                previewContainer.appendChild(img);
                            }
                            reader.readAsDataURL(file);
                        }
                    });
                }
            }

            // Event delegation untuk menghapus row input foto
            // Event delegation untuk menghapus row input foto (Satu paket dengan gambarnya)
            document.getElementById('container-foto-valins')?.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-hapus-gambar')) {
                    // .closest() akan mencari bapak paling luar (.foto-input-row) sehingga terhapus total
                    e.target.closest('.foto-input-row').remove();
                }
            });
        </script>

    <?php endif; ?>
    
    <div id="imageModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.85); display: flex; align-items: center; justify-content: center;">
        <span onclick="tutupModal()" style="position: absolute; top: 20px; right: 40px; color: #fff; font-size: 45px; font-weight: bold; cursor: pointer;">&times;</span>
        <img class="modal-content" id="img01" style="max-width: 80%; max-height: 80%; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5);">
    </div>    
    
    <script>
        document.getElementById("filterFormList")?.addEventListener("submit", function (e) {
            let inputs = this.querySelectorAll("input, select");
            inputs.forEach(input => { if (!input.value) input.removeAttribute("name"); });
        });

        function bukaModal(imgSrc) {
            const modal = document.getElementById("imageModal");
            modal.style.display = "flex"; // Gunakan flex agar gambar ke tengah vertikal & horizontal
            document.getElementById("img01").src = imgSrc;
        }        
        function tutupModal() {
            document.getElementById("imageModal").style.display = "none";
        }        
        window.onclick = function(event) {
            const modal = document.getElementById("imageModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        
        // Agar modal tidak otomatis muncul saat halaman dimuat (hidden initially override)
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("imageModal").style.display = "none";
        });
    </script>

<?php if ($page == 'list'): ?>
    <script>
        // Set waktu cek data otomatis (15000 milidetik = 15 detik)
        const INTERVAL_CEK = 15000; 

        setInterval(function() {
            // Pengaman Utama: Cek jika user sedang memfokuskan input ketikan / filter
            const elemenAktif = document.activeElement;
            if (
                elemenAktif && (
                    elemenAktif.tagName === 'INPUT' || 
                    elemenAktif.tagName === 'SELECT' || 
                    elemenAktif.tagName === 'TEXTAREA'
                )
            ) {
                // Jangan lakukan fetch jika DAMAN sedang aktif mengetik pencarian/mengklik filter
                return; 
            }

            // Memanggil url halaman saat ini di latar belakang (Mempertahankan filter parameter URL yang aktif)
            fetch(window.location.href)
                .then(response => {
                    if (!response.ok) throw new Error('Jaringan bermasalah');
                    return response.text();
                })
                .then(html => {
                    // Konversi teks HTML respon menjadi bentuk DOM Object di memory
                    const parser = new DOMParser();
                    const dokumenBaru = parser.parseFromString(html, 'text/html');
                    
                    // Seleksi bagian inti baris tabel (tbody) yang baru dan yang lama
                    const tbodyBaru = dokumenBaru.querySelector('.table-custom tbody');
                    const tbodyLama = document.querySelector('.table-custom tbody');
                    
                    // Tukar isinya secara halus tanpa me-refresh jendela browser
                    if (tbodyBaru && tbodyLama) {
                        tbodyLama.innerHTML = tbodyBaru.innerHTML;
                    }
                })
                .catch(error => console.warn('Gagal memuat otomatis data tiket terbaru:', error));
        }, INTERVAL_CEK);
    </script>
    <?php endif; ?>
    ```


</body>
</html>