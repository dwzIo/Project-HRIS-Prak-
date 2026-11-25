<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();

// Proses approval / penolakan cuti oleh Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_cuti = isi('id');
  $keputusan = isi('keputusan'); // 'Disetujui' atau 'Ditolak'
  $catatan = trim(isi('catatan_admin'));

  if ($id_cuti !== '' && in_array($keputusan, array('Disetujui', 'Ditolak'))) {
    $cuti = satu("SELECT * FROM leave_requests WHERE id=?", array($id_cuti));
    if ($cuti && $cuti['status'] === 'Menunggu') {
      jalankan(
        "UPDATE leave_requests SET status=?, catatan_admin=? WHERE id=?",
        array($keputusan, $catatan, $id_cuti)
      );
      pesan("Permohonan cuti berhasil di-$keputusan.");
    } else {
      pesan('Permohonan cuti ini sudah pernah diputuskan sebelumnya.', 'gagal');
    }
  }
  pindah('admin_cuti.php');
}

// Filter status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Menunggu';
$sql = "SELECT l.*, u.nama, u.nip 
        FROM leave_requests l
        JOIN users u ON u.id = l.user_id";
$params = array();

if ($status_filter !== 'Semua') {
  $sql .= " WHERE l.status = ?";
  $params[] = $status_filter;
}
$sql .= " ORDER BY CASE WHEN l.status='Menunggu' THEN 0 ELSE 1 END, l.created_at DESC";

$daftar_cuti = daftar($sql, $params);

// Statistik Ringkasan
$total_menunggu = angka("SELECT COUNT(*) FROM leave_requests WHERE status='Menunggu'");
$total_disetujui_bulan_ini = angka(
  "SELECT COUNT(*) FROM leave_requests WHERE status='Disetujui' AND strftime('%Y-%m', created_at) = ?",
  array(date('Y-m'))
);
$total_ditolak_bulan_ini = angka(
  "SELECT COUNT(*) FROM leave_requests WHERE status='Ditolak' AND strftime('%Y-%m', created_at) = ?",
  array(date('Y-m'))
);

top('Persetujuan Cuti Karyawan');
?>

<!-- Ringkasan -->
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
  <?php kartu_angka('Menunggu keputusan', $total_menunggu, 'text-black'); ?>
  <?php kartu_angka('Disetujui bulan ini', $total_disetujui_bulan_ini, 'text-black'); ?>
  <?php kartu_angka('Ditolak bulan ini', $total_ditolak_bulan_ini, 'text-black'); ?>
</div>

<!-- Filter status dan pencarian -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <div class="flex items-center gap-1 rounded-md border border-stone-200 bg-white p-1 text-xs">
    <?php
    $tabs = array('Menunggu' => 'Perlu Tindakan (' . $total_menunggu . ')', 'Disetujui' => 'Disetujui', 'Ditolak' => 'Ditolak', 'Semua' => 'Semua Permohonan');
    foreach ($tabs as $key => $label):
      $aktif = ($status_filter === $key) ? 'bg-teal-700 font-medium text-white' : 'text-stone-600 hover:bg-stone-100';
    ?>
      <a href="?status=<?= urlencode($key) ?>" class="rounded px-3 py-1.5 transition-colors <?= $aktif ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="w-full sm:w-64">
    <input id="cari" class="kolom text-xs" placeholder="Cari nama atau NIP...">
  </div>
</div>

<?php kartu_awal('Daftar Permohonan Cuti'); ?>

<?php if (count($daftar_cuti) === 0): ?>
  <?php kosong('Tidak ada permohonan cuti untuk filter ini.'); ?>
<?php else: ?>
  <?php tabel_awal(array('Karyawan', 'Jenis & Durasi', 'Alasan & Bukti', 'Status', 'Tindakan / Keputusan'), 'tabel-cari'); ?>
  <?php foreach ($daftar_cuti as $c): ?>
    <tr>
      <td data-label="Karyawan">
        <span class="font-medium text-stone-900"><?= e($c['nama']) ?></span>
        <br><span class="keterangan">NIP: <?= e($c['nip']) ?></span>
      </td>
      <td data-label="Jenis & Durasi">
        <span class="text-xs font-medium text-stone-900"><?= e($c['jenis_cuti']) ?></span>
        <br><span class="text-xs text-stone-700"><?= tgl($c['tanggal_mulai']) ?> - <?= tgl($c['tanggal_selesai']) ?></span>
        <br><span class="keterangan">(<?= (int)$c['jumlah_hari'] ?> Hari)</span>
      </td>
      <td data-label="Alasan & Bukti">
        <p class="max-w-xs text-xs text-stone-700"><?= e($c['alasan']) ?></p>
        <?php if ($c['bukti']): ?>
          <a href="unduh.php?jenis=bukti_cuti&id=<?= e($c['id']) ?>&unduh=1" class="tautan mt-1 inline-block text-xs">Lampiran bukti (unduh)</a>
        <?php endif; ?>
      </td>
      <td data-label="Status">
        <?= lencana($c['status']) ?>
        <p class="keterangan mt-1"><?= tgl($c['created_at']) ?></p>
      </td>
      <td data-label="Tindakan" class="whitespace-nowrap">
        <?php if ($c['status'] === 'Menunggu'): ?>
          <form method="POST" class="flex w-52 flex-col gap-1.5 max-md:w-full">
            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
            <input type="text" name="catatan_admin" placeholder="Catatan keputusan..." class="kolom px-2 py-1 text-xs">
            <div class="flex gap-1.5">
              <button name="keputusan" value="Disetujui" class="btn btn-sukses btn-kecil flex-1">Setujui</button>
              <button name="keputusan" value="Ditolak" class="btn btn-bahaya btn-kecil flex-1">Tolak</button>
            </div>
          </form>
        <?php else: ?>
          <div class="text-xs">
            <span class="font-medium text-stone-500">Catatan admin:</span>
            <p class="text-stone-700"><?= e($c['catatan_admin'] ?: '-') ?></p>
          </div>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php tabel_akhir(); ?>
<?php endif; ?>

<?php kartu_akhir(); ?>

<?php bottom(); ?>
