<?php
include __DIR__ . "/../src/auth.php"; include __DIR__ . "/../src/layout.php"; wajib_login();
$id = $_SESSION['id'];
$boleh = array('alamat' => 'Alamat', 'no_hp' => 'Nomor HP', 'email' => 'Email', 'kontak_darurat' => 'Kontak darurat');
$u = satu("SELECT * FROM users WHERE id=?", array($id));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');
  if ($aksi === 'foto') {
    $hasil = upload('foto', 'foto', array('jpg', 'jpeg', 'png'), 2);
    if ($hasil[1]) { pesan($hasil[1], 'gagal'); }
    else {
      if ($u['foto']) { @unlink(FOLDER_UPLOAD . '/foto/' . $u['foto']); } // hapus foto lama
      jalankan("UPDATE users SET foto=? WHERE id=?", array($hasil[0], $id));
      pesan('Foto profil diperbarui.');
    }
  } elseif ($aksi === 'ubah') {
    $kolom = isi('field');
    $nilai_baru = trim(isi('nilai'));
    $bukti = null;
    $error = '';
    if (!isset($boleh[$kolom]) || $nilai_baru === '') { $error = 'Isi nilai baru dengan benar.'; }
    elseif (ada_file('bukti')) {
      $hasil = upload('bukti', 'bukti', array('jpg', 'jpeg', 'png', 'pdf'), 2);
      if ($hasil[1]) { $error = $hasil[1]; } else { $bukti = $hasil[0]; }
    }
    if ($error !== '') { pesan($error, 'gagal'); }
    else {
      jalankan("INSERT INTO change_requests (id,user_id,field,nilai_lama,nilai_baru,bukti) VALUES (?,?,?,?,?,?)", array(buat_id(), $id, $kolom, $u[$kolom], $nilai_baru, $bukti));
      pesan('Pengajuan dikirim dan menunggu persetujuan HR.');
    }
  } elseif ($aksi === 'password') {
    if (!password_verify(isi('lama'), $u['password'])) { pesan('Password lama salah.', 'gagal'); }
    elseif (strlen(isi('baru')) < 6 || isi('baru') !== isi('konfirmasi')) { pesan('Password baru minimal 6 karakter dan harus sama dengan konfirmasi.', 'gagal'); }
    else { jalankan("UPDATE users SET password=? WHERE id=?", array(password_hash(isi('baru'), PASSWORD_DEFAULT), $id)); pesan('Password berhasil diganti.'); }
  }
  pindah('profil.php');
}
$riwayat = daftar("SELECT * FROM change_requests WHERE user_id=? ORDER BY created_at DESC, rowid DESC", array($id));
top('Profil saya');
?>
<div class="grid gap-5 lg:grid-cols-5">
  <div class="lg:col-span-2">
    <section class="kartu mb-5">
      <div class="mb-4 flex items-center gap-4">
        <?= avatar($u, 'h-20 w-20', 'text-2xl') ?>
        <div><p class="font-semibold"><?= e($u['nama']) ?></p><p class="text-sm text-stone-500">NIP <?= e($u['nip']) ?></p></div>
      </div>
      <dl class="mb-4 space-y-2 text-sm">
        <?php foreach ($boleh as $k => $label): ?>
          <div class="flex justify-between gap-4"><dt class="text-stone-500"><?= $label ?></dt><dd class="text-right"><?= e($u[$k]) !== '' ? e($u[$k]) : '-' ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <form method="POST" enctype="multipart/form-data" data-validasi novalidate>
        <input type="hidden" name="aksi" value="foto">
        <div class="mb-3"><label class="label">Ganti foto (nanti bisa dipotong persegi)</label>
          <input type="file" id="input-foto" name="foto" accept="image/*" class="kolom" required></div>
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
      <form method="POST" enctype="multipart/form-data" data-validasi novalidate>
        <input type="hidden" name="aksi" value="ubah">
        <?php pilih('Data yang diubah', 'field', $boleh); ?>
        <?php isian('Nilai baru', 'nilai', 'text', '', 'required'); ?>
        <div class="mb-4"><label class="label">Bukti pendukung (opsional: JPG, PNG, atau PDF)</label>
          <input type="file" name="bukti" accept=".jpg,.jpeg,.png,.pdf" class="kolom"></div>
        <button class="btn btn-utama">Kirim pengajuan</button>
      </form>
    <?php kartu_akhir(); ?>

    <?php kartu_awal('Riwayat pengajuan'); ?>
      <?php if (count($riwayat) == 0): kosong('Belum ada pengajuan. Isi formulir di atas untuk memulai.'); else: tabel_awal(array('Data', 'Nilai baru', 'Status', 'Catatan HR')); ?>
        <?php foreach ($riwayat as $r): ?>
          <tr><td data-label="Data"><?= $boleh[$r['field']] ?></td><td data-label="Nilai baru"><?= e($r['nilai_baru']) ?></td><td data-label="Status"><?= lencana($r['status']) ?></td><td data-label="Catatan HR"><?= e($r['catatan']) ?></td></tr>
        <?php endforeach; tabel_akhir(); endif; ?>
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
