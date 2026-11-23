<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_login();

$id = $_SESSION['id'];
$jenis_surat = array('Surat keterangan kerja', 'Surat pengantar', 'Surat rekomendasi', 'Surat izin pendidikan lanjutan', 'Salinan kontrak kerja');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $jenis = isi('jenis');
  $keperluan = trim(isi('keperluan'));

  if (!in_array($jenis, $jenis_surat) || $keperluan === '') {
    pesan('Pilih jenis surat dan isi keperluan.', 'gagal');
  } else {
    jalankan("INSERT INTO letter_requests (id,user_id,jenis,keperluan) VALUES (?,?,?,?)", array(buat_id(), $id, $jenis, $keperluan));
    pesan('Permintaan surat terkirim.');
  }
  pindah('surat.php');
}

$riwayat = daftar("SELECT * FROM letter_requests WHERE user_id=? ORDER BY created_at DESC, rowid DESC", array($id));

top('Surat keterangan');
?>
<div class="grid gap-5 lg:grid-cols-3">

  <!-- Form permintaan -->
  <div>
    <?php kartu_awal('Minta surat baru'); ?>
    <form method="POST" data-validasi novalidate><?= csrf_field() ?>
      <?php pilih('Jenis surat', 'jenis', array_combine($jenis_surat, $jenis_surat)); ?>

      <div class="mb-4">
        <label class="label">Keperluan</label>
        <textarea name="keperluan" rows="3" class="kolom" placeholder="Contoh: pengajuan KPR di bank" required></textarea>
      </div>

      <button class="btn btn-utama">Kirim permintaan</button>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <!-- Riwayat permintaan -->
  <div class="lg:col-span-2">
    <?php kartu_awal('Riwayat permintaan'); ?>

    <?php if (count($riwayat) == 0): ?>
      <?php kosong('Belum ada permintaan surat.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Surat', 'Keperluan', 'Status', 'File')); ?>
      <?php foreach ($riwayat as $r): ?>
        <tr>
          <td data-label="Surat">
            <?= e($r['jenis']) ?><br class="max-md:hidden"><span class="keterangan max-md:ml-2"><?= tgl($r['created_at']) ?></span>
          </td>
          <td data-label="Keperluan"><?= e($r['keperluan']) ?></td>
          <td data-label="Status"><?= lencana($r['status']) ?></td>
          <td data-label="File">
            <?php if ($r['file']): ?>
              <a href="unduh.php?jenis=surat&id=<?= e($r['id']) ?>&unduh=1" class="tautan">Unduh</a>
            <?php else: ?>
              -
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
