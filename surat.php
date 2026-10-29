<?php
include "auth.php";
include "layout.php";
wajib_login();
$id = $_SESSION['id'];
$jenis = ['Surat keterangan kerja','Surat pengantar','Surat rekomendasi','Surat izin pendidikan lanjutan','Salinan kontrak kerja'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $j = $_POST['jenis'] ?? ''; $k = trim($_POST['keperluan'] ?? '');
  if (!in_array($j, $jenis) || $k === '') flash("Pilih jenis surat dan isi keperluan.", 'danger');
  else { q("INSERT INTO letter_requests (user_id,jenis,keperluan) VALUES (?,?,?)", "iss", [$id, $j, $k]); flash("Permintaan surat terkirim."); }
  header("Location: surat.php"); exit;
}
top("Surat keterangan"); ?>

<div class="row g-3">
  <div class="col-lg-4"><div class="card card-body mb-3"><h2 class="h5 mb-3">Minta surat baru</h2>
    <form method="POST" data-validate novalidate>
      <div class="mb-3"><label class="form-label">Jenis surat</label><select name="jenis" class="form-select"><?php foreach ($jenis as $j) echo "<option>" . e($j) . "</option>"; ?></select></div>
      <div class="mb-3"><label class="form-label">Keperluan</label><textarea name="keperluan" rows="3" class="form-control" placeholder="Contoh: pengajuan KPR di bank" required></textarea></div>
      <button class="btn btn-success">Kirim permintaan</button>
    </form></div></div>
  <div class="col-lg-8"><div class="card card-body mb-3"><h2 class="h5 mb-3">Riwayat permintaan</h2>
    <?php $rw = semua(q("SELECT * FROM letter_requests WHERE user_id=? ORDER BY id DESC", "i", [$id]));
    if (!$rw) echo "<p class='text-muted mb-0'>Belum ada permintaan surat.</p>"; else { ?>
    <div class="table-responsive"><table class="table"><thead class="table-light"><tr><th>Surat</th><th>Keperluan</th><th>Status</th><th>File</th></tr></thead><tbody>
    <?php foreach ($rw as $r): ?><tr><td><?= e($r['jenis']) ?><br><small class="text-muted"><?= tgl($r['created_at']) ?></small></td><td><?= e($r['keperluan']) ?></td><td><?= badge($r['status']) ?></td>
      <td><?= $r['file'] ? "<a href='uploads/surat/" . e($r['file']) . "' download>Unduh</a>" : '-' ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php } ?>
  </div></div>
</div>

<?php bottom(); ?>
