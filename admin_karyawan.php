<?php
include "auth.php"; include "layout.php"; wajib_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $a = $_POST['aksi'] ?? '';
  if ($a === 'hapus') {
    if ((int)$_POST['id'] === $_SESSION['id']) flash("Akun yang sedang dipakai tidak bisa dihapus.", 'danger');
    else { q("DELETE FROM users WHERE id=?", "i", [(int)$_POST['id']]); flash("Karyawan dihapus."); }
  } else {
    $uid = (int)($_POST['id'] ?? 0); $nip = trim($_POST['nip']); $nama = trim($_POST['nama']); $email = trim($_POST['email']);
    $hp = trim($_POST['no_hp']); $alamat = trim($_POST['alamat']); $role = $_POST['role'] === 'admin' ? 'admin' : 'karyawan'; $pw = $_POST['password'];
    $dobel = hitung("SELECT COUNT(*) FROM users WHERE nip=? AND id<>?", "si", [$nip, $uid]);
    if ($nip === '' || $nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash("NIP, nama, dan email yang valid wajib diisi.", 'danger');
    elseif ($dobel) flash("NIP sudah dipakai karyawan lain.", 'danger');
    elseif ($uid) {
      q("UPDATE users SET nip=?,nama=?,email=?,no_hp=?,alamat=?,role=? WHERE id=?", "ssssssi", [$nip,$nama,$email,$hp,$alamat,$role,$uid]);
      if ($pw !== '') q("UPDATE users SET password=? WHERE id=?", "si", [password_hash($pw, PASSWORD_DEFAULT), $uid]);
      flash("Data karyawan diperbarui.");
    } elseif (strlen($pw) < 6) flash("Password awal minimal 6 karakter.", 'danger');
    else { q("INSERT INTO users (nip,nama,email,no_hp,alamat,role,password) VALUES (?,?,?,?,?,?,?)", "sssssss", [$nip,$nama,$email,$hp,$alamat,$role,password_hash($pw, PASSWORD_DEFAULT)]); flash("Karyawan ditambahkan."); }
  }
  header("Location: admin_karyawan.php"); exit;
}
$e = isset($_GET['edit']) ? ambil(q("SELECT * FROM users WHERE id=?", "i", [(int)$_GET['edit']])) : null;
top("Data karyawan"); ?>

<div class="row g-3">
  <div class="col-lg-4"><div class="card card-body mb-3"><h2 class="h5 mb-3"><?= $e ? "Ubah karyawan" : "Tambah karyawan" ?></h2>
    <form method="POST" data-validate novalidate>
      <input type="hidden" name="id" value="<?= $e['id'] ?? 0 ?>">
      <div class="mb-2"><label class="form-label">NIP</label><input name="nip" class="form-control" value="<?= e($e['nip'] ?? '') ?>" required></div>
      <div class="mb-2"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?= e($e['nama'] ?? '') ?>" required></div>
      <div class="mb-2"><label class="form-label">Email</label><input name="email" class="form-control" value="<?= e($e['email'] ?? '') ?>" required></div>
      <div class="mb-2"><label class="form-label">Nomor HP</label><input name="no_hp" class="form-control" value="<?= e($e['no_hp'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control" rows="2"><?= e($e['alamat'] ?? '') ?></textarea></div>
      <div class="mb-2"><label class="form-label">Role</label><select name="role" class="form-select"><option value="karyawan">Karyawan</option><option value="admin" <?= ($e['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin HR</option></select></div>
      <div class="mb-3"><label class="form-label"><?= $e ? "Password baru (kosongkan jika tidak diganti)" : "Password awal" ?></label><input type="password" name="password" class="form-control"></div>
      <button class="btn btn-success">Simpan</button> <?php if ($e) echo "<a href='admin_karyawan.php' class='btn btn-outline-secondary'>Batal</a>"; ?>
    </form></div>
  <div class="col-lg-8"><div class="card card-body mb-3"><h2 class="h5 mb-3">Daftar karyawan</h2>
    <input id="cari" class="form-control mb-3" placeholder="Cari nama atau NIP...">
    <div class="table-responsive"><table class="table" id="tabel-cari"><thead class="table-light"><tr><th>NIP</th><th>Nama</th><th>Email</th><th>Role</th><th></th></tr></thead><tbody>
    <?php foreach (semua(q("SELECT * FROM users ORDER BY role, nama")) as $r): ?>
      <tr><td><?= e($r['nip']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['role']) ?></td>
      <td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="?edit=<?= $r['id'] ?>">Ubah</a>
        <form method="POST" class="d-inline" data-confirm="Hapus karyawan beserta seluruh datanya?"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr>
    <?php endforeach; ?></tbody></table></div>
  </div></div>
</div>
<?php bottom(); ?>
