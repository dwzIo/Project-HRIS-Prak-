<?php
include "auth.php"; include "layout.php"; wajib_login();
$id = $_SESSION['id'];
$jenis = ['KTP','Kartu Keluarga','NPWP','Ijazah','Sertifikat','BPJS','Kontrak kerja'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (($_POST['aksi'] ?? '') === 'hapus') {
    $d = ambil(q("SELECT * FROM documents WHERE id=? AND user_id=?", "ii", [(int)$_POST['id'], $id]));
    if ($d) { @unlink("uploads/dokumen/" . $d['file']); q("DELETE FROM documents WHERE id=?", "i", [$d['id']]); flash("Dokumen dihapus."); }
  } else {
    list($n, $err) = upload($_FILES['file'] ?? null, 'dokumen', ['pdf','jpg','jpeg','png'], 3145728);
    if (!in_array($_POST['jenis'] ?? '', $jenis)) $err = "Jenis dokumen tidak valid.";
    if ($err) flash($err, 'danger'); else { q("INSERT INTO documents (user_id,jenis,file) VALUES (?,?,?)", "iss", [$id, $_POST['jenis'], $n]); flash("Dokumen berhasil diunggah."); }
  }
  header("Location: dokumen.php"); exit;
}
top("Dokumen saya"); ?>
<div class="row g-3">
  <div class="col-lg-4"><div class="card card-body mb-3"><h2 class="h5 mb-3">Unggah dokumen</h2>
    <form method="POST" enctype="multipart/form-data" data-validate novalidate>
      <div class="mb-3"><label class="form-label">Jenis dokumen</label><select name="jenis" class="form-select"><?php foreach ($jenis as $j) echo "<option>$j</option>"; ?></select></div>
      <div class="mb-3"><label class="form-label">File (PDF/JPG/PNG, maks 3 MB)</label><input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required></div>
      <button class="btn btn-success">Unggah</button>
    </form></div></div>
  <div class="col-lg-8"><div class="card card-body mb-3"><h2 class="h5 mb-3">Dokumen tersimpan</h2>
    <?php $rw = semua(q("SELECT * FROM documents WHERE user_id=? ORDER BY id DESC", "i", [$id]));
    if (!$rw) echo "<p class='text-muted mb-0'>Belum ada dokumen. Unggah KTP atau ijazahmu lebih dulu.</p>"; else { ?>
    <table class="table"><thead class="table-light"><tr><th>Jenis</th><th>Diunggah</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rw as $r): ?><tr><td><a href="uploads/dokumen/<?= e($r['file']) ?>" target="_blank"><?= e($r['jenis']) ?></a></td><td><?= tgl($r['uploaded_at']) ?></td><td><?= badge($r['status']) ?></td>
      <td><form method="POST" data-confirm="Hapus dokumen ini?"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach; ?>
    </tbody></table><?php } ?>
  </div></div>
</div>
<?php bottom(); ?>
