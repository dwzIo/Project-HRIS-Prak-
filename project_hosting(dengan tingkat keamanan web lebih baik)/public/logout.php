<?php
// Keluar: memakai konfigurasi session terpusat, lalu menghapus session & cookie-nya.
include_once __DIR__ . "/../src/keamanan.php";
mulai_sesi_aman();
kirim_header_keamanan();

$peran = '';
if (isset($_SESSION['role'])) {
  $peran = $_SESSION['role'];
}
$_SESSION = array();
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

if ($peran === 'admin') {
  header("Location: login_admin.php");
} elseif ($peran === 'karyawan') {
  header("Location: login_karyawan.php");
} else {
  header("Location: login.php");
}
exit;
