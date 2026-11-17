<?php
include __DIR__ . "/../src/auth.php"; include __DIR__ . "/../src/layout.php"; wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isi('aksi') === 'hapus') {
    // Aturan hapus: akun yang sedang dipakai tidak boleh dihapus
    $target = satu("SELECT * FROM users WHERE id=?", array(isi('id')));
    if (!$target) { pesan('Akun tidak ditemukan.', 'gagal'); }
    else {
      $alasan = alasan_tidak_boleh_hapus($target);
      if ($alasan !== '') { pesan($alasan, 'gagal'); }
      else { jalankan("DELETE FROM users WHERE id=?", array($target['id'])); pesan('Akun dihapus.'); }
    }
  } else {
    $uid = isi('id'); // kosong = akun baru
    $nip = trim(isi('nip'));
    $nama = trim(isi('nama'));
    $email = trim(isi('email'));
    $no_hp = trim(isi('no_hp'));
    $alamat = trim(isi('alamat'));
    $password = isi('password');
    $konfirmasi = isi('konfirmasi');
    $role = 'karyawan';
    if (isi('role') === 'admin') { $role = 'admin'; }

    $target = null;
    if ($uid !== '') { $target = satu("SELECT * FROM users WHERE id=?", array($uid)); }
    $error = '';
    if ($uid !== '' && !$target) { $error = 'Akun tidak ditemukan.'; }
    if ($target && $target['id'] === $_SESSION['id']) { $role = $target['role']; } // role akun sendiri tidak bisa diubah

    if ($error === '') {
      if ($nip === '' || $nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'NIP, nama, dan email yang valid wajib diisi.'; }
      // NIP hanya angka, tetapi tetap disimpan sebagai teks (string) agar angka 0 di depan tidak hilang.
      // Pengecualian: NIP lama akun yang sedang diubah (mis. akun awal "admin") boleh dipertahankan apa adanya.
      elseif (!ctype_digit($nip) && !($target && $target['nip'] === $nip)) { $error = 'NIP hanya boleh berisi angka.'; }
      elseif ($no_hp !== '' && !ctype_digit($no_hp)) { $error = 'Nomor HP hanya boleh berisi angka.'; }
      elseif (angka("SELECT COUNT(*) FROM users WHERE nip=? AND id<>?", array($nip, $uid)) > 0) { $error = 'NIP sudah dipakai akun lain.'; }
      elseif ($uid === '' && $password === '') { $error = 'Password wajib diisi untuk akun baru.'; }
      elseif ($uid === '' && $konfirmasi === '') { $error = 'Konfirmasi password wajib diisi.'; }
      elseif ($password !== '' && strlen($password) < 6) { $error = 'Password minimal 6 karakter.'; }
      elseif ($password !== $konfirmasi) { $error = 'Konfirmasi password tidak sama dengan password.'; }
    }

    if ($error !== '') { pesan($error, 'gagal'); }
    elseif ($uid !== '') {
      jalankan("UPDATE users SET nip=?, nama=?, email=?, no_hp=?, alamat=?, role=? WHERE id=?", array($nip, $nama, $email, $no_hp, $alamat, $role, $uid));
      if ($password !== '') { jalankan("UPDATE users SET password=? WHERE id=?", array(password_hash($password, PASSWORD_DEFAULT), $uid)); }
      pesan('Data akun diperbarui.');
    } else {
      jalankan("INSERT INTO users (id,nip,nama,email,no_hp,alamat,role,password) VALUES (?,?,?,?,?,?,?,?)",
        array(buat_id(), $nip, $nama, $email, $no_hp, $alamat, $role, password_hash($password, PASSWORD_DEFAULT)));
      pesan('Akun ditambahkan.');
    }
  }
  pindah('admin_karyawan.php');
}

$edit = null;
if (isset($_GET['edit'])) { $edit = satu("SELECT * FROM users WHERE id=?", array($_GET['edit'])); }
$nilai = array('id' => '', 'nip' => '', 'nama' => '', 'email' => '', 'no_hp' => '', 'alamat' => '', 'role' => 'karyawan');
if ($edit) { $nilai = $edit; }
$semua = daftar("SELECT * FROM users ORDER BY CASE role WHEN 'admin' THEN 0 ELSE 1 END, nama");

// Saat membuat akun baru, password dan konfirmasi wajib diisi. Saat mengubah, boleh dikosongkan.
$atribut_sandi = '';
if (!$edit) { $atribut_sandi = 'required'; }
top('Data karyawan');
?>
<div class="grid gap-5 lg:grid-cols-3">
  <div>
    <?php kartu_awal($edit ? 'Ubah akun' : 'Tambah karyawan / admin'); ?>
      <form method="POST" data-validasi novalidate>
        <input type="hidden" name="id" value="<?= e($nilai['id']) ?>">
        <?php isian('NIP (angka saja)', 'nip', 'text', $nilai['nip'], 'required data-angka inputmode="numeric" autocomplete="off"'); ?>
        <?php isian('Nama', 'nama', 'text', $nilai['nama'], 'required'); ?>
        <?php isian('Email', 'email', 'email', $nilai['email'], 'required'); ?>
        <?php isian('Nomor HP (angka saja)', 'no_hp', 'number', $nilai['no_hp'], 'data-nomor min="0" inputmode="numeric"'); ?>
        <div class="mb-3"><label class="label">Alamat</label><textarea name="alamat" rows="2" class="kolom"><?= e($nilai['alamat']) ?></textarea></div>
        <?php pilih('Role', 'role', array('karyawan' => 'Karyawan', 'admin' => 'Admin'), $nilai['role']); ?>
        <?php isian_sandi($edit ? 'Password baru (kosongkan jika tidak diganti)' : 'Password', 'password', $atribut_sandi); ?>
        <?php isian_sandi('Konfirmasi password', 'konfirmasi', $atribut_sandi); ?>
        <div class="flex gap-2">
          <button class="btn btn-utama flex-1 sm:flex-none">Simpan</button>
          <?php if ($edit): ?><a href="admin_karyawan.php" class="btn btn-garis flex-1 sm:flex-none">Batal</a><?php endif; ?>
        </div>
      </form>
    <?php kartu_akhir(); ?>
  </div>
  <div class="lg:col-span-2">
    <?php kartu_awal('Daftar akun'); ?>
      <input id="cari" class="kolom mb-3" placeholder="Cari nama atau NIP...">
      <?php tabel_awal(array('NIP', 'Nama', 'Email', 'Role', ''), 'tabel-cari'); ?>
        <?php foreach ($semua as $r):
          $boleh_hapus = (alasan_tidak_boleh_hapus($r) === ''); // tombol Hapus hanya muncul jika diizinkan
        ?>
          <tr>
            <td data-label="NIP"><?= e($r['nip']) ?></td>
            <td data-label="Nama"><div class="flex items-center gap-3"><?= avatar($r, 'h-9 w-9', 'text-sm') ?><span><?= e($r['nama']) ?></span></div></td>
            <td data-label="Email" class="break-all"><?= e($r['email']) ?></td>
            <td data-label="Role"><?= e(nama_peran($r['role'])) ?></td>
            <td data-label="" class="whitespace-nowrap">
              <div class="flex gap-1">
                <a href="?edit=<?= e($r['id']) ?>" class="btn btn-garis btn-kecil">Ubah</a>
                <?php if ($boleh_hapus): ?>
                  <form method="POST" data-konfirmasi="Hapus akun ini beserta seluruh datanya?">
                    <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
                    <button class="btn btn-bahaya btn-kecil">Hapus</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php tabel_akhir(); ?>
    <?php kartu_akhir(); ?>
  </div>
</div>
<?php bottom(); ?>
