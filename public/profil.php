<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_login();
$id = $_SESSION['id'];
$boleh = array('alamat' => 'Alamat', 'no_hp' => 'Nomor HP', 'email' => 'Email', 'kontak_darurat' => 'Kontak darurat');
$u = satu("SELECT * FROM users WHERE id=?", array($id));

// Cooldown 90 hari: cek apakah ada pengajuan yang disetujui dalam 90 hari terakhir
$batas_cooldown = date('Y-m-d H:i:s', strtotime('-90 days'));
$cooldown_aktif = satu(
  "SELECT created_at FROM change_requests WHERE user_id=? AND status='Disetujui' AND created_at > ? ORDER BY created_at DESC LIMIT 1",
  array($id, $batas_cooldown)
);
$sisa_hari = 0;
if ($cooldown_aktif) {
  $tanggal_ajuan = strtotime($cooldown_aktif['created_at']);
  $tanggal_buka = $tanggal_ajuan + (90 * 86400);
  $sisa_hari = (int) ceil(($tanggal_buka - time()) / 86400);
  if ($sisa_hari <= 0) {
    $sisa_hari = 0;
    $cooldown_aktif = null;
  }
}

// Cek apakah masih ada pengajuan yang berstatus Menunggu
$masih_menunggu = satu(
  "SELECT id FROM change_requests WHERE user_id=? AND status='Menunggu' LIMIT 1",
  array($id)
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');
  if ($aksi === 'foto') {
    $hasil = upload('foto', 'foto', array('jpg', 'jpeg', 'png'), 2);
    if ($hasil[1]) {
      pesan($hasil[1], 'gagal');
    } else {
      if ($u['foto']) {
        @unlink(FOLDER_UPLOAD . '/foto/' . $u['foto']);
      } // hapus foto lama
      jalankan("UPDATE users SET foto=? WHERE id=?", array($hasil[0], $id));
      pesan('Foto profil diperbarui.');
    }
  } elseif ($aksi === 'ubah') {
    if ($cooldown_aktif) {
      pesan('Anda baru bisa mengajukan perubahan data lagi dalam ' . $sisa_hari . ' hari.', 'gagal');
    } elseif ($masih_menunggu) {
      pesan('Anda masih memiliki pengajuan yang menunggu persetujuan. Tunggu hingga diproses.', 'gagal');
    } else {
      // Ambil seluruh input field yang diubah & tidak sama dengan data saat ini
      $items = array();
      foreach ($boleh as $kolom => $label) {
        $nilai_baru = trim(isi('nilai_' . $kolom));
        if ($nilai_baru !== '' && $nilai_baru !== (string)$u[$kolom]) {
          $items[] = array('field' => $kolom, 'nilai_baru' => $nilai_baru);
        }
      }

      if (count($items) === 0) {
        pesan('Isi minimal satu field yang ingin diubah (dan berbeda dari data saat ini).', 'gagal');
      } else {
        // Upload bukti pendukung (opsional, berlaku untuk seluruh pengajuan dalam 1 batch)
        $bukti = null;
        $error = '';
        if (ada_file('bukti')) {
          $hasil = upload('bukti', 'bukti', array('jpg', 'jpeg', 'png', 'pdf'), 2);
          if ($hasil[1]) {
            $error = $hasil[1];
          } else {
            $bukti = $hasil[0];
          }
        }

        if ($error !== '') {
          pesan($error, 'gagal');
        } else {
          // Waktu submit identik untuk semua baris agar terkelompok dalam 1 pengajuan
          $waktu_ajuan = date('Y-m-d H:i:s');

          foreach ($items as $item) {
            jalankan(
              "INSERT INTO change_requests (id, user_id, field, nilai_lama, nilai_baru, bukti, created_at) VALUES (?,?,?,?,?,?,?)",
              array(buat_id(), $id, $item['field'], $u[$item['field']], $item['nilai_baru'], $bukti, $waktu_ajuan)
            );
          }

          $jumlah = count($items);
          pesan('Pengajuan dikirim (' . $jumlah . ' perubahan) dan menunggu persetujuan HR.');
        }
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

// Riwayat: dikelompokkan berdasarkan created_at batch
$riwayat_grup = daftar(
  "SELECT created_at, MAX(status) as status, MAX(catatan) as catatan FROM change_requests WHERE user_id=? GROUP BY created_at ORDER BY created_at DESC",
  array($id)
);

top('Profil saya');
?>
<div class="grid gap-5 lg:grid-cols-5">
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
      <form method="POST" enctype="multipart/form-data" data-validasi novalidate>
        <input type="hidden" name="aksi" value="foto">
        <div class="mb-3"><label class="label">Ganti foto (nanti bisa dipotong persegi)</label>
          <input type="file" id="input-foto" name="foto" accept="image/*" class="kolom" required>
        </div>
        <img id="pratinjau" alt="Pratinjau" class="mb-3 hidden h-20 w-20 rounded-full object-cover">
        <button class="btn btn-garis btn-kecil">Simpan foto</button>
      </form>
    </section>
    <?php kartu_awal('Ganti password'); ?>
    <form method="POST" data-validasi novalidate>
      <input type="hidden" name="aksi" value="password">
      <?php isian('Password lama', 'lama', 'password', '', 'required'); ?>
      <?php isian('Password baru', 'baru', 'password', '', 'required'); ?>
      <?php isian('Ulangi password baru', 'konfirmasi', 'password', '', 'required'); ?>
      <button class="btn btn-garis btn-kecil">Simpan password</button>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <div class="lg:col-span-3">
    <?php kartu_awal('Ajukan perubahan data'); ?>
    <?php if ($cooldown_aktif): ?>
      <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>⏳ Cooldown aktif.</strong> Anda baru bisa mengajukan perubahan data lagi dalam
        <strong><?= $sisa_hari ?> hari</strong>
        (sejak pengajuan tanggal <?= tgl($cooldown_aktif['created_at']) ?>).
      </div>
    <?php elseif ($masih_menunggu): ?>
      <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
        <strong>⏳ Menunggu persetujuan.</strong> Anda masih memiliki pengajuan yang belum diproses HR. Silakan tunggu hasilnya terlebih dahulu.
      </div>
    <?php else: ?>
      <p class="mb-4 text-sm text-stone-500">Isi field yang ingin diubah (bisa beberapa sekaligus dalam satu kali pengajuan). Kosongkan field yang tidak ingin diubah.</p>
      <form method="POST" enctype="multipart/form-data" data-konfirmasi="Apakah Anda yakin ingin mengajukan perubahan data ini? Setelah disetujui, Anda tidak dapat mengubah data kembali selama 90 hari." novalidate>
        <input type="hidden" name="aksi" value="ubah">
        <?php foreach ($boleh as $kolom => $label): ?>
          <div class="mb-3">
            <label class="label"><?= $label ?> <span class="font-normal text-stone-400">(saat ini: <?= e($u[$kolom]) !== '' ? e($u[$kolom]) : '-' ?>)</span></label>
            <input type="text" name="nilai_<?= $kolom ?>" value="" placeholder="Biarkan kosong jika tidak diubah" class="kolom">
          </div>
        <?php endforeach; ?>
        <div class="mb-4"><label class="label">Bukti pendukung (opsional: JPG, PNG, atau PDF)</label>
          <input type="file" name="bukti" accept=".jpg,.jpeg,.png,.pdf" class="kolom">
        </div>
        <button class="btn btn-utama">Kirim pengajuan</button>
      </form>
    <?php endif; ?>
    <?php kartu_akhir(); ?>

    <?php kartu_awal('Riwayat pengajuan'); ?>
    <?php if (count($riwayat_grup) == 0): kosong('Belum ada pengajuan. Isi formulir di atas untuk memulai.');
    else: ?>
      <?php foreach ($riwayat_grup as $grup):
        $items = daftar("SELECT * FROM change_requests WHERE user_id=? AND created_at=? ORDER BY rowid ASC", array($id, $grup['created_at']));
      ?>
        <div class="mb-4 rounded-lg border border-stone-200 p-3">
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs text-stone-500"><?= tgl($grup['created_at']) ?> — <?= count($items) ?> perubahan</span>
            <?= lencana($grup['status']) ?>
          </div>
          <?php if ($grup['catatan']): ?>
            <p class="mb-2 text-xs text-stone-500">Catatan HR: <?= e($grup['catatan']) ?></p>
          <?php endif; ?>
          <table class="tabel">
            <thead>
              <tr>
                <th>Data</th>
                <th>Nilai lama</th>
                <th>Nilai baru</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $r): ?>
                <tr>
                  <td data-label="Data"><?= $boleh[$r['field']] ?></td>
                  <td data-label="Nilai lama"><?= e($r['nilai_lama']) ?></td>
                  <td data-label="Nilai baru"><?= e($r['nilai_baru']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php kartu_akhir(); ?>
  </div>
</div>
<!-- Jendela potong (crop) foto profil, dikendalikan assets/app.js -->
<div id="modal-crop" class="fixed inset-0 z-50 hidden items-center justify-center bg-stone-900/60 p-4">
  <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-lg">
    <h3 class="mb-1 text-base font-semibold">Atur foto profil</h3>
    <p class="mb-3 text-sm text-stone-500">Geser foto untuk mengatur posisi, lalu gunakan slider untuk memperbesar.</p>
    <canvas id="kanvas-crop" width="288" height="288" class="mx-auto mb-3 block max-w-full cursor-move touch-none rounded-lg bg-stone-100"></canvas>
    <input type="range" id="zoom-crop" min="1" max="3" step="0.01" value="1" aria-label="Perbesar foto" class="mb-4 w-full">
    <div class="flex justify-end gap-2">
      <button type="button" id="crop-batal" class="btn btn-garis">Batal</button>
      <button type="button" id="crop-pakai" class="btn btn-utama">Pakai foto ini</button>
    </div>
  </div>
</div>
<?php bottom(); ?>