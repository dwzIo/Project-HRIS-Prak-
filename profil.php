<?php
include "auth.php"; include "layout.php"; wajib_login();
$id = $_SESSION['id'];
$boleh = ['alamat'=>'Alamat','no_hp'=>'Nomor HP','email'=>'Email','kontak_darurat'=>'Kontak darurat'];
$u = ambil(q("SELECT * FROM users WHERE id=?", "i", [$id]));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $a = $_POST['aksi'] ?? '';
  if ($a === 'foto') {
    list($n, $err) = upload($_FILES['foto'] ?? null, 'foto', ['jpg','jpeg','png']);
    if ($err) flash($err, 'danger'); else { q("UPDATE users SET foto=? WHERE id=?", "si", [$n, $id]); flash("Foto profil diperbarui."); }
  } elseif ($a === 'ubah') {
    $f = $_POST['field'] ?? ''; $v = trim($_POST['nilai'] ?? ''); $bukti = null; $err = null;
    if (!isset($boleh[$f]) || $v === '') $err = "Isi nilai baru dengan benar.";
    elseif (($_FILES['bukti']['error'] ?? 4) === 0) list($bukti, $err) = upload($_FILES['bukti'], 'bukti', ['jpg','jpeg','png','pdf']);
    if ($err) flash($err, 'danger');
    else { q("INSERT INTO change_requests (user_id,field,nilai_lama,nilai_baru,bukti) VALUES (?,?,?,?,?)", "issss", [$id, $f, $u[$f], $v, $bukti]); flash("Pengajuan dikirim dan menunggu persetujuan HR."); }
  } elseif ($a === 'password') {
    if (!password_verify($_POST['lama'], $u['password'])) flash("Password lama salah.", 'danger');
    elseif (strlen($_POST['baru']) < 6 || $_POST['baru'] !== $_POST['konfirmasi']) flash("Password baru minimal 6 karakter dan harus sama dengan konfirmasi.", 'danger');
    else { q("UPDATE users SET password=? WHERE id=?", "si", [password_hash($_POST['baru'], PASSWORD_DEFAULT), $id]); flash("Password berhasil diganti."); }
  }
  header("Location: profil.php"); exit;
}
top("Profil saya");
?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card card-body mb-3">
      <div class="d-flex gap-3 align-items-center mb-3">
        <?php if ($u['foto']): ?><img class="rounded-circle object-fit-cover" width="96" height="96" src="uploads/foto/<?= e($u['foto']) ?>" alt="Foto profil">
        <?php else: ?><span class="badge rounded-pill text-bg-success fs-1"><?= e(strtoupper(substr($u['nama'], 0, 1))) ?></span><?php endif; ?>
        <div><h2 class="h5 mb-0"><?= e($u['nama']) ?></h2><span class="text-muted">NIP <?= e($u['nip']) ?></span></div>
      </div>
      <table class="table table-sm mb-3">
        <?php foreach ($boleh as $k => $lbl): ?><tr><th style="width:40%"><?= $lbl ?></th><td><?= e($u[$k]) ?: '<span class="text-muted">belum diisi</span>' ?></td></tr><?php endforeach; ?>
      </table>
      <form method="POST" enctype="multipart/form-data" data-validate novalidate>
        <input type="hidden" name="aksi" value="foto">
        <label class="form-label">Ganti foto (JPG/PNG, maks 2 MB)</label>
        <input type="file" id="input-foto" name="foto" class="form-control mb-2" accept=".jpg,.jpeg,.png" required>
        <img id="pratinjau" class="rounded-circle object-fit-cover d-none mb-2" width="96" height="96" alt="Pratinjau">
        <button class="btn btn-success btn-sm">Simpan foto</button>
      </form>
    </div>

    <div class="card card-body mb-3"><h2 class="h5 mb-3">Ganti password</h2>
      <form method="POST" data-validate novalidate>
        <input type="hidden" name="aksi" value="password">
        <div class="mb-2"><input type="password" name="lama" class="form-control" placeholder="Password lama" required></div>
        <div class="mb-2"><input type="password" name="baru" class="form-control" placeholder="Password baru" required></div>
        <div class="mb-2"><input type="password" name="konfirmasi" class="form-control" placeholder="Ulangi password baru" required></div>
        <button class="btn btn-success btn-sm">Simpan password</button>
      </form>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card card-body mb-3"><h2 class="h5 mb-3">Ajukan perubahan data</h2>
      <form method="POST" enctype="multipart/form-data" data-validate novalidate>
        <input type="hidden" name="aksi" value="ubah">
        <div class="mb-3"><label class="form-label">Data yang diubah</label>
          <select name="field" class="form-select"><?php foreach ($boleh as $k => $lbl) echo "<option value='$k'>$lbl</option>"; ?></select></div>
        <div class="mb-3"><label class="form-label">Nilai baru</label><input type="text" name="nilai" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Bukti pendukung (opsional: JPG/PNG/PDF)</label><input type="file" name="bukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
        <button class="btn btn-success">Kirim pengajuan</button>
      </form>
    </div>
    
    <div class="card card-body mb-3"><h2 class="h5 mb-3">Riwayat pengajuan</h2>
      <?php $rw = semua(q("SELECT * FROM change_requests WHERE user_id=? ORDER BY id DESC", "i", [$id]));
      if (!$rw) echo "<p class='text-muted mb-0'>Belum ada pengajuan. Isi formulir di atas untuk memulai.</p>"; else { ?>
      <div class="table-responsive"><table class="table"><thead class="table-light"><tr><th>Data</th><th>Nilai baru</th><th>Status</th><th>Catatan HR</th></tr></thead><tbody>
      <?php foreach ($rw as $r): ?><tr><td><?= e($boleh[$r['field']]) ?></td><td><?= e($r['nilai_baru']) ?></td><td><?= badge($r['status']) ?></td><td><?= e($r['catatan']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php } ?>
    </div>
  </div>
</div>

<?php bottom(); ?>
