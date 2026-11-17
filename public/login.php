<?php
// Login & Register dalam SATU halaman (dua tampilan, berganti lewat JavaScript)
include "../src/auth.php";
include "../src/layout.php";
if (isset($_SESSION['login'])) { pindah('dashboard.php'); }
$teks_pesan = '';
$jenis_pesan = 'gagal';
$tab = 'login';
$nip_isi = '';

// Pesan dari halaman lain (misalnya setelah reset password)
$pesan_flash = ambil_pesan();
if ($pesan_flash) {
  $teks_pesan = $pesan_flash[0];
  if ($pesan_flash[1] === 'sukses') { $jenis_pesan = 'sukses'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isi('aksi') === 'login') {
    $nip = trim(isi('nip'));
    $user = satu("SELECT * FROM users WHERE nip=?", array($nip));
    if ($user && password_verify(isi('password'), $user['password'])) {
      session_regenerate_id(true);
      $_SESSION['login'] = true;
      $_SESSION['id'] = $user['id'];
      $_SESSION['nama'] = $user['nama'];
      $_SESSION['role'] = $user['role'];
      // --- Cookie "ingat NIP saya" (DINONAKTIFKAN sementara, kode dipertahankan) ---
      // if (isset($_POST['ingat'])) { setcookie('nip_terakhir', $nip, time() + 604800); }
      // else { setcookie('nip_terakhir', '', time() - 3600); }
      pindah('dashboard.php');
    }
    $teks_pesan = 'NIP atau password salah.';
    $jenis_pesan = 'gagal';
    $nip_isi = $nip; // NIP yang tadi diketik dikembalikan ke kolom
  } else {
    $tab = 'register';
    $nip = trim(isi('nip'));
    $nama = trim(isi('nama'));
    $email = trim(isi('email'));
    $password = isi('password');
    if ($nip === '' || $nama === '' || $email === '' || $password === '') { $teks_pesan = 'Semua kolom wajib diisi.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $teks_pesan = 'Format email tidak valid.'; }
    elseif (strlen($password) < 6) { $teks_pesan = 'Password minimal 6 karakter.'; }
    elseif ($password !== isi('konfirmasi')) { $teks_pesan = 'Konfirmasi password tidak sama.'; }
    elseif (angka("SELECT COUNT(*) FROM users WHERE nip=?", array($nip)) > 0) { $teks_pesan = 'NIP sudah terdaftar.'; }
    else {
      $hash = password_hash($password, PASSWORD_DEFAULT); // password disimpan sebagai hash
      jalankan("INSERT INTO users (id,nip,nama,email,password,role) VALUES (?,?,?,?,?,'karyawan')", array(buat_id(), $nip, $nama, $email, $hash));
      $teks_pesan = 'Akun berhasil dibuat. Silakan masuk.';
      $jenis_pesan = 'sukses';
      $tab = 'login';
    }
  }
}
// Cookie (DINONAKTIFKAN): $nip_isi = isset($_COOKIE['nip_terakhir']) ? $_COOKIE['nip_terakhir'] : '';
$sembunyi_login = '';
$sembunyi_daftar = 'hidden';
if ($tab === 'register') { $sembunyi_login = 'hidden'; $sembunyi_daftar = ''; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk | MyHR</title>
  <?php gaya(); ?>
</head>
<body class="min-h-screen bg-white text-stone-800 lg:flex">

  <!-- Kiri: panel statis (hanya layar lebar). Tersusun vertikal: logo - gambar - teks,
       jadi gambar tidak pernah menimpa teks di kiri bawah (dan sebaliknya). -->
  <section class="hidden min-h-screen flex-1 flex-col overflow-hidden bg-stone-50 px-12 py-10 lg:flex">
    <p class="text-2xl font-semibold text-teal-700">MyHR</p>
    <!-- Area gambar mengisi sisa ruang; gambar mengecil menyesuaikan tinggi layar -->
    <div class="relative my-6 min-h-[12rem] flex-1">
      <img src="assets/hris.jpg" alt="Ilustrasi pengelolaan data karyawan di sistem HR" class="absolute inset-0 h-full w-full object-contain mix-blend-multiply">
    </div>
    <div class="max-w-md">
      <h2 class="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">Data dirimu, <span class="text-teal-700">kamu yang atur.</span></h2>
      <p class="mt-5 text-stone-600">Ajukan perubahan data, minta surat keterangan, dan simpan dokumen pribadi tanpa antre di bagian HR.</p>
    </div>
  </section>

  <!-- Kanan: formulir masuk / daftar -->
  <section class="flex min-h-screen w-full items-center justify-center border-stone-200 px-6 py-10 lg:w-[34rem] lg:border-l">
    <div class="w-full max-w-md">
      <p class="mb-8 text-2xl font-semibold text-teal-700 lg:hidden">MyHR</p>

      <?php if ($teks_pesan !== ''):
        $warna = 'border-danger-200 bg-danger-50 text-danger-800';
        if ($jenis_pesan === 'sukses') { $warna = 'border-emerald-200 bg-emerald-50 text-emerald-800'; }
      ?>
        <div class="mb-4 rounded-xl border px-4 py-3 text-sm <?= $warna ?>"><?= e($teks_pesan) ?></div>
      <?php endif; ?>

      <div id="form-login" class="<?= $sembunyi_login ?>">
        <h1 class="mb-4 text-lg font-semibold">Masuk ke MyHR</h1>
        <form method="POST" data-validasi novalidate>
          <input type="hidden" name="aksi" value="login">
          <?php isian_login('NIP', 'nip', 'text', $nip_isi, 'required'); ?>
          <?php isian_login('Password', 'password', 'password', '', 'required'); ?>
          <?php /* Cookie "Ingat NIP saya" (DINONAKTIFKAN sementara, kode dipertahankan):
          <label class="mb-4 flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" name="ingat" <?= $nip_isi !== '' ? 'checked' : '' ?>> Ingat NIP saya</label>
          */ ?>
          <button class="h-11 w-full rounded-full bg-teal-700 text-sm font-semibold text-white hover:bg-teal-800">Masuk</button>
        </form>
        <p class="mt-4 text-center text-sm"><a href="lupa_password.php" class="font-medium text-stone-700 hover:underline">Lupa password? (khusus karyawan)</a></p>
        <button type="button" data-tab="register" class="mt-10 h-11 w-full rounded-full border border-teal-700 text-sm font-semibold text-teal-700 hover:bg-teal-50">Buat akun baru</button>
      </div>

      <div id="form-register" class="<?= $sembunyi_daftar ?>">
        <h1 class="mb-4 text-lg font-semibold">Buat akun karyawan</h1>
        <form method="POST" data-validasi novalidate>
          <input type="hidden" name="aksi" value="register">
          <?php isian_login('NIP', 'nip', 'text', '', 'required'); ?>
          <?php isian_login('Nama lengkap', 'nama', 'text', '', 'required'); ?>
          <?php isian_login('Email', 'email', 'email', '', 'required'); ?>
          <?php isian_login('Password', 'password', 'password', '', 'required'); ?>
          <?php isian_login('Ulangi password', 'konfirmasi', 'password', '', 'required'); ?>
          <button class="mt-1 h-11 w-full rounded-full bg-teal-700 text-sm font-semibold text-white hover:bg-teal-800">Buat akun</button>
        </form>
        <button type="button" data-tab="login" class="mt-6 h-11 w-full rounded-full border border-teal-700 text-sm font-semibold text-teal-700 hover:bg-teal-50">Sudah punya akun? Masuk</button>
      </div>
    </div>
  </section>

  <script src="../assets/app.js"></script>
</body>
</html>
