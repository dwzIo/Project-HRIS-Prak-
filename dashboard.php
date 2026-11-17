<?php
include "auth.php"; include "layout.php"; wajib_login();
$id = $_SESSION['id']; $adm = $_SESSION['role'] === 'admin';
top("Halo, " . $_SESSION['nama']);
if ($adm) {
  $k = [["Total karyawan", hitung("SELECT COUNT(*) FROM users WHERE role='karyawan'")],
        ["Perubahan data menunggu", hitung("SELECT COUNT(*) FROM change_requests WHERE status='Menunggu'")],
        ["Surat perlu diproses", hitung("SELECT COUNT(*) FROM letter_requests WHERE status IN ('Menunggu','Diproses')")],
        ["Dokumen belum diverifikasi", hitung("SELECT COUNT(*) FROM documents WHERE status='Menunggu'")]];
} else {
  $k = [["Pengajuan data menunggu", hitung("SELECT COUNT(*) FROM change_requests WHERE user_id=? AND status='Menunggu'", "i", [$id])],
        ["Surat selesai diunduh", hitung("SELECT COUNT(*) FROM letter_requests WHERE user_id=? AND status='Selesai'", "i", [$id])],
        ["Dokumen tersimpan", hitung("SELECT COUNT(*) FROM documents WHERE user_id=?", "i", [$id])]];
}
?>
<div class="row g-3 mb-4">
  <?php foreach ($k as $s): ?><div class="col-md-3 col-6"><div class="card border-warning border-start border-4 h-100"><div class="card-body"><div class="fs-2 fw-bold text-success"><?= $s[1] ?></div><?= $s[0] ?></div></div></div><?php endforeach; ?>
</div>
<div class="card card-body mb-3"><h2 class="h5 mb-3">Aktivitas terbaru</h2>
<?php
$sql = "SELECT l.jenis AS judul, l.status, l.created_at, u.nama FROM letter_requests l JOIN users u ON u.id=l.user_id " . ($adm ? "" : "WHERE l.user_id=$id ") . "ORDER BY l.id DESC LIMIT 5";
$rows = semua(q($sql));
if (!$rows) echo "<p class='text-muted mb-0'>Belum ada aktivitas.</p>";
else { echo "<table class='table'><thead class='table-light'><tr><th>Surat</th><th>Pemohon</th><th>Status</th><th>Tanggal</th></tr></thead><tbody>";
  foreach ($rows as $r) echo "<tr><td>" . e($r['judul']) . "</td><td>" . e($r['nama']) . "</td><td>" . badge($r['status']) . "</td><td>" . tgl($r['created_at']) . "</td></tr>";
  echo "</tbody></table>"; } ?>
</div>
<?php bottom(); ?>
