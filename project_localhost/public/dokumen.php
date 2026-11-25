<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_login();

$id = $_SESSION['id'];
$jenis_dok = array('KTP', 'Kartu Keluarga', 'NPWP', 'Ijazah', 'Sertifikat', 'BPJS', 'Kontrak kerja');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isi('aksi') === 'hapus') {
    $d = satu("SELECT * FROM documents WHERE id=? AND user_id=?", array(isi('id'), $id));
    if ($d) {
      @unlink(FOLDER_UPLOAD . '/dokumen/' . $d['file']);
      jalankan("DELETE FROM documents WHERE id=?", array($d['id']));
      pesan('Dokumen dihapus.');
    }
  } elseif (!in_array(isi('jenis'), $jenis_dok)) {
    pesan('Jenis dokumen tidak valid.', 'gagal');
  } else {
    $hasil = upload('file', 'dokumen', array('pdf', 'jpg', 'jpeg', 'png'), 3);
    if ($hasil[1]) {
      pesan($hasil[1], 'gagal');
    } else {
      jalankan("INSERT INTO documents (id,user_id,jenis,file) VALUES (?,?,?,?)", array(buat_id(), $id, isi('jenis'), $hasil[0]));
      pesan('Dokumen berhasil diunggah.');
    }
  }
  pindah('dokumen.php');
}

$dokumen = daftar("SELECT * FROM documents WHERE user_id=? ORDER BY uploaded_at DESC, rowid DESC", array($id));

top('Dokumen saya');
?>
<div class="grid gap-5 lg:grid-cols-3">

  <!-- Form unggah -->
  <div>
    <?php kartu_awal('Unggah dokumen'); ?>
    <form method="POST" enctype="multipart/form-data" data-validasi novalidate>
      <?php pilih('Jenis dokumen', 'jenis', array_combine($jenis_dok, $jenis_dok)); ?>

      <div class="mb-4">
        <label class="label">File (PDF, JPG, atau PNG, maks 3 MB)</label>
        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="kolom" required>
      </div>

      <button class="btn btn-utama">Unggah</button>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <!-- Dokumen tersimpan -->
  <div class="lg:col-span-2">
    <?php kartu_awal('Dokumen tersimpan'); ?>

    <?php if (count($dokumen) == 0): ?>
      <?php kosong('Belum ada dokumen. Unggah KTP atau ijazahmu lebih dulu.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Jenis', 'Diunggah', 'Status', '')); ?>
      <?php foreach ($dokumen as $d): ?>
        <tr>
          <td data-label="Jenis">
            <a href="unduh.php?jenis=dokumen&id=<?= e($d['id']) ?>" target="_blank" class="tautan-dokumen"><?= e($d['jenis']) ?></a>
          </td>
          <td data-label="Diunggah"><?= tgl($d['uploaded_at']) ?></td>
          <td data-label="Status"><?= lencana($d['status']) ?></td>
          <td data-label="">
            <div class="flex gap-1.5">
              <a href="unduh.php?jenis=dokumen&id=<?= e($d['id']) ?>&unduh=1" class="btn btn-zinc btn-kecil">Unduh</a>
              <form method="POST" data-konfirmasi="Hapus dokumen ini?">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= e($d['id']) ?>">
                <button class="btn btn-bahaya btn-kecil">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php tabel_akhir(); ?>
    <?php endif; ?>

    <?php kartu_akhir(); ?>
  </div>

</div>

<?php bottom(); ?>
