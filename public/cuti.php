<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_karyawan();

$id = $_SESSION['id'];
$tahun_ini = date('Y');

// Kuota dasar cuti tahunan
$kuota_tahunan = 12;

// Hitung hari cuti tahunan yang telah disetujui di tahun ini
$terpakai = angka(
  "SELECT COALESCE(SUM(jumlah_hari), 0) FROM leave_requests 
 WHERE user_id=? AND status='Disetujui' AND jenis_cuti='Cuti Tahunan' AND strftime('%Y', tanggal_mulai)=?",
  array($id, $tahun_ini)
);
$sisa_cuti = max(0, $kuota_tahunan - $terpakai);

// Proses form pengajuan & pembatalan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');

  if ($aksi === 'ajukan') {
    $jenis = isi('jenis_cuti');
    $tgl_mulai = isi('tanggal_mulai');
    $tgl_selesai = isi('tanggal_selesai');
    $alasan = trim(isi('alasan'));

    if ($tgl_mulai === '' || $tgl_selesai === '' || $alasan === '') {
      pesan('Semua kolom wajib diisi.', 'gagal');
    } elseif ($tgl_mulai > $tgl_selesai) {
      pesan('Tanggal mulai tidak boleh melebihi tanggal selesai.', 'gagal');
    } else {
      // Hitung selisih hari inklusif
      $d1 = new DateTime($tgl_mulai);
      $d2 = new DateTime($tgl_selesai);
      $durasi = $d2->diff($d1)->days + 1;

      if ($jenis === 'Cuti Tahunan' && $durasi > $sisa_cuti) {
        pesan("Sisa cuti tahunan Anda hanya $sisa_cuti hari. Pengajuan $durasi hari melebihi batas.", 'gagal');
      } else {
        $nama_file = null;
        $error_upload = '';
        if (ada_file('bukti')) {
          $hasil_upload = upload('bukti', 'bukti_cuti', array('pdf', 'jpg', 'jpeg', 'png'), 5);
          if ($hasil_upload[1]) {
            $error_upload = $hasil_upload[1];
          } else {
            $nama_file = $hasil_upload[0];
          }
        }

        if ($error_upload !== '') {
          pesan($error_upload, 'gagal');
        } else {
          jalankan(
            "INSERT INTO leave_requests (id, user_id, jenis_cuti, tanggal_mulai, tanggal_selesai, jumlah_hari, alasan, bukti) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            array(buat_id(), $id, $jenis, $tgl_mulai, $tgl_selesai, $durasi, $alasan, $nama_file)
          );
          pesan("Permohonan cuti ($durasi hari) berhasil diajukan dan sedang menunggu persetujuan HR/Admin.");
        }
      }
    }
  } elseif ($aksi === 'batal') {
    $id_cuti = isi('id');
    $cek = satu("SELECT * FROM leave_requests WHERE id=? AND user_id=? AND status='Menunggu'", array($id_cuti, $id));
    if ($cek) {
      jalankan("DELETE FROM leave_requests WHERE id=?", array($id_cuti));
      pesan('Pengajuan cuti berhasil dibatalkan.');
    } else {
      pesan('Pengajuan tidak dapat dibatalkan.', 'gagal');
    }
  }

  pindah('cuti.php');
}

// Riwayat pengajuan cuti
$riwayat_cuti = daftar(
  "SELECT * FROM leave_requests WHERE user_id=? ORDER BY created_at DESC",
  array($id)
);

$menunggu = angka("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status='Menunggu'", array($id));

top('Pengajuan Cuti Karyawan');
?>

<!-- Ringkasan kuota cuti -->
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
  <?php kartu_angka('Hak cuti tahunan (' . $tahun_ini . ')', $kuota_tahunan . ' Hari'); ?>
  <?php kartu_angka('Cuti terpakai', $terpakai . ' Hari'); ?>
  <?php kartu_angka('Sisa kuota cuti', $sisa_cuti . ' Hari', 'text-teal-700'); ?>
</div>

<div class="grid gap-6 lg:grid-cols-3">

  <!-- Formulir pengajuan cuti -->
  <div class="lg:col-span-1">
    <?php kartu_awal('Ajukan Cuti Baru'); ?>
    <form method="POST" enctype="multipart/form-data" data-validasi novalidate>
      <input type="hidden" name="aksi" value="ajukan">

      <?php pilih('Jenis Cuti', 'jenis_cuti', array(
        'Cuti Tahunan' => 'Cuti Tahunan (Reguler)',
        'Cuti Sakit' => 'Cuti Sakit',
        'Cuti Menikah' => 'Cuti Menikah (3 Hari)',
        'Cuti Melahirkan' => 'Cuti Melahirkan (90 Hari)',
        'Cuti Khusus' => 'Cuti Khusus / Alasan Penting'
      )); ?>

      <div class="grid grid-cols-2 gap-3">
        <?php isian('Tanggal Mulai', 'tanggal_mulai', 'date', '', 'id="tgl-mulai" required'); ?>
        <?php isian('Tanggal Selesai', 'tanggal_selesai', 'date', '', 'id="tgl-selesai" required'); ?>
      </div>

      <div id="info-durasi" class="notif notif-netral mb-3 hidden py-1.5 text-xs">
        Perkiraan durasi: <span id="teks-durasi" class="font-semibold">0</span> hari kalender
      </div>

      <div class="mb-3">
        <label class="label">Alasan Cuti</label>
        <textarea name="alasan" rows="3" class="kolom text-sm" placeholder="Jelaskan keperluan atau alasan cuti Anda..." required></textarea>
      </div>

      <div class="mb-4">
        <label class="label">Lampiran Bukti (Opsional)</label>
        <input type="file" name="bukti" accept=".pdf,.jpg,.jpeg,.png" class="kolom">
        <p class="keterangan mt-1">PDF, JPG, PNG (maks 5 MB). Diperlukan untuk surat dokter / cuti sakit.</p>
      </div>

      <button class="btn btn-utama w-full">Kirimkan Pengajuan</button>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <!-- Riwayat pengajuan cuti -->
  <div class="lg:col-span-2">
    <?php kartu_awal('Riwayat Pengajuan Cuti Saya'); ?>

    <?php if (count($riwayat_cuti) === 0): ?>
      <?php kosong('Anda belum pernah mengajukan permohonan cuti.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Jenis Cuti', 'Periode & Durasi', 'Alasan', 'Status', 'Catatan HR', 'Aksi')); ?>
      <?php foreach ($riwayat_cuti as $c): ?>
        <tr>
          <td data-label="Jenis Cuti">
            <span class="font-medium text-stone-800"><?= e($c['jenis_cuti']) ?></span>
            <br><span class="keterangan">Diajukan: <?= tgl($c['created_at']) ?></span>
          </td>
          <td data-label="Periode & Durasi">
            <span class="text-xs font-medium text-stone-700"><?= tgl($c['tanggal_mulai']) ?> s/d <?= tgl($c['tanggal_selesai']) ?></span>
            <br><span class="keterangan"><?= (int)$c['jumlah_hari'] ?> Hari</span>
          </td>
          <td data-label="Alasan">
            <span class="text-xs text-stone-700"><?= e($c['alasan']) ?></span>
            <?php if ($c['bukti']): ?>
              <br><a href="unduh.php?jenis=bukti_cuti&id=<?= e($c['id']) ?>&unduh=1" class="tautan text-xs">Lihat lampiran</a>
            <?php endif; ?>
          </td>
          <td data-label="Status"><?= lencana($c['status']) ?></td>
          <td data-label="Catatan HR" class="text-xs text-stone-500"><?= e($c['catatan_admin'] ?: '-') ?></td>
          <td data-label="Aksi" class="whitespace-nowrap">
            <?php if ($c['status'] === 'Menunggu'): ?>
              <form method="POST" data-konfirmasi="Batalkan permohonan cuti ini?">
                <input type="hidden" name="aksi" value="batal">
                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                <button class="btn btn-bahaya btn-kecil">Batalkan</button>
              </form>
            <?php else: ?>
              <span class="keterangan">Selesai</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php tabel_akhir(); ?>
    <?php endif; ?>

    <?php kartu_akhir(); ?>
  </div>

</div>

<?php bottom(); ?>
