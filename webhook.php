<?php
require 'koneksi.php';

// 1. Masukkan Token Bot Telegram Anda
$bot_token = "8725441277:AAHJ_hWS0DcCxv_ZjBBqRNQJSun8ywSNlTk"; 

// 2. Menangkap data JSON dari Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

// 3. Cek apakah ini pesan baru (message) atau pesan yang diedit (edited_message)
$is_edit = false;
if (isset($update["message"])) {
    $msg_data = $update["message"];
} elseif (isset($update["edited_message"])) {
    $msg_data = $update["edited_message"];
    $is_edit = true; 
} else {
    exit;
}

$chat_id = $msg_data["chat"]["id"];
$message_id = $msg_data["message_id"];

$text = isset($msg_data["text"]) ? $msg_data["text"] : (isset($msg_data["caption"]) ? $msg_data["caption"] : '');
$nama_pemohon = isset($msg_data["from"]["username"]) ? "@" . $msg_data["from"]["username"] : $msg_data["from"]["first_name"];
$waktu_request = date('Y-m-d H:i:s');
$media_group_id = isset($msg_data["media_group_id"]) ? $msg_data["media_group_id"] : null;

// ==========================================
// CEK ATAU DAFTARKAN TEKNISI
// ==========================================
// Modifikasi: Tabel technicians -> teknisi, kolom id -> id_teknisi, chat_id -> id_chat
$stmt_tech = $conn->prepare("SELECT id_teknisi FROM teknisi WHERE id_chat = ?");
$stmt_tech->bind_param("s", $chat_id);
$stmt_tech->execute();
$res_tech = $stmt_tech->get_result();

if ($res_tech->num_rows > 0) {
    $row_tech = $res_tech->fetch_assoc();
    $technician_id = $row_tech['id_teknisi']; // Modifikasi key array
} else {
    // Modifikasi: Nama tabel dan kolom disesuaikan
    $stmt_ins_tech = $conn->prepare("INSERT INTO teknisi (id_chat, nama_pemohon) VALUES (?, ?)");
    $stmt_ins_tech->bind_param("ss", $chat_id, $nama_pemohon);
    $stmt_ins_tech->execute();
    $technician_id = $stmt_ins_tech->insert_id;
}


// ==========================================
// FUNGSI DOWNLOAD GAMBAR DARI TELEGRAM
// ==========================================
function downloadTelegramImage($bot_token, $file_id) {
    $url = "https://api.telegram.org/bot$bot_token/getFile?file_id=$file_id";
    $response = json_decode(file_get_contents($url), true);
    
    if ($response['ok']) {
        $file_path = $response['result']['file_path'];
        $download_url = "https://api.telegram.org/file/bot$bot_token/$file_path";
        
        $ekstensi = pathinfo($file_path, PATHINFO_EXTENSION);
        $nama_file_lokal = "uploads/img_" . time() . "_" . rand(1000, 9999) . "." . $ekstensi;
        
        if(file_put_contents($nama_file_lokal, file_get_contents($download_url))) {
            return $nama_file_lokal;
        }
    }
    return false;
}

// ==========================================
// DOWNLOAD GAMBAR JIKA ADA
// ==========================================
$local_path = null;
if (isset($msg_data['photo'])) {
    $photo_array = $msg_data['photo'];
    $highest_resolution = end($photo_array);
    $file_id = $highest_resolution['file_id'];
    
    $local_path = downloadTelegramImage($bot_token, $file_id);
}

// ==========================================
// LOGIKA MEMBUAT NOMOR TIKET OTOMATIS URUT HARIAN
// ==========================================
$tanggal_hari_ini = date('Ymd');
$prefix = "TKT-" . $tanggal_hari_ini . "-";

// Modifikasi: Nama tabel tickets -> tiket, kolom PK id -> id_tiket
$stmt_cek = $conn->prepare("SELECT no_tiket FROM tiket WHERE no_tiket LIKE ? ORDER BY id_tiket DESC LIMIT 1");
$like_param = $prefix . "%";
$stmt_cek->bind_param("s", $like_param);
$stmt_cek->execute();
$hasil_cek = $stmt_cek->get_result();

if ($hasil_cek->num_rows > 0) {
    $row = $hasil_cek->fetch_assoc();
    $tiket_terakhir = $row['no_tiket'];
    $urutan_terakhir = (int) substr($tiket_terakhir, -3); 
    $urutan_baru = $urutan_terakhir + 1; 
} else {
    $urutan_baru = 1;
}

$angka_urut = str_pad($urutan_baru, 3, '0', STR_PAD_LEFT);
$no_tiket_baru = $prefix . $angka_urut; 

// ==========================================
// FUNGSI VALIDASI FORMAT NAMA ODP
// ==========================================
function cekFormatODP($odp) {
    $odp = strtoupper($odp); 
    if (substr($odp, 0, 4) !== 'ODP-') {
        return "❌ Penulisan salah: Harus diawali dengan 'ODP-'.\nContoh: ODP-ABC...";
    }
    $parts = explode('/', $odp);
    if (count($parts) != 2) {
        return "❌ Penulisan salah: Harus ada tanda garis miring (/) untuk memisahkan huruf dan angka.\nContoh: ...DEF/123";
    }
    $kiri = $parts[0]; 
    $kanan = $parts[1]; 
    if (!preg_match('/^\d{1,3}$/', $kanan)) {
        return "❌ Penulisan salah pada letak: /$kanan\nBagian setelah garis miring (/) harus berupa angka dan maksimal 3 digit.";
    }
    $kiri_parts = explode('-', $kiri);
    if (count($kiri_parts) != 3) {
        return "❌ Penulisan salah: Format huruf kurang tepat. Harus ada dua tanda strip.\nContoh: ODP-ABC-DEF/...";
    }
    if (!preg_match('/^[A-Z]{1,3}$/', $kiri_parts[1])) {
        return "❌ Penulisan salah pada letak: -{$kiri_parts[1]}-\nBagian kode pertama setelah 'ODP-' harus murni huruf (tanpa angka/simbol) dan maksimal 3 karakter.";
    }
    if (!preg_match('/^[A-Z]{1,3}$/', $kiri_parts[2])) {
        return "❌ Penulisan salah pada letak: -{$kiri_parts[2]}/ \nBagian kode kedua sebelum garis miring harus murni huruf (tanpa angka/simbol) dan maksimal 3 karakter.";
    }
    return "OK";
}

// ==========================================
// A. LOGIKA PEMBATALAN TIKET (/batal)
// ==========================================
if (preg_match('/^\/batal/i', $text)) {
    // Cari dan hapus berdasarkan id_teknisi
    // Modifikasi: tickets -> tiket, technician_id -> id_teknisi, id -> id_tiket
    $stmt = $conn->prepare("DELETE FROM tiket WHERE id_teknisi = ? AND status = 'pending' ORDER BY id_tiket DESC LIMIT 1");
    $stmt->bind_param("i", $technician_id);
    $stmt->execute();
    
    $pesan = "✅ Tiket terakhir Anda berhasil dibatalkan dan dihapus dari sistem.";
    file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan) . "&reply_to_message_id=$message_id");
    exit;
}

// ==========================================
// B. LOGIKA PEMUNCULAN
// ==========================================
if (preg_match('/^\/req_muncul\s+([A-Za-z0-9\-\/]+)(?:\s+(?:untuk\s+)?(\d+)\s*line)?/i', $text, $matches)) {
    $jenis_permintaan = 'pemunculan';
    $nama_odp = strtoupper($matches[1]); 
    $req_line = isset($matches[2]) ? (int)$matches[2] : 1; 

    $status_validasi = cekFormatODP($nama_odp);
    if ($status_validasi !== "OK") {
        $pesan_bantuan = $status_validasi . "\n\nFormat Benar:\nODP-XXX-XXX/000";
        file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan_bantuan) . "&reply_to_message_id=$message_id");
        exit;
    }

    // 🔥 TAMBAHKAN LOGIKA INI (Deteksi variasi tulisan "sdh golive blm")
    // Regex ini menangkap: sdh/sudah + go live/golive + blm/blum/belum dengan/tanpa tanda tanya (?)
    if (preg_match('/(sdh|sudah)\s*(go\s*live|golive)\s*(blm|blum|belum)/i', $text)) {
        $nama_odp .= " (CEK GOLIVE)";
    }
    if ($is_edit) {
        // Modifikasi: tabel tiket, log_telegram, dan semua parameter join serta kondisinya
        $stmt = $conn->prepare("
            UPDATE tiket t 
            JOIN log_telegram log ON t.id_tiket = log.id_tiket 
            SET t.nama_odp = ?, t.permintaan_line = ? 
            WHERE log.id_pesan_tg = ? AND t.id_teknisi = ? AND t.status = 'pending'
        ");
        $stmt->bind_param("sisi", $nama_odp, $req_line, $message_id, $technician_id);
        $stmt->execute();
    } else {
        // Modifikasi: tabel tiket dan id_teknisi
        $stmt_spam = $conn->prepare("SELECT COUNT(*) as total_pending FROM tiket WHERE id_teknisi = ? AND status = 'pending'");
        $stmt_spam->bind_param("i", $technician_id);
        $stmt_spam->execute();
        $hasil_spam = $stmt_spam->get_result()->fetch_assoc();

        if ($hasil_spam['total_pending'] >= 3) {
            $pesan_spam = "⚠️ Mohon maaf, Anda masih memiliki 3 tiket yang belum diambil oleh Tim Daman. Harap tunggu tiket Anda sebelumnya diproses sebelum membuat request baru.";
            file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan_spam) . "&reply_to_message_id=$message_id");
            exit; 
        }
        
        // Modifikasi: penyesuaian nama kolom tiket (id_teknisi, waktu_permintaan, permintaan_line)
        $stmt = $conn->prepare("INSERT INTO tiket (no_tiket, id_teknisi, waktu_permintaan, jenis_permintaan, nama_odp, permintaan_line, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sisssi", $no_tiket_baru, $technician_id, $waktu_request, $jenis_permintaan, $nama_odp, $req_line);
        
        if ($stmt->execute()) {
            $ticket_id = $stmt->insert_id;
            
            // Insert log telegram (Modifikasi: log_telegram, id_tiket, id_pesan_tg, id_grup_media)
            $stmt_log = $conn->prepare("INSERT INTO log_telegram (id_tiket, id_pesan_tg, id_grup_media) VALUES (?, ?, ?)");
            $stmt_log->bind_param("iss", $ticket_id, $message_id, $media_group_id);
            $stmt_log->execute();
            
            // Insert foto jika ada (Modifikasi: gambar_tiket, id_tiket, lokasi_file)
            if ($local_path) {
                $stmt_img = $conn->prepare("INSERT INTO gambar_tiket (id_tiket, lokasi_file) VALUES (?, ?)");
                $stmt_img->bind_param("is", $ticket_id, $local_path);
                $stmt_img->execute();
            }
            
            $pesan_sukses = "✅ Tiket Pemunculan Berhasil Dibuat!\n\nNo Tiket: $no_tiket_baru\nODP: $nama_odp\nLine: $req_line";
            file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan_sukses) . "&reply_to_message_id=$message_id");
        }
    }
}

// ==========================================
// C. LOGIKA PENGOSONGAN
// ==========================================
elseif (preg_match('/^\/req_kosong/i', $text)) {
    if (preg_match('/^\/req_kosong\s+([A-Za-z0-9\-\/]+)\s+Valins\s+(\d+)(?:\s+(?:untuk\s+)?(\d+)\s*line)?/i', $text, $matches)) {
        
        if (!$local_path) {
            $pesan_error = "❌ *Permintaan Ditolak*\n\nPengosongan port ODP WAJIB melampirkan foto bukti Valins. Silakan kirim ulang foto dan sertakan caption format request Anda.";
            file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan_error) . "&reply_to_message_id=$message_id&parse_mode=Markdown");
            exit;
        }

        $jenis_permintaan = 'pengosongan';
        $nama_odp = strtoupper($matches[1]); 
        $valins_id = $matches[2];
        $req_line = isset($matches[3]) ? (int)$matches[3] : 1; 

        $status_validasi = cekFormatODP($nama_odp);
        if ($status_validasi !== "OK") {
            $pesan_bantuan = $status_validasi . "\n\nFormat Benar:\nODP-XXX-XXX/000";
            file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan_bantuan) . "&reply_to_message_id=$message_id");
            exit;
        }

        if ($is_edit) {
            // Modifikasi: tabel tiket, log_telegram, validasi_tiket dan relasi PK/FK
            $stmt = $conn->prepare("
                UPDATE tiket t 
                JOIN log_telegram log ON t.id_tiket = log.id_tiket 
                LEFT JOIN validasi_tiket v ON t.id_tiket = v.id_tiket
                SET t.nama_odp = ?, v.id_valins = ?, t.permintaan_line = ? 
                WHERE log.id_pesan_tg = ? AND t.id_teknisi = ? AND t.status = 'pending'
            ");
            $stmt->bind_param("ssisi", $nama_odp, $valins_id, $req_line, $message_id, $technician_id);
            $stmt->execute();
        } else {
            // Modifikasi: tiket dan id_teknisi
            $stmt_spam = $conn->prepare("SELECT COUNT(*) as total_pending FROM tiket WHERE id_teknisi = ? AND status = 'pending'");
            $stmt_spam->bind_param("i", $technician_id);
            $stmt_spam->execute();
            $hasil_spam = $stmt_spam->get_result()->fetch_assoc();

            if ($hasil_spam['total_pending'] >= 3) {
                $pesan_spam = "⚠️ Mohon maaf, Anda masih memiliki 3 tiket yang belum diambil oleh Tim Daman. Harap tunggu tiket Anda sebelumnya diproses.";
                file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan_spam) . "&reply_to_message_id=$message_id");
                exit; 
            }
            
            // Modifikasi: penyesuaian kolom tiket
            $stmt = $conn->prepare("INSERT INTO tiket (no_tiket, id_teknisi, waktu_permintaan, jenis_permintaan, nama_odp, permintaan_line, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("sisssi", $no_tiket_baru, $technician_id, $waktu_request, $jenis_permintaan, $nama_odp, $req_line);
            
            if ($stmt->execute()) {
                $ticket_id = $stmt->insert_id;
                
                // Insert log telegram (Modifikasi tabel log_telegram)
                $stmt_log = $conn->prepare("INSERT INTO log_telegram (id_tiket, id_pesan_tg, id_grup_media) VALUES (?, ?, ?)");
                $stmt_log->bind_param("iss", $ticket_id, $message_id, $media_group_id);
                $stmt_log->execute();
                
                // Insert valins ID (Modifikasi tabel validasi_tiket)
                $stmt_val = $conn->prepare("INSERT INTO validasi_tiket (id_tiket, id_valins) VALUES (?, ?)");
                $stmt_val->bind_param("is", $ticket_id, $valins_id);
                $stmt_val->execute();
                
                // Insert foto (Modifikasi tabel gambar_tiket)
                if ($local_path) {
                    $stmt_img = $conn->prepare("INSERT INTO gambar_tiket (id_tiket, lokasi_file) VALUES (?, ?)");
                    $stmt_img->bind_param("is", $ticket_id, $local_path);
                    $stmt_img->execute();
                }
                
                $pesan_sukses = "✅ Tiket Pengosongan Berhasil Dibuat!\n\nNo Tiket: $no_tiket_baru\nODP: $nama_odp\nValins: $valins_id\nLine: $req_line";
                file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan_sukses) . "&reply_to_message_id=$message_id");
            }
        }
    } else {
        $pesan_bantuan = "❌ *Format Pengosongan Salah!*\n\nAnda WAJIB menyertakan kata 'Valins' dan nomor serinya untuk permintaan pengosongan.\n\n✅ *Contoh Format Benar:*\n`/req_kosong ODP-ABC-DEF/123 Valins 987654 2 line`\n\n_(Pastikan juga Anda melampirkan foto bukti fisik!)_";
        file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan_bantuan) . "&reply_to_message_id=$message_id&parse_mode=Markdown");
        exit;
    }
}

// ==========================================
// D. LOGIKA ALBUM GAMBAR 
// ==========================================
elseif (empty($text) && !empty($media_group_id) && $local_path) {
    // Cari ticket_id melalui tabel telegram_logs (Modifikasi penyesuaian PK)
    $stmt_cari = $conn->prepare("SELECT id_tiket FROM log_telegram WHERE id_grup_media = ? ORDER BY id_log_telegram DESC LIMIT 1");
    $stmt_cari->bind_param("s", $media_group_id);
    $stmt_cari->execute();
    $result = $stmt_cari->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $ticket_id = $row['id_tiket']; // Modifikasi array key
        
        // Modifikasi tabel gambar_tiket
        $stmt_img = $conn->prepare("INSERT INTO gambar_tiket (id_tiket, lokasi_file) VALUES (?, ?)");
        $stmt_img->bind_param("is", $ticket_id, $local_path);
        $stmt_img->execute();
    }
}
?>