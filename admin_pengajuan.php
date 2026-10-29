<?php
include "auth.php"; include "layout.php"; wajib_admin();
$boleh = ['alamat'=>'Alamat','no_hp'=>'Nomor HP','email'=>'Email','kontak_darurat'=>'Kontak darurat'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $a = $_POST['aksi']; $id = (int)$_POST['id'];
  if ($a === 'data') {
    $c = ambil(q("SELECT * FROM change_requests WHERE id=? AND status='Menunggu'", "i", [$id]));
    if ($c) {
      $st = $_POST['keputusan'] === 'Disetujui' ? 'Disetujui' : 'Ditolak';
      if ($st === 'Disetujui' && isset($boleh[$c['field']])) q("UPDATE users SET {$c['field']}=? WHERE id=?", "si", [$c['nilai_baru'], $c['user_id']]);
      q("UPDATE change_requests SET status=?, catatan=? WHERE id=?", "ssi", [$st, trim($_POST['catatan']), $id]);
      flash("Pengajuan $st.");
    }
  } elseif ($a === 'surat') {
    $st = in_array($_POST['status'], ['Diproses','Selesai','Ditolak']) ? $_POST['status'] : 'Diproses'; $f = null;
    if (($_FILES['file']['error'] ?? 4) === 0) { list($f, $err) = upload($_FILES['file'], 'surat', ['pdf','doc','docx'], 5242880); if ($err) { flash($err, 'danger'); header("Location: admin_pengajuan.php"); exit; } }
    if ($f) q("UPDATE letter_requests SET status=?, file=? WHERE id=?", "ssi", [$st, $f, $id]); else q("UPDATE letter_requests SET status=? WHERE id=?", "si", [$st, $id]);
    flash("Status surat diperbarui.");
  } elseif ($a === 'dokumen') {
    $st = $_POST['status'] === 'Terverifikasi' ? 'Terverifikasi' : 'Perlu Diperbarui';
    q("UPDATE documents SET status=? WHERE id=?", "si", [$st, $id]); flash("Status dokumen diperbarui.");
  }
  header("Location: admin_pengajuan.php"); exit;
}
top("Pengajuan karyawan"); ?>
<div class="card card-body mb-3"><h2 class="h5 mb-3">Perubahan data pribadi</h2>
<?php $rw = semua(q("SELECT c.*, u.nama, u.nip FROM change_requests c JOIN users u ON u.id=c.user_id ORDER BY c.status='Menunggu' DESC, c.id DESC"));
if (!$rw) echo "<p class='text-muted mb-0'>Tidak ada pengajuan.</p>"; else { ?>
<div class="table-responsive"><table class="table align-middle"><thead class="table-light"><tr><th>Karyawan</th><th>Data</th><th>Lama</th><th>Baru</th><th>Bukti</th><th>Keputusan</th></tr></thead><tbody>
<?php foreach ($rw as $r): ?><tr><td><?= e($r['nama']) ?><br><small class="text-muted"><?= e($r['nip']) ?></small></td><td><?= e($boleh[$r['field']] ?? $r['field']) ?></td><td><?= e($r['nilai_lama']) ?></td><td><?= e($r['nilai_baru']) ?></td>
  <td><?= $r['bukti'] ? "<a href='uploads/bukti/" . e($r['bukti']) . "' target='_blank'>Lihat</a>" : '-' ?></td>
  <td><?php if ($r['status'] === 'Menunggu'): ?>
    <form method="POST" class="d-flex gap-1"><input type="hidden" name="aksi" value="data"><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <input name="catatan" class="form-control form-control-sm" placeholder="Catatan">
      <button name="keputusan" value="Disetujui" class="btn btn-sm btn-success">Setujui</button><button name="keputusan" value="Ditolak" class="btn btn-sm btn-outline-danger">Tolak</button></form>
    <?php else: echo badge($r['status']) . " " . e($r['catatan']); endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php } ?></div>

<div class="card card-body mb-3"><h2 class="h5 mb-3">Permintaan surat</h2>
<?php $rw = semua(q("SELECT l.*, u.nama FROM letter_requests l JOIN users u ON u.id=l.user_id ORDER BY l.status IN ('Menunggu','Diproses') DESC, l.id DESC"));
if (!$rw) echo "<p class='text-muted mb-0'>Tidak ada permintaan surat.</p>"; else { ?>
<div class="table-responsive"><table class="table align-middle"><thead class="table-light"><tr><th>Karyawan</th><th>Surat</th><th>Keperluan</th><th>Status</th><th>Proses</th></tr></thead><tbody>
<?php foreach ($rw as $r): ?><tr><td><?= e($r['nama']) ?></td><td><?= e($r['jenis']) ?></td><td><?= e($r['keperluan']) ?></td><td><?= badge($r['status']) ?></td>
  <td><form method="POST" enctype="multipart/form-data" class="d-flex gap-1"><input type="hidden" name="aksi" value="surat"><input type="hidden" name="id" value="<?= $r['id'] ?>">
    <select name="status" class="form-select form-select-sm"><?php foreach (['Diproses','Selesai','Ditolak'] as $s) echo "<option" . ($s === $r['status'] ? ' selected' : '') . ">$s</option>"; ?></select>
    <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.doc,.docx"><button class="btn btn-sm btn-success">Simpan</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php } ?></div>

<div class="card card-body mb-3"><h2 class="h5 mb-3">Verifikasi dokumen</h2>
<?php $rw = semua(q("SELECT d.*, u.nama FROM documents d JOIN users u ON u.id=d.user_id ORDER BY d.status='Menunggu' DESC, d.id DESC"));
if (!$rw) echo "<p class='text-muted mb-0'>Belum ada dokumen yang diunggah.</p>"; else { ?>
<table class="table align-middle"><thead class="table-light"><tr><th>Karyawan</th><th>Dokumen</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rw as $r): ?><tr><td><?= e($r['nama']) ?></td><td><a href="uploads/dokumen/<?= e($r['file']) ?>" target="_blank"><?= e($r['jenis']) ?></a></td><td><?= badge($r['status']) ?></td>
  <td><form method="POST" class="d-flex gap-1"><input type="hidden" name="aksi" value="dokumen"><input type="hidden" name="id" value="<?= $r['id'] ?>">
    <button name="status" value="Terverifikasi" class="btn btn-sm btn-success">Verifikasi</button><button name="status" value="Perlu Diperbarui" class="btn btn-sm btn-outline-danger">Perlu diperbarui</button></form></td></tr><?php endforeach; ?>
</tbody></table><?php } ?></div>
<?php bottom(); ?>
