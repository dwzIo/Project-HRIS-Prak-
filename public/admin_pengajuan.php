<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();
$boleh = array('alamat' => 'Alamat', 'no_hp' => 'Nomor HP', 'email' => 'Email', 'kontak_darurat' => 'Kontak darurat');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');
  $id = isi('id');

  if ($aksi === 'data') {
    // Cari data pengajuan yang diklik admin
    $c = satu("SELECT * FROM change_requests WHERE id=? AND status='Menunggu'", array($id));
    if ($c) {
      $status = (isi('keputusan') === 'Disetujui') ? 'Disetujui' : 'Ditolak';
      $catatan = trim(isi('catatan'));

      // Ambil SEMUA data perubahan milik karyawan ini yang diajukan di waktu (created_at) yang sama
      $semua_perubahan = daftar(
        "SELECT * FROM change_requests WHERE user_id=? AND created_at=? AND status='Menunggu'",
        array($c['user_id'], $c['created_at'])
      );

      // Jika disetujui, update semua data baru ke tabel users
      if ($status === 'Disetujui') {
        foreach ($semua_perubahan as $item) {
          if (isset($boleh[$item['field']])) {
            jalankan("UPDATE users SET " . $item['field'] . "=? WHERE id=?", array($item['nilai_baru'], $item['user_id']));
          }
        }
      }

      // Update status semua baris dalam pengajuan tersebut
      jalankan(
        "UPDATE change_requests SET status=?, catatan=? WHERE user_id=? AND created_at=? AND status='Menunggu'",
        array($status, $catatan, $c['user_id'], $c['created_at'])
      );

      pesan("Pengajuan berhasil di-$status.");
    }
  } elseif ($aksi === 'reset') {
    $r = satu("SELECT * FROM password_resets WHERE id=?", array($id));
    if ($r && $r['status'] === 'Menunggu' && $r['expires_at'] >= time()) {
      $status = (isi('keputusan') === 'Disetujui') ? 'Disetujui' : 'Ditolak';
      $berlaku = ($status === 'Disetujui') ? 3600 : 86400;
      jalankan("UPDATE password_resets SET status=?, catatan=?, expires_at=? WHERE id=?", array($status, trim(isi('catatan')), time() + $berlaku, $id));
      pesan('Permintaan reset password ' . strtolower($status) . '.');
    } else {
      pesan('Permintaan sudah diputuskan atau kedaluwarsa.', 'gagal');
    }
  } elseif ($aksi === 'surat') {
    $status = isi('status');
    if (!in_array($status, array('Diproses', 'Selesai', 'Ditolak'))) {
      $status = 'Diproses';
    }
    $nama_file = null;
    $error = '';
    if (ada_file('file')) {
      $hasil = upload('file', 'surat', array('pdf', 'doc', 'docx'), 5);
      if ($hasil[1]) {
        $error = $hasil[1];
      } else {
        $nama_file = $hasil[0];
      }
    }
    if ($error !== '') {
      pesan($error, 'gagal');
    } else {
      if ($nama_file) {
        jalankan("UPDATE letter_requests SET status=?, file=? WHERE id=?", array($status, $nama_file, $id));
      } else {
        jalankan("UPDATE letter_requests SET status=? WHERE id=?", array($status, $id));
      }
      pesan('Status surat diperbarui.');
    }
  } elseif ($aksi === 'dokumen') {
    $status = (isi('status') === 'Terverifikasi') ? 'Terverifikasi' : 'Perlu Diperbarui';
    jalankan("UPDATE documents SET status=? WHERE id=?", array($status, $id));
    pesan('Status dokumen diperbarui.');
  }
  pindah('admin_pengajuan.php');
}

$urut_menunggu = "CASE WHEN status='Menunggu' THEN 0 ELSE 1 END";
$perubahan = daftar("SELECT c.*, u.nama, u.nip FROM change_requests c JOIN users u ON u.id=c.user_id ORDER BY " . str_replace('status', 'c.status', $urut_menunggu) . ", c.created_at DESC, c.rowid DESC");
$surat = daftar("SELECT l.*, u.nama FROM letter_requests l JOIN users u ON u.id=l.user_id ORDER BY CASE WHEN l.status IN ('Menunggu','Diproses') THEN 0 ELSE 1 END, l.created_at DESC, l.rowid DESC");
$dokumen = daftar("SELECT d.*, u.nama FROM documents d JOIN users u ON u.id=d.user_id ORDER BY " . str_replace('status', 'd.status', $urut_menunggu) . ", d.uploaded_at DESC, d.rowid DESC");
$resets = daftar("SELECT r.*, u.nip, u.nama FROM password_resets r JOIN users u ON u.id=r.user_id ORDER BY CASE WHEN r.status='Menunggu' THEN 0 ELSE 1 END, r.created_at DESC");

top('Pengajuan karyawan');
?>
<?php kartu_awal('Permintaan reset password'); ?>
<p class="-mt-2 mb-4 text-sm text-stone-500">Karyawan baru bisa mengganti passwordnya setelah permintaan disetujui di sini.</p>
<?php if (count($resets) == 0): kosong('Tidak ada permintaan reset password.');
else: tabel_awal(array('Karyawan', 'Diminta', 'Status', 'Keputusan')); ?>
  <?php foreach ($resets as $r):
    $statusTampil = $r['status'];
    if (($r['status'] === 'Menunggu' || $r['status'] === 'Disetujui') && $r['expires_at'] < time()) {
      $statusTampil = 'Kedaluwarsa';
    }
  ?>
    <tr>
      <td data-label="Karyawan"><?= e($r['nama']) ?><br class="max-md:hidden"><span class="text-xs text-stone-500 max-md:ml-2"><?= e($r['nip']) ?></span></td>
      <td data-label="Diminta"><?= e(date('d M Y H:i', $r['created_at'])) ?></td>
      <td data-label="Status"><?= lencana($statusTampil) ?></td>
      <td data-label="Keputusan">
        <?php if ($statusTampil === 'Menunggu'): ?>
          <form method="POST" class="flex flex-wrap items-center gap-1">
            <input type="hidden" name="aksi" value="reset"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
            <input name="catatan" placeholder="Catatan" class="kolom w-32 py-1">
            <button name="keputusan" value="Disetujui" class="btn btn-utama btn-kecil">Setujui</button>
            <button name="keputusan" value="Ditolak" class="btn btn-bahaya btn-kecil">Tolak</button>
          </form>
        <?php else: ?><span class="text-xs text-stone-500"><?= e($r['catatan']) ?></span><?php endif; ?>
      </td>
    </tr>
<?php endforeach;
  tabel_akhir();
endif; ?>
<?php kartu_akhir(); ?>

<?php kartu_awal('Perubahan data pribadi'); ?>
<?php if (count($perubahan) == 0): kosong('Tidak ada pengajuan.');
else: tabel_awal(array('Karyawan', 'Waktu', 'Data', 'Lama', 'Baru', 'Bukti', 'Keputusan')); ?>
  <?php foreach ($perubahan as $r): ?>
    <tr>
      <td data-label="Karyawan"><?= e($r['nama']) ?><br class="max-md:hidden"><span class="text-xs text-stone-500 max-md:ml-2"><?= e($r['nip']) ?></span></td>
      <td data-label="Waktu"><?= tgl($r['created_at']) ?></td>
      <td data-label="Data"><?= $boleh[$r['field']] ?></td>
      <td data-label="Lama"><?= e($r['nilai_lama']) ?: '-' ?></td>
      <td data-label="Baru"><?= e($r['nilai_baru']) ?></td>
      <td data-label="Bukti"><?php if ($r['bukti']): ?><a class="text-teal-700 underline" href="unduh.php?jenis=bukti&id=<?= e($r['id']) ?>" target="_blank">Lihat</a><?php else: ?>-<?php endif; ?></td>
      <td data-label="Keputusan">
        <?php if ($r['status'] === 'Menunggu'): ?>
          <form method="POST" class="flex flex-wrap items-center gap-1">
            <input type="hidden" name="aksi" value="data"><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <input name="catatan" placeholder="Catatan" class="kolom w-32 py-1">
            <button name="keputusan" value="Disetujui" class="btn btn-utama btn-kecil" title="Menyetujui perubahan ini dan perubahan lain di waktu yang sama">Setujui</button>
            <button name="keputusan" value="Ditolak" class="btn btn-bahaya btn-kecil">Tolak</button>
          </form>
          <?php else: ?><?= lencana($r['status']) ?> <span class="text-xs text-stone-500"><?= e($r['catatan']) ?></span><?php endif; ?>
      </td>
    </tr>
<?php endforeach;
  tabel_akhir();
endif; ?>
<?php kartu_akhir(); ?>

<?php kartu_awal('Permintaan surat'); ?>
<?php if (count($surat) == 0): kosong('Tidak ada permintaan surat.');
else: tabel_awal(array('Karyawan', 'Surat', 'Keperluan', 'Status', 'Proses')); ?>
  <?php foreach ($surat as $r): ?>
    <tr>
      <td data-label="Karyawan"><?= e($r['nama']) ?></td>
      <td data-label="Surat"><?= e($r['jenis']) ?></td>
      <td data-label="Keperluan"><?= e($r['keperluan']) ?></td>
      <td data-label="Status"><?= lencana($r['status']) ?></td>
      <td data-label="Proses">
        <form method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-1">
          <input type="hidden" name="aksi" value="surat"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <select name="status" class="kolom w-28 py-1">
            <?php foreach (array('Diproses', 'Selesai', 'Ditolak') as $s): ?>
              <option <?= $s === $r['status'] ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <input type="file" name="file" accept=".pdf,.doc,.docx" class="w-40 text-xs">
          <button class="btn btn-utama btn-kecil">Simpan</button>
        </form>
      </td>
    </tr>
<?php endforeach;
  tabel_akhir();
endif; ?>
<?php kartu_akhir(); ?>

<?php kartu_awal('Verifikasi dokumen'); ?>
<?php if (count($dokumen) == 0): kosong('Belum ada dokumen yang diunggah.');
else: tabel_awal(array('Karyawan', 'Dokumen', 'Status', '')); ?>
  <?php foreach ($dokumen as $r): ?>
    <tr>
      <td data-label="Karyawan"><?= e($r['nama']) ?></td>
      <td data-label="Dokumen"><a class="text-teal-700 underline" href="unduh.php?jenis=dokumen&id=<?= e($r['id']) ?>" target="_blank"><?= e($r['jenis']) ?></a></td>
      <td data-label="Status"><?= lencana($r['status']) ?></td>
      <td data-label="">
        <form method="POST" class="flex gap-1">
          <input type="hidden" name="aksi" value="dokumen"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button name="status" value="Terverifikasi" class="btn btn-utama btn-kecil">Verifikasi</button>
          <button name="status" value="Perlu Diperbarui" class="btn btn-bahaya btn-kecil">Perlu diperbarui</button>
        </form>
      </td>
    </tr>
<?php endforeach;
  tabel_akhir();
endif; ?>
<?php kartu_akhir(); ?>
<?php bottom(); ?>