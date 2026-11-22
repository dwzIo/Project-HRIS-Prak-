<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_login();

// Pastikan sinkronisasi Alfa otomatis berjalan
sinkron_alfa_otomatis();

$id = $_SESSION['id'];
$admin = adalah_admin();
$hari_ini = date('Y-m-d');
$waktu_sekarang = date('H:i:s');

if ($admin) {
  // Data Portal Admin
  $total_karyawan = angka("SELECT COUNT(*) FROM users WHERE role='karyawan'");
  $total_dept = angka("SELECT COUNT(*) FROM departments");
  $hadir_hari_ini = angka("SELECT COUNT(*) FROM attendances WHERE tanggal=? AND status IN ('Hadir','Terlambat')", array($hari_ini));
  $cuti_menunggu = angka("SELECT COUNT(*) FROM leave_requests WHERE status='Menunggu'");
  $surat_menunggu = angka("SELECT COUNT(*) FROM letter_requests WHERE status IN ('Menunggu','Diproses')");
  $data_menunggu = angka("SELECT COUNT(*) FROM change_requests WHERE status='Menunggu'");

  $ringkasan = array(
    array('Total Karyawan', $total_karyawan, 'admin_karyawan.php'),
    array('Departemen', $total_dept, 'admin_departemen.php'),
    array('Hadir Hari Ini', $hadir_hari_ini, 'admin_absensi.php'),
    array('Cuti Perlu Disetujui', $cuti_menunggu, 'admin_cuti.php'),
    array('Surat Perlu Diproses', $surat_menunggu, 'admin_pengajuan.php'),
    array('Pengajuan Data', $data_menunggu, 'admin_pengajuan.php')
  );

  // Permohonan cuti terbaru menunggu tindakan
  $cuti_terbaru = daftar("
    SELECT l.*, u.nama, u.nip 
    FROM leave_requests l 
    JOIN users u ON u.id = l.user_id 
    WHERE l.status='Menunggu' 
    ORDER BY l.created_at DESC 
    LIMIT 5
  ");

  // Permintaan surat terbaru
  $surat_terbaru = daftar("
    SELECT l.id, l.jenis, l.status, l.created_at, u.nama 
    FROM letter_requests l 
    JOIN users u ON u.id = l.user_id 
    ORDER BY l.created_at DESC 
    LIMIT 5
  ");
} else {
  // Data Portal Karyawan
  $absen_hari_ini = satu("SELECT * FROM attendances WHERE user_id=? AND tanggal=?", array($id, $hari_ini));

  // Cuti tahunan
  $tahun_ini = date('Y');
  $cuti_terpakai = angka("SELECT COALESCE(SUM(jumlah_hari),0) FROM leave_requests WHERE user_id=? AND status='Disetujui' AND jenis_cuti='Cuti Tahunan' AND strftime('%Y', tanggal_mulai)=?", array($id, $tahun_ini));
  $sisa_cuti = max(0, 12 - $cuti_terpakai);

  // Slip gaji terakhir
  $slip_terakhir = satu("SELECT * FROM payrolls WHERE user_id=? AND status IN ('Diterbitkan','Dibayar') ORDER BY periode_tahun DESC, periode_bulan DESC LIMIT 1", array($id));

  // Pengajuan dalam proses
  $pengajuan_cuti_menunggu = angka("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status='Menunggu'", array($id));
  $pengajuan_surat_menunggu = angka("SELECT COUNT(*) FROM letter_requests WHERE user_id=? AND status IN ('Menunggu','Diproses')", array($id));
  $pengajuan_data_menunggu = angka("SELECT COUNT(*) FROM change_requests WHERE user_id=? AND status='Menunggu'", array($id));
  $total_pengajuan_menunggu = $pengajuan_cuti_menunggu + $pengajuan_surat_menunggu + $pengajuan_data_menunggu;

  $ringkasan = array(
    array('Status Absensi Hari Ini', $absen_hari_ini ? $absen_hari_ini['status'] : 'Belum Absen', 'absensi.php'),
    array('Sisa Cuti Tahunan', $sisa_cuti . ' Hari', 'cuti.php'),
    array('Slip Gaji Terakhir', $slip_terakhir ? rupiah($slip_terakhir['total_gaji']) : 'Belum Terbit', 'slip_gaji.php'),
    array('Pengajuan Menunggu', $total_pengajuan_menunggu, 'surat.php')
  );

  // Riwayat cuti & surat terbaru milik sendiri
  $cuti_terbaru = daftar("SELECT * FROM leave_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 5", array($id));
  $surat_terbaru = daftar("SELECT l.id, l.jenis, l.status, l.created_at, u.nama FROM letter_requests l JOIN users u ON u.id=l.user_id WHERE l.user_id=? ORDER BY l.created_at DESC LIMIT 5", array($id));
}

top('Selamat Datang, ' . $_SESSION['nama']);
?>

<!-- Ringkasan angka dan status -->
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 <?= $admin ? 'xl:grid-cols-6' : 'xl:grid-cols-4' ?>">
  <?php foreach ($ringkasan as $r): ?>
    <?php kartu_angka($r[0], $r[1], 'text-stone-900', 'kartu', $r[2]); ?>
  <?php endforeach; ?>
</div>

<?php if (!$admin && !$absen_hari_ini): ?>
  <!-- Pengingat absensi masuk untuk karyawan -->
  <div class="notif notif-netral mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <h3 class="text-sm font-semibold text-stone-900">Anda belum melakukan absensi masuk hari ini</h3>
      <p class="keterangan mt-0.5">Tanggal hari ini: <?= tgl($hari_ini) ?>. Catat kehadiran Anda untuk memulai jam kerja.</p>
    </div>
    <a href="absensi.php" class="btn btn-utama btn-kecil">Buka halaman absensi</a>
  </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-2">

  <!-- Kiri: permohonan cuti -->
  <div>
    <?php kartu_awal($admin ? 'Permohonan Cuti Perlu Disetujui' : 'Pengajuan Cuti Terkini'); ?>

    <?php if (count($cuti_terbaru) === 0): ?>
      <?php kosong($admin ? 'Tidak ada permohonan cuti yang menunggu persetujuan.' : 'Belum ada pengajuan cuti.'); ?>
    <?php else: ?>
      <?php tabel_awal(array($admin ? 'Karyawan' : 'Jenis Cuti', 'Durasi', 'Status', '')); ?>
      <?php foreach ($cuti_terbaru as $c): ?>
        <tr>
          <td data-label="<?= $admin ? 'Karyawan' : 'Jenis Cuti' ?>">
            <?php if ($admin): ?>
              <span class="font-medium text-stone-800"><?= e($c['nama']) ?></span>
              <br><span class="keterangan"><?= e($c['jenis_cuti']) ?></span>
            <?php else: ?>
              <span class="font-medium text-stone-800"><?= e($c['jenis_cuti']) ?></span>
              <br><span class="keterangan"><?= tgl($c['created_at']) ?></span>
            <?php endif; ?>
          </td>
          <td data-label="Durasi" class="text-xs font-medium text-stone-700"><?= (int)$c['jumlah_hari'] ?> Hari</td>
          <td data-label="Status"><?= lencana($c['status']) ?></td>
          <td data-label="" class="text-right">
            <a href="<?= $admin ? 'admin_cuti.php' : 'cuti.php' ?>" class="btn btn-garis btn-kecil">Detail</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php tabel_akhir(); ?>
    <?php endif; ?>

    <?php kartu_akhir(); ?>
  </div>

  <!-- Kanan: permintaan surat -->
  <div>
    <?php kartu_awal($admin ? 'Permintaan Surat Keterangan Terbaru' : 'Permintaan Surat Saya'); ?>

    <?php if (count($surat_terbaru) === 0): ?>
      <?php kosong('Belum ada permintaan surat.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Surat', 'Pemohon', 'Status', 'Tanggal')); ?>
      <?php foreach ($surat_terbaru as $s): ?>
        <tr>
          <td data-label="Surat" class="font-medium text-stone-800"><?= e($s['jenis']) ?></td>
          <td data-label="Pemohon" class="text-xs text-stone-600"><?= e($s['nama']) ?></td>
          <td data-label="Status"><?= lencana($s['status']) ?></td>
          <td data-label="Tanggal" class="text-xs text-stone-500"><?= tgl($s['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php tabel_akhir(); ?>
    <?php endif; ?>

    <?php kartu_akhir(); ?>
  </div>

</div>

<?php bottom(); ?>
