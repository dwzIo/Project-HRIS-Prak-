<?php
// Lupa password - KHUSUS KARYAWAN, dengan persetujuan admin:
// 1) karyawan mengajukan (NIP + email)  2) admin menyetujui / menolak di menu Pengajuan
// 3) setelah disetujui, karyawan membuka halaman status ini lagi dan mengisi password baru.
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
if (isset($_SESSION['login'])) { pindah('dashboard.php'); }

$kode = '';
if (isset($_GET['kode'])) { $kode = $_GET['kode']; }
if (isset($_POST['kode'])) { $kode = $_POST['kode']; }
$teks = '';
$jenis = 'gagal';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isi('aksi') === 'minta') {
    $nip = trim(isi('nip'));
    $email = trim(isi('email'));
    $user = satu("SELECT * FROM users WHERE nip=? AND role='karyawan'", array($nip)); // hanya karyawan; admin tidak bisa memakai fitur ini
    if ($user && strtolower($user['email']) === strtolower($email)) {
      jalankan("DELETE FROM password_resets WHERE user_id=?", array($user['id']));
      $baru = bin2hex(random_bytes(16));
      jalankan("INSERT INTO password_resets (id,user_id,token_hash,status,created_at,expires_at) VALUES (?,?,?,?,?,?)",
        array(buat_id(), $user['id'], hash('sha256', $baru), 'Menunggu', time(), time() + 86400));
      pindah('lupa_password.php?kode=' . $baru);
    }
    $teks = 'NIP dan email tidak cocok dengan akun karyawan mana pun.';
  } elseif (isi('aksi') === 'reset') {
    $permintaan = null;
    if ($kode !== '') { $permintaan = satu("SELECT * FROM password_resets WHERE token_hash=?", array(hash('sha256', $kode))); }
    $password = isi('password');
    if (!$permintaan || $permintaan['status'] !== 'Disetujui' || $permintaan['expires_at'] < time()) { $teks = 'Permintaan ini belum disetujui admin atau sudah tidak berlaku.'; }
    elseif (strlen($password) < 6) { $teks = 'Password minimal 6 karakter.'; }
    elseif ($password !== isi('konfirmasi')) { $teks = 'Konfirmasi password tidak sama.'; }
    else {
      jalankan("UPDATE users SET password=? WHERE id=?", array(password_hash($password, PASSWORD_DEFAULT), $permintaan['user_id']));
      jalankan("UPDATE password_resets SET status='Selesai' WHERE id=?", array($permintaan['id']));
      pesan('Password berhasil diganti. Silakan masuk dengan password baru.');
      pindah('login.php');
    }
  }
}

// Menentukan tahap yang ditampilkan berdasarkan status permintaan
$tahap = 'minta';
$permintaan = null;
if ($kode !== '') {
  $permintaan = satu("SELECT r.*, u.nip, u.nama FROM password_resets r JOIN users u ON u.id=r.user_id WHERE r.token_hash=?", array(hash('sha256', $kode)));
  if (!$permintaan) { $tahap = 'tidak_ada'; }
  elseif ($permintaan['status'] === 'Selesai') { $tahap = 'selesai'; }
  elseif ($permintaan['expires_at'] < time()) { $tahap = 'kedaluwarsa'; }
  elseif ($permintaan['status'] === 'Ditolak') { $tahap = 'ditolak'; }
  elseif ($permintaan['status'] === 'Disetujui') { $tahap = 'disetujui'; }
  else { $tahap = 'menunggu'; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lupa password | MyHR</title>
  <?php gaya(); ?>
</head>
<body class="flex min-h-screen items-center justify-center bg-stone-100 px-4 py-8 text-stone-800">
  <div class="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-8 shadow-sm">
    <p class="mb-1 text-2xl font-semibold text-teal-700">MyHR</p>
    <h1 class="mb-1 text-lg font-semibold">Lupa password</h1>
    <p class="mb-5 text-sm text-stone-500">Khusus akun karyawan. Permintaan reset harus disetujui admin terlebih dahulu.</p>

    <?php if ($teks !== ''):
      $warna = 'border-danger-200 bg-danger-50 text-danger-800';
      if ($jenis === 'sukses') { $warna = 'border-emerald-200 bg-emerald-50 text-emerald-800'; }
    ?>
      <div class="mb-4 rounded-xl border px-4 py-3 text-sm <?= $warna ?>"><?= e($teks) ?></div>
    <?php endif; ?>

    <?php if ($tahap === 'minta'): ?>
      <form method="POST" data-validasi novalidate>
        <input type="hidden" name="aksi" value="minta">
        <?php isian_login('NIP', 'nip', 'text', '', 'required'); ?>
        <?php isian_login('Email terdaftar', 'email', 'email', '', 'required'); ?>
        <button class="h-11 w-full rounded-full bg-teal-700 text-sm font-semibold text-white hover:bg-teal-800">Ajukan permintaan reset</button>
      </form>

    <?php elseif ($tahap === 'menunggu'): ?>
      <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">Menunggu persetujuan admin</p>
        <p class="mt-1">Permintaan atas nama <?= e($permintaan['nama']) ?> (NIP <?= e($permintaan['nip']) ?>) sudah dikirim ke admin.</p>
      </div>
      <p class="mb-4 text-sm text-stone-600">Simpan alamat halaman ini, lalu buka lagi nanti untuk memeriksa apakah permintaanmu sudah disetujui.</p>
      <a href="lupa_password.php?kode=<?= e($kode) ?>" class="btn btn-garis w-full">Periksa status sekarang</a>

    <?php elseif ($tahap === 'disetujui'): ?>
      <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
        <p class="font-semibold">Permintaan disetujui</p>
        <p class="mt-1">Buat password baru untuk NIP <?= e($permintaan['nip']) ?>. Berlaku sampai <?= e(date('d M Y H:i', $permintaan['expires_at'])) ?>.</p>
      </div>
      <form method="POST" data-validasi novalidate>
        <input type="hidden" name="aksi" value="reset">
        <input type="hidden" name="kode" value="<?= e($kode) ?>">
        <?php isian_login('Password baru', 'password', 'password', '', 'required'); ?>
        <?php isian_login('Ulangi password baru', 'konfirmasi', 'password', '', 'required'); ?>
        <button class="h-11 w-full rounded-full bg-teal-700 text-sm font-semibold text-white hover:bg-teal-800">Simpan password baru</button>
      </form>

    <?php elseif ($tahap === 'ditolak'): ?>
      <div class="mb-4 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800">
        <p class="font-semibold">Permintaan ditolak admin</p>
        <?php if ($permintaan['catatan']): ?><p class="mt-1">Catatan: <?= e($permintaan['catatan']) ?></p><?php endif; ?>
      </div>
      <a href="lupa_password.php" class="btn btn-garis w-full">Ajukan permintaan baru</a>

    <?php elseif ($tahap === 'selesai'): ?>
      <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">Password untuk permintaan ini sudah diganti. Silakan masuk.</div>

    <?php elseif ($tahap === 'kedaluwarsa'): ?>
      <div class="mb-4 rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm text-stone-700">Permintaan ini sudah kedaluwarsa.</div>
      <a href="lupa_password.php" class="btn btn-garis w-full">Ajukan permintaan baru</a>

    <?php else: ?>
      <div class="mb-4 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800">Tautan permintaan tidak dikenali.</div>
      <a href="lupa_password.php" class="btn btn-garis w-full">Ajukan permintaan baru</a>
    <?php endif; ?>

    <p class="mt-6 text-center text-sm"><a href="login.php" class="font-medium text-stone-700 hover:underline">Kembali ke halaman masuk</a></p>
  </div>
  <script src="assets/app.js"></script>
</body>
</html>
