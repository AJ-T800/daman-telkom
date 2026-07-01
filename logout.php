<?php
// Mulai session untuk bisa mengaksesnya
session_start();

// Hapus semua variabel session yang ada
session_unset();

// Hancurkan session secara keseluruhan
session_destroy();

// Pantulkan (redirect) user langsung ke halaman login
header("Location: login.php");
exit();
?>