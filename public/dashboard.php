<?php
include __DIR__ . "/../src/auth.php"; include __DIR__ . "/../src/layout.php"; wajib_login();
$id = $_SESSION['id'];
$admin = adalah_admin();

if ($admin) {
  $ringkasan = array(
    array('Total karyawan', angka("SELECT COUNT(*) FROM users WHERE role='karyawan'")),
    array('Perubahan data menunggu', angka("SELECT COUNT(*) FROM change_requests WHERE status='Menunggu'")),
    array('Surat perlu diproses', angka("SELECT COUNT(*) FROM letter_requests WHERE status IN ('Menunggu','Diproses')")),
    array('Dokumen belum diverifikasi', angka("SELECT COUNT(*) FROM documents WHERE status='Menunggu'")),
    array('Reset password menunggu', angka("SELECT COUNT(*) FROM password_resets WHERE status='Menunggu' AND expires_at>?", array(time())))
  );
  $surat = daftar("SELECT l.jenis, l.status, l.created_at, u.nama FROM letter_requests l JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC, l.rowid DESC LIMIT 5");
} else {
  $ringkasan = array(
    array('Pengajuan data menunggu', angka("SELECT COUNT(*) FROM change_requests WHERE user_id=? AND status='Menunggu'", array($id))),
    array('Surat sudah selesai', angka("SELECT COUNT(*) FROM letter_requests WHERE user_id=? AND status='Selesai'", array($id))),
    array('Dokumen tersimpan', angka("SELECT COUNT(*) FROM documents WHERE user_id=?", array($id)))
  );
  $surat = daftar("SELECT l.jenis, l.status, l.created_at, u.nama FROM letter_requests l JOIN users u ON u.id=l.user_id WHERE l.user_id=? ORDER BY l.created_at DESC, l.rowid DESC LIMIT 5", array($id));
}
top('Halo, ' . $_SESSION['nama']);
?>
<div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
  <?php foreach ($ringkasan as $r): ?>
    <!-- Kartu sama tinggi; angka selalu menempel di bawah sehingga sejajar walau label 1 atau 2 baris -->
    <div class="kartu flex flex-col">
      <p class="text-sm text-stone-500"><?= $r[0] ?></p>
      <p class="mt-auto pt-1 text-3xl font-semibold"><?= $r[1] ?></p>
    </div>
  <?php endforeach; ?>
</div>

<?php kartu_awal('Permintaan surat terbaru'); ?>
<?php if (count($surat) == 0): kosong('Belum ada permintaan surat.'); else: tabel_awal(array('Surat', 'Pemohon', 'Status', 'Tanggal')); ?>
  <?php foreach ($surat as $s): ?>
    <tr><td data-label="Surat"><?= e($s['jenis']) ?></td><td data-label="Pemohon"><?= e($s['nama']) ?></td><td data-label="Status"><?= lencana($s['status']) ?></td><td data-label="Tanggal"><?= tgl($s['created_at']) ?></td></tr>
  <?php endforeach; tabel_akhir(); endif; ?>
<?php kartu_akhir(); ?>
<?php bottom(); ?>
