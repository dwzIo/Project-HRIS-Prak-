<?php
// Pintu masuk: pilih masuk sebagai Karyawan atau Admin (masing-masing punya halaman login sendiri).
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";

if (isset($_SESSION['login'])) {
  pindah('dashboard.php');
}

login_atas(
  'Masuk',
  'Data dirimu, kamu yang atur.',
  'Ajukan perubahan data, minta surat keterangan, dan simpan dokumen pribadi tanpa antre di bagian HR.'
);
?>
      <h1 class="mb-1 text-lg font-semibold">Masuk ke MyHR</h1>
      <p class="mb-5 text-sm text-stone-500">Pilih sesuai peran akunmu.</p>

      <a href="login_karyawan.php" class="btn btn-utama mb-3 h-11 w-full">Masuk sebagai Karyawan</a>
      <a href="login_admin.php" class="btn btn-garis h-11 w-full">Masuk sebagai Admin</a>
<?php
login_bawah();
