<?php
// Di-include paling atas setiap halaman (session_start harus di baris awal)
session_start();
include_once __DIR__ . "/koneksi.php";
define('FOLDER_UPLOAD', __DIR__ . '/../uploads'); // lokasi asli folder upload di server

// ---------- fungsi bantu umum ----------
function e($teks) { return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8'); }
function isi($nama) { if (isset($_POST[$nama])) { return $_POST[$nama]; } return ''; }
function tgl($waktu) { return date('d M Y', strtotime($waktu)); }
// ID acak 16 karakter untuk semua tabel (bukan auto increment)
function buat_id() { return bin2hex(random_bytes(8)); }
function pindah($url) { header("Location: $url"); exit; }

function wajib_login() { if (!isset($_SESSION['login'])) { pindah('login.php'); } }
// Peran dibaca dari database (bukan dari session) agar perubahan peran langsung berlaku
function peran_sekarang() {
  static $peran = null;
  if ($peran === null) {
    $r = satu("SELECT role FROM users WHERE id=?", array($_SESSION['id']));
    $peran = '';
    if ($r) { $peran = $r['role']; }
  }
  return $peran;
}
function adalah_admin() { return peran_sekarang() === 'admin'; }
function nama_peran($role) {
  if ($role === 'admin') { return 'Admin'; }
  return 'Karyawan';
}
function wajib_admin() { wajib_login(); if (!adalah_admin()) { pindah('dashboard.php'); } }

// Aturan hapus akun. Mengembalikan alasan penolakan, atau teks KOSONG jika boleh dihapus.
function alasan_tidak_boleh_hapus($target) {
  if ($target['id'] === $_SESSION['id']) { return 'Anda tidak bisa menghapus akun yang sedang dipakai.'; }
  return '';
}

// Pesan singkat yang tampil sekali setelah aksi
function pesan($teks, $tipe = 'sukses') { $_SESSION['pesan'] = array($teks, $tipe); }
function ambil_pesan() {
  if (!isset($_SESSION['pesan'])) { return null; }
  $p = $_SESSION['pesan'];
  unset($_SESSION['pesan']);
  return $p;
}

// ---------- database (PDO + prepared statement) ----------
function jalankan($sql, $parameter = array()) {
  global $konek;
  $st = $konek->prepare($sql);
  $st->execute($parameter);
  return $st;
}
function satu($sql, $parameter = array()) {
  $baris = jalankan($sql, $parameter)->fetch(PDO::FETCH_ASSOC);
  if ($baris) { return $baris; }
  return null;
}
function daftar($sql, $parameter = array()) { return jalankan($sql, $parameter)->fetchAll(PDO::FETCH_ASSOC); }
function angka($sql, $parameter = array()) { return (int) jalankan($sql, $parameter)->fetchColumn(); }

// ---------- upload file ----------
function ada_file($nama) { return isset($_FILES[$nama]) && $_FILES[$nama]['error'] !== UPLOAD_ERR_NO_FILE; }
function upload($nama, $folder, $ekstensi, $maks_mb) {
  if (!ada_file($nama)) { return array(null, 'Pilih file terlebih dahulu.'); }
  $f = $_FILES[$nama];
  if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) { return array(null, 'Ukuran file melebihi batas server (upload_max_filesize di php.ini).'); }
  if ($f['error'] !== UPLOAD_ERR_OK) { return array(null, 'Upload gagal (kode error ' . $f['error'] . ').'); }
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $ekstensi)) { return array(null, 'Tipe file harus: ' . implode(', ', $ekstensi) . '.'); }
  if ($f['size'] > $maks_mb * 1048576) { return array(null, 'Ukuran file maksimal ' . $maks_mb . ' MB.'); }
  $tujuan = FOLDER_UPLOAD . '/' . $folder;
  if (!is_dir($tujuan) && !mkdir($tujuan, 0777, true)) { return array(null, 'Folder uploads/' . $folder . ' tidak bisa dibuat. Periksa izin folder.'); }
  $nama_baru = bin2hex(random_bytes(8)) . '.' . $ext; // nama diganti acak
  if (!move_uploaded_file($f['tmp_name'], $tujuan . '/' . $nama_baru)) { return array(null, 'File gagal disimpan ke folder uploads/' . $folder . '. Periksa izin tulis folder.'); }
  return array($nama_baru, null);
}

// Label status berwarna
function lencana($status) {
  $warna = 'bg-stone-100 text-stone-700';
  if ($status === 'Menunggu') { $warna = 'bg-amber-100 text-amber-800'; }
  if ($status === 'Diproses') { $warna = 'bg-sky-100 text-sky-800'; }
  if (in_array($status, array('Disetujui', 'Selesai', 'Terverifikasi'))) { $warna = 'bg-emerald-100 text-emerald-800'; }
  if (in_array($status, array('Ditolak', 'Perlu Diperbarui'))) { $warna = 'bg-danger-100 text-danger-800'; }
  return '<span class="inline-block rounded px-2 py-0.5 text-xs font-medium ' . $warna . '">' . e($status) . '</span>';
}

// Jika data kiriman melebihi post_max_size, PHP mengosongkan $_POST dan $_FILES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
  pesan('Ukuran data yang dikirim melebihi batas server (post_max_size di php.ini).', 'gagal');
  pindah(basename($_SERVER['PHP_SELF']));
}
