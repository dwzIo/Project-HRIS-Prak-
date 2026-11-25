<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";

if (isset($_SESSION['login'])) {
  pindah('dashboard.php');
}

$teks_pesan = '';
$jenis_pesan = 'gagal';
$nip_isi = '';

// Pesan dari halaman lain (misalnya setelah reset password)
$pesan_flash = ambil_pesan();
if ($pesan_flash) {
  $teks_pesan = $pesan_flash[0];
  if ($pesan_flash[1] === 'sukses') {
    $jenis_pesan = 'sukses';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isi('aksi') === 'login') {
  $teks_pesan = proses_login('karyawan'); // berhasil = langsung pindah ke dashboard
  $jenis_pesan = 'gagal';
  $nip_isi = trim(isi('nip'));
}

login_atas(
  'Masuk Karyawan',
  'Data dirimu, kamu yang atur.',
  'Ajukan perubahan data, minta surat keterangan, dan simpan dokumen pribadi tanpa antre di bagian HR.',
  $teks_pesan,
  $jenis_pesan
);
form_login('karyawan', $nip_isi);
login_bawah();
