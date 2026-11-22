<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_login();
$id = $_SESSION['id'];
$boleh = array('alamat' => 'Alamat', 'no_hp' => 'Nomor HP', 'email' => 'Email');
$u = satu("SELECT * FROM users WHERE id=?", array($id));

// 1. Cek Cooldown 90 hari setelah status Disetujui
$batas_90_hari = date('Y-m-d H:i:s', strtotime('-90 days'));
$cek_setuju = satu("SELECT created_at FROM change_requests WHERE user_id=? AND status='Disetujui' AND created_at > ? ORDER BY created_at DESC LIMIT 1", array($id, $batas_90_hari));

$sisa_hari = 0;
if ($cek_setuju) {
  $selisih_detik = (strtotime($cek_setuju['created_at']) + (90 * 86400)) - time();
  $sisa_hari = ceil($selisih_detik / 86400);
}

// 2. Cek apakah masih ada pengajuan yang belum diproses HR
$cek_menunggu = satu("SELECT id FROM change_requests WHERE user_id=? AND status='Menunggu' LIMIT 1", array($id));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');
  if ($aksi === 'foto') {
    $hasil = upload('foto', 'foto', array('jpg', 'jpeg', 'png'), 2);
    if ($hasil[1]) {
      pesan($hasil[1], 'gagal');
    } else {
      if ($u['foto']) {
        @unlink(FOLDER_UPLOAD . '/foto/' . $u['foto']);
      }
      jalankan("UPDATE users SET foto=? WHERE id=?", array($hasil[0], $id));
      pesan('Foto profil diperbarui.');
    }
  } elseif ($aksi === 'ubah') {
    if ($sisa_hari > 0) {
      pesan("Data baru disetujui. Anda baru bisa mengajukan lagi dalam $sisa_hari hari.", 'gagal');
    } elseif ($cek_menunggu) {
      pesan('Masih ada pengajuan Anda yang menunggu persetujuan HR.', 'gagal');
    } else {
      // Ambil file bukti (jika ada)
      $bukti = null;
      if (ada_file('bukti')) {
        $up = upload('bukti', 'bukti', array('jpg', 'jpeg', 'png', 'pdf'), 2);
        if ($up[1]) {
          pesan($up[1], 'gagal');
          pindah('profil.php');
        }
        $bukti = $up[0];
      }

      // Waktu dibuat sama agar terhitung satu kali pengajuan
      $waktu = date('Y-m-d H:i:s');
      $jumlah = 0;

      foreach ($boleh as $kolom => $label) {
        $nilai_baru = trim(isi($kolom));
        // Simpan hanya jika diisi dan nilainya berbeda dari data lama
        if ($nilai_baru !== '' && $nilai_baru !== (string)$u[$kolom]) {
          jalankan(
            "INSERT INTO change_requests (id,user_id,field,nilai_lama,nilai_baru,bukti,created_at) VALUES (?,?,?,?,?,?,?)",
            array(buat_id(), $id, $kolom, $u[$kolom], $nilai_baru, $bukti, $waktu)
          );
          $jumlah++;
        }
      }

      if ($jumlah > 0) {
        pesan("$jumlah data berhasil diajukan dan menunggu persetujuan HR.");
      } else {
        pesan('Tidak ada data baru yang diisi untuk diubah.', 'gagal');
      }
    }
  } elseif ($aksi === 'password') {
    if (!password_verify(isi('lama'), $u['password'])) {
      pesan('Password lama salah.', 'gagal');
    } elseif (strlen(isi('baru')) < 6 || isi('baru') !== isi('konfirmasi')) {
      pesan('Password baru minimal 6 karakter dan harus sama dengan konfirmasi.', 'gagal');
    } else {
      jalankan("UPDATE users SET password=? WHERE id=?", array(password_hash(isi('baru'), PASSWORD_DEFAULT), $id));
      pesan('Password berhasil diganti.');
    }
  }
  pindah('profil.php');
}

$riwayat = daftar("SELECT * FROM change_requests WHERE user_id=? ORDER BY created_at DESC, rowid DESC", array($id));

top('Profil saya');
?>
<div class="grid gap-5 lg:grid-cols-5">

  <!-- Kiri: identitas, foto, dan password -->
  <div class="lg:col-span-2">
    <section class="kartu mb-5">
      <div class="mb-4 flex items-center gap-4">
        <?= avatar($u, 'h-20 w-20', 'text-2xl') ?>
        <div>
          <p class="font-semibold"><?= e($u['nama']) ?></p>
          <p class="text-sm text-stone-500">NIP <?= e($u['nip']) ?></p>
        </div>
      </div>

      <dl class="mb-4 space-y-2 text-sm">
        <?php foreach ($boleh as $k => $label): ?>
          <div class="flex justify-between gap-4">
            <dt class="text-stone-500"><?= $label ?></dt>
            <dd class="text-right"><?= e($u[$k]) !== '' ? e($u[$k]) : '-' ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="foto">
        <div class="mb-3">
          <label class="label">Ganti foto</label>
          <input type="file" id="input-foto" name="foto" accept="image/*" class="kolom" required>
        </div>
        <img id="pratinjau" alt="Pratinjau" class="mb-3 hidden h-20 w-20 rounded-full object-cover">
        <button class="btn btn-garis btn-kecil">Simpan foto</button>
      </form>
    </section>

    <?php kartu_awal('Ganti password'); ?>
    <form method="POST">
      <input type="hidden" name="aksi" value="password">
      <?php isian('Password lama', 'lama', 'password', '', 'required'); ?>
      <?php isian('Password baru', 'baru', 'password', '', 'required'); ?>
      <?php isian('Ulangi password baru', 'konfirmasi', 'password', '', 'required'); ?>
      <button class="btn btn-garis btn-kecil">Simpan password</button>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <!-- Kanan: pengajuan perubahan data dan riwayatnya -->
  <div class="lg:col-span-3">
    <?php kartu_awal('Ajukan perubahan data'); ?>

    <?php if ($sisa_hari > 0): ?>
      <p class="notif notif-peringatan">Perubahan data Anda baru saja disetujui. Anda baru dapat mengajukan perubahan lagi dalam <b><?= $sisa_hari ?> hari</b>.</p>

    <?php elseif ($cek_menunggu): ?>
      <p class="notif notif-info">Anda masih memiliki pengajuan yang berstatus <b>Menunggu</b>. Harap tunggu konfirmasi HR.</p>

    <?php else: ?>
      <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Apakah Anda yakin ingin mengajukan perubahan data ini? Setelah disetujui, data tidak dapat diubah selama 90 hari.');">
        <input type="hidden" name="aksi" value="ubah">
        <p class="keterangan mb-3">Isi data yang ingin diubah saja (bisa lebih dari satu sekaligus), kosongkan jika tidak ingin diubah.</p>

        <?php foreach ($boleh as $kolom => $label): ?>
          <div class="mb-3">
            <label class="label"><?= $label ?> <span class="text-xs font-normal text-stone-400">(Saat ini: <?= e($u[$kolom]) ?: '-' ?>)</span></label>
            <input type="text" name="<?= $kolom ?>" placeholder="Ketik data baru jika ingin mengubah..." class="kolom">
          </div>
        <?php endforeach; ?>

        <div class="mb-4">
          <label class="label">Bukti pendukung (opsional)</label>
          <input type="file" name="bukti" accept=".jpg,.jpeg,.png,.pdf" class="kolom">
        </div>

        <button class="btn btn-utama">Kirim pengajuan</button>
      </form>
    <?php endif; ?>

    <?php kartu_akhir(); ?>

    <?php kartu_awal('Riwayat pengajuan'); ?>
    <?php if (count($riwayat) == 0): ?>
      <?php kosong('Belum ada pengajuan.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Tanggal', 'Data', 'Lama', 'Baru', 'Status', 'Catatan')); ?>
      <?php foreach ($riwayat as $r): ?>
        <tr>
          <td data-label="Tanggal"><?= tgl($r['created_at']) ?></td>
          <td data-label="Data"><?= isset($boleh[$r['field']]) ? $boleh[$r['field']] : e($r['field']) ?></td>
          <td data-label="Lama"><?= e($r['nilai_lama']) ?: '-' ?></td>
          <td data-label="Baru"><?= e($r['nilai_baru']) ?></td>
          <td data-label="Status"><?= lencana($r['status']) ?></td>
          <td data-label="Catatan"><?= e($r['catatan']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php tabel_akhir(); ?>
    <?php endif; ?>
    <?php kartu_akhir(); ?>
  </div>

</div>

<!-- Jendela potong foto profil (dikendalikan assets/app.js) -->
<div id="modal-crop" class="fixed inset-0 z-50 hidden items-center justify-center bg-stone-900/60 p-4">
  <div class="w-full max-w-sm rounded-lg border border-stone-200 bg-white p-5">
    <h3 class="mb-3 text-base font-semibold">Atur foto profil</h3>
    <canvas id="kanvas-crop" width="288" height="288" class="mx-auto mb-3 block max-w-full rounded-md bg-stone-100"></canvas>
    <input type="range" id="zoom-crop" min="1" max="3" step="0.01" value="1" class="mb-4 w-full">
    <div class="flex justify-end gap-2">
      <button type="button" id="crop-batal" class="btn btn-garis">Batal</button>
      <button type="button" id="crop-pakai" class="btn btn-utama">Pakai foto ini</button>
    </div>
  </div>
</div>

<?php bottom(); ?>
