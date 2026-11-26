<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_login();

sinkron_alfa_otomatis();

$id = $_SESSION['id'];
$admin = adalah_admin();
$hari_ini = date('Y-m-d');
$waktu_sekarang = date('H:i:s');

if ($admin) {
  $total_karyawan = angka("SELECT COUNT(*) FROM users WHERE role='karyawan'");
  $hadir_hari_ini = angka("SELECT COUNT(*) FROM attendances WHERE tanggal=? AND status IN ('Hadir','Terlambat')", array($hari_ini));
  $cuti_menunggu = angka("SELECT COUNT(*) FROM leave_requests WHERE status='Menunggu'");
  $surat_menunggu = angka("SELECT COUNT(*) FROM letter_requests WHERE status IN ('Menunggu','Diproses')");
  $data_menunggu = angka("SELECT COUNT(*) FROM change_requests WHERE status='Menunggu'") + angka("SELECT COUNT(*) FROM password_resets WHERE status='Menunggu'") + angka("SELECT COUNT(*) FROM documents WHERE status='Menunggu'");

  $ringkasan = array(
    array('Total Karyawan', $total_karyawan, 'admin_karyawan.php'),
    array('Hadir Hari Ini', $hadir_hari_ini, 'admin_absensi.php'),
    array('Cuti Perlu Disetujui', $cuti_menunggu, 'admin_cuti.php'),
    array('Surat Perlu Diproses', $surat_menunggu, 'admin_pengajuan.php'),
    array('Pengajuan Data', $data_menunggu, 'admin_pengajuan.php')
  );

  $cuti_terbaru = daftar("
    SELECT l.*, u.nama, u.nip 
    FROM leave_requests l 
    JOIN users u ON u.id = l.user_id 
    WHERE l.status = 'Menunggu' 
    ORDER BY l.created_at DESC 
    LIMIT 5
  ");

  $surat_terbaru = daftar("
    SELECT l.*, u.nama, u.nip 
    FROM letter_requests l 
    JOIN users u ON u.id = l.user_id 
    WHERE l.status IN ('Menunggu','Diproses') 
    ORDER BY l.created_at DESC 
    LIMIT 5
  ");
} else {
  $absen_hari_ini = satu("SELECT * FROM attendances WHERE user_id=? AND tanggal=?", array($id, $hari_ini));

  $tahun_ini = date('Y');
  $cuti_terpakai = angka("SELECT COALESCE(SUM(jumlah_hari),0) FROM leave_requests WHERE user_id=? AND status='Disetujui' AND jenis_cuti='Cuti Tahunan' AND strftime('%Y', tanggal_mulai)=?", array($id, $tahun_ini));
  $sisa_cuti = max(0, 12 - $cuti_terpakai);

  $pengajuan_cuti_menunggu = angka("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status='Menunggu'", array($id));
  $pengajuan_surat_menunggu = angka("SELECT COUNT(*) FROM letter_requests WHERE user_id=? AND status IN ('Menunggu','Diproses')", array($id));
  $pengajuan_data_menunggu = angka("SELECT COUNT(*) FROM change_requests WHERE user_id=? AND status='Menunggu'", array($id));
  $total_pengajuan_menunggu = $pengajuan_cuti_menunggu + $pengajuan_surat_menunggu + $pengajuan_data_menunggu;

  $ringkasan = array(
    array('Status Absensi Hari Ini', $absen_hari_ini ? $absen_hari_ini['status'] : 'Belum Absen', 'absensi.php'),
    array('Sisa Cuti Tahunan', $sisa_cuti . ' Hari', 'cuti.php'),
    array('Pengajuan Menunggu', $total_pengajuan_menunggu, 'surat.php')
  );

  $cuti_terbaru = daftar("SELECT * FROM leave_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 5", array($id));
  $surat_terbaru = daftar("SELECT l.id, l.jenis, l.status, l.created_at, u.nama FROM letter_requests l JOIN users u ON u.id=l.user_id WHERE l.user_id=? ORDER BY l.created_at DESC LIMIT 5", array($id));
}

top('Selamat Datang, ' . $_SESSION['nama']);
?>

<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 <?= $admin ? 'xl:grid-cols-5' : 'xl:grid-cols-3' ?>">
  <?php foreach ($ringkasan as $r): ?>
    <a href="<?= $r[2] ?>" class="kartu block transition-all hover:border-stone-300 hover:bg-stone-100 hover:shadow-sm">
      <p class="text-xs text-stone-500"><?= e($r[0]) ?></p>
      <p class="mt-1 text-2xl font-bold text-stone-900"><?= e($r[1]) ?></p>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid gap-6 lg:grid-cols-2">
  <div>
    <?php kartu_awal($admin ? 'Permohonan Cuti Menunggu Tindakan' : 'Riwayat Pengajuan Cuti Terbaru'); ?>
    <?php if (count($cuti_terbaru) === 0): ?>
      <?php kosong($admin ? 'Tidak ada permohonan cuti yang perlu ditinjau.' : 'Belum ada riwayat pengajuan cuti.'); ?>
    <?php else: ?>
      <div class="divide-y divide-stone-100">
        <?php foreach ($cuti_terbaru as $c): ?>
          <div class="flex items-center justify-between py-2.5 text-xs">
            <div>
              <span class="font-semibold text-stone-900"><?= e($admin ? $c['nama'] : $c['jenis_cuti']) ?></span>
              <span class="text-stone-400">• <?= tgl($c['tanggal_mulai']) ?> (<?= $c['jumlah_hari'] ?> hari)</span>
            </div>
            <div>
              <?= lencana($c['status']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-3 text-right">
        <a href="<?= $admin ? 'admin_cuti.php' : 'cuti.php' ?>" class="text-xs font-semibold text-black hover:underline">Lihat Semua →</a>
      </div>
    <?php endif; ?>
    <?php kartu_akhir(); ?>
  </div>

  <div>
    <?php kartu_awal($admin ? 'Pengajuan Surat Menunggu Diproses' : 'Riwayat Pengajuan Surat Terbaru'); ?>
    <?php if (count($surat_terbaru) === 0): ?>
      <?php kosong($admin ? 'Tidak ada permohonan surat yang perlu diproses.' : 'Belum ada riwayat pengajuan surat.'); ?>
    <?php else: ?>
      <div class="divide-y divide-stone-100">
        <?php foreach ($surat_terbaru as $s): ?>
          <div class="flex items-center justify-between py-2.5 text-xs">
            <div>
              <span class="font-semibold text-stone-900"><?= e($admin ? $s['nama'] : $s['jenis']) ?></span>
              <span class="text-stone-400">• <?= tgl($s['created_at']) ?></span>
            </div>
            <div>
              <?= lencana($s['status']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-3 text-right">
        <a href="<?= $admin ? 'admin_pengajuan.php' : 'surat.php' ?>" class="text-xs font-semibold text-black hover:underline">Lihat Semua →</a>
      </div>
    <?php endif; ?>
    <?php kartu_akhir(); ?>
  </div>
</div>

<?php bottom(); ?>