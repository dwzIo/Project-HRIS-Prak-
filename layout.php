<?php
function top($judul) {
  $f = flash();
  $adm = ($_SESSION['role'] ?? '') === 'admin';
  $menu = $adm
    ? ['dashboard.php'=>'Dashboard','admin_karyawan.php'=>'Data karyawan','admin_pengajuan.php'=>'Pengajuan']
    : ['dashboard.php'=>'Dashboard','profil.php'=>'Profil saya','surat.php'=>'Surat keterangan','dokumen.php'=>'Dokumen saya'];
  $hal = basename($_SERVER['PHP_SELF']); ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul) ?> | MyHR</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-body-tertiary">
<nav class="navbar navbar-expand-md navbar-dark bg-success">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard.php">MyHR</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav me-auto">
        <?php foreach ($menu as $url => $nama): ?>
          <li class="nav-item"><a class="nav-link <?= $hal === $url ? 'active fw-semibold' : '' ?>" href="<?= $url ?>"><?= $nama ?></a></li>
        <?php endforeach; ?>
      </ul>
      <span class="navbar-text me-3"><?= e($_SESSION['nama']) ?></span>
      <a href="logout.php" class="btn btn-sm btn-outline-light">Keluar</a>
    </div>
  </div>
</nav>
<main class="container py-4 flex-grow-1">
  <h1 class="h3 mb-3"><?= e($judul) ?></h1>
  <?php if ($f): ?><div class="alert alert-<?= $f[1] ?>"><?= e($f[0]) ?></div><?php endif;
}
function bottom() { ?>
</main>
<footer class="bg-dark text-light-emphasis py-3 mt-auto">
  <div class="container d-flex justify-content-between flex-wrap small text-secondary">
    <span>MyHR &mdash; Portal Layanan Mandiri Karyawan</span><span>Praktikum Pemrograman Web</span>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
<?php } ?>
