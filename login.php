<?php
// Login & Register dalam SATU halaman (tab diganti lewat JavaScript)
include "auth.php";
if (isset($_SESSION['login'])) {
  header("Location: dashboard.php");
  exit;
  }
$pesan = "";
$tipe = "danger";
$tab = "login";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (($_POST['aksi'] ?? '') === 'login') {
    $nip = trim($_POST['nip']); $pw = $_POST['password'];
    $u = ambil(q("SELECT * FROM users WHERE nip=?", "s", [$nip]));
    if ($u && password_verify($pw, $u['password'])) {
      session_regenerate_id(true);
      $_SESSION['login'] = true; $_SESSION['id'] = $u['id'];
      $_SESSION['nama'] = $u['nama']; $_SESSION['role'] = $u['role'];
      // Cookie "ingat saya": menyimpan NIP di browser selama 7 hari
      if (isset($_POST['ingat'])) setcookie("nip_terakhir", $nip, time() + 604800);
      else setcookie("nip_terakhir", "", time() - 3600);
      header("Location: dashboard.php"); exit;
    }
    $pesan = "NIP atau password salah.";
  }
  else {
    $tab = "register";
    $nip = trim($_POST['nip']); $nama = trim($_POST['nama']); $email = trim($_POST['email']);
    $pw = $_POST['password']; $kf = $_POST['konfirmasi'];
    if ($nip === '' || $nama === '' || $email === '' || $pw === '') $pesan = "Semua kolom wajib diisi.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $pesan = "Format email tidak valid.";
    elseif (strlen($pw) < 6) $pesan = "Password minimal 6 karakter.";
    elseif ($pw !== $kf) $pesan = "Konfirmasi password tidak sama.";
    elseif (hitung("SELECT COUNT(*) FROM users WHERE nip=?", "s", [$nip]) > 0) $pesan = "NIP sudah terdaftar.";
    else {
      $hash = password_hash($pw, PASSWORD_DEFAULT); // password disimpan dalam bentuk hash
      q("INSERT INTO users (nip,nama,email,password,role) VALUES (?,?,?,?,'karyawan')", "ssss", [$nip, $nama, $email, $hash]);
      $pesan = "Akun berhasil dibuat. Silakan masuk."; $tipe = "success"; $tab = "login";
    }
  }
}
$nip_cookie = $_COOKIE['nip_terakhir'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk | MyHR</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-body-tertiary">
<div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
  <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
    <div class="card shadow border-0">
      <div class="card-header bg-success text-white text-center py-3">
        <h1 class="h4 mb-1">MyHR</h1>
        <small>Portal Layanan Mandiri Karyawan</small>
      </div>
      <div class="card-body p-4">
        <ul class="nav nav-pills nav-fill mb-3">
          <li class="nav-item"><button type="button" data-tab="login" class="nav-link <?= $tab === 'login' ? 'active' : '' ?>">Masuk</button></li>
          <li class="nav-item"><button type="button" data-tab="register" class="nav-link <?= $tab === 'register' ? 'active' : '' ?>">Daftar</button></li>
        </ul>
        <?php if ($pesan): ?><div class="alert alert-<?= $tipe ?>"><?= e($pesan) ?></div><?php endif; ?>

        <form id="form-login" method="POST" data-validate class="<?= $tab === 'login' ? '' : 'd-none' ?>" novalidate>
          <input type="hidden" name="aksi" value="login">
          <div class="mb-3"><label class="form-label">NIP</label>
            <input type="text" name="nip" class="form-control" value="<?= e($nip_cookie) ?>" required></div>
          <div class="mb-3"><label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required></div>
          <div class="d-flex justify-content-between mb-3 small">
            <label><input type="checkbox" name="ingat" class="form-check-input" <?= $nip_cookie ? 'checked' : '' ?>> Ingat NIP saya</label>
            <label><input type="checkbox" class="form-check-input" data-toggle-pw> Lihat password</label>
          </div>
          <button class="btn btn-success w-100">Masuk</button>
        </form>

        <form id="form-register" method="POST" data-validate class="<?= $tab === 'register' ? '' : 'd-none' ?>" novalidate>
          <input type="hidden" name="aksi" value="register">
          <div class="mb-3"><label class="form-label">NIP</label><input type="text" name="nip" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Nama lengkap</label><input type="text" name="nama" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Ulangi password</label><input type="password" name="konfirmasi" class="form-control" required></div>
          <button class="btn btn-success w-100">Buat akun</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="assets/app.js"></script>
</body>
</html>
