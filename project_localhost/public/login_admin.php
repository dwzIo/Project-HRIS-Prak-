<?php
// Halaman masuk KHUSUS ADMIN. Sengaja tanpa tautan "Lupa password":
// reset password hanya untuk karyawan dan disetujui oleh admin.
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";

if (isset($_SESSION['login'])) {
  pindah('dashboard.php');
}

$teks_pesan = '';
$jenis_pesan = 'gagal';
$nip_isi = '';

$pesan_flash = ambil_pesan();
if ($pesan_flash) {
  $teks_pesan = $pesan_flash[0];
  if ($pesan_flash[1] === 'sukses') {
    $jenis_pesan = 'sukses';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isi('aksi') === 'login') {
  $teks_pesan = proses_login('admin'); // berhasil = langsung pindah ke dashboard
  $jenis_pesan = 'gagal';
  $nip_isi = trim(isi('nip'));
}

login_atas(
  'Masuk Admin',
  'Kelola data karyawan dengan rapi.',
  'Verifikasi pengajuan, kelola absensi, cuti, dan payroll dari satu tempat.',
  $teks_pesan,
  $jenis_pesan
);
form_login('admin', $nip_isi);
login_bawah();
