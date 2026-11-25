<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isi('aksi') === 'hapus') {
    // Aturan hapus: akun yang sedang dipakai tidak boleh dihapus
    $target = satu("SELECT * FROM users WHERE id=?", array(isi('id')));
    if (!$target) {
      pesan('Akun tidak ditemukan.', 'gagal');
    } else {
      $alasan = alasan_tidak_boleh_hapus($target);
      if ($alasan !== '') {
        pesan($alasan, 'gagal');
      } else {
        jalankan("DELETE FROM users WHERE id=?", array($target['id']));
        pesan('Akun dihapus.');
      }
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
    if (isi('role') === 'admin') {
      $role = 'admin';
    }

    $target = null;
    if ($uid !== '') {
      $target = satu("SELECT * FROM users WHERE id=?", array($uid));
    }
    $error = '';
    if ($uid !== '' && !$target) {
      $error = 'Akun tidak ditemukan.';
    }
    if ($target && $target['id'] === $_SESSION['id']) {
      $role = $target['role'];
    } // role akun sendiri tidak bisa diubah

    if ($error === '') {
      if ($nip === '' || $nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'NIP, nama, dan email yang valid wajib diisi.';
      } elseif (!ctype_digit($nip) && !($target && $target['nip'] === $nip)) {
        $error = 'NIP hanya boleh berisi angka.';
      } elseif ($no_hp !== '' && !ctype_digit($no_hp)) {
        $error = 'Nomor HP hanya boleh berisi angka.';
      } elseif (angka("SELECT COUNT(*) FROM users WHERE nip=? AND id<>?", array($nip, $uid)) > 0) {
        $error = 'NIP sudah dipakai akun lain.';
      } elseif (angka("SELECT COUNT(*) FROM users WHERE lower(email)=lower(?) AND id<>?", array($email, $uid)) > 0) {
        $error = 'Email sudah dipakai akun lain.';
      } elseif ($uid === '' && $password === '') {
        $error = 'Password wajib diisi untuk akun baru.';
      } elseif ($uid === '' && $konfirmasi === '') {
        $error = 'Konfirmasi password wajib diisi.';
      } elseif ($password !== '' && strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
      } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama dengan password.';
      }
    }

    if ($error !== '') {
      pesan($error, 'gagal');
    } elseif ($uid !== '') {
      jalankan(
        "UPDATE users SET nip=?, nama=?, email=?, no_hp=?, alamat=?, role=? WHERE id=?",
        array($nip, $nama, $email, $no_hp, $alamat, $role, $uid)
      );
      if ($password !== '') {
        jalankan("UPDATE users SET password=? WHERE id=?", array(password_hash($password, PASSWORD_DEFAULT), $uid));
      }
      pesan('Data akun diperbarui.');
    } else {
      jalankan(
        "INSERT INTO users (id, nip, nama, email, no_hp, alamat, role, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        array(buat_id(), $nip, $nama, $email, $no_hp, $alamat, $role, password_hash($password, PASSWORD_DEFAULT))
      );
      pesan('Akun ditambahkan.');
    }
  }
  pindah('admin_karyawan.php');
}

$edit = null;
if (isset($_GET['edit'])) {
  $edit = satu("SELECT * FROM users WHERE id=?", array($_GET['edit']));
}
$nilai = array(
  'id' => '',
  'nip' => '',
  'nama' => '',
  'email' => '',
  'no_hp' => '',
  'alamat' => '',
  'role' => 'karyawan'
);
if ($edit) {
  $nilai = $edit;
}

$semua = daftar("
  SELECT u.* 
  FROM users u
  ORDER BY CASE u.role WHEN 'admin' THEN 0 ELSE 1 END, u.nama
");

$atribut_sandi = '';
if (!$edit) {
  $atribut_sandi = 'required';
}

top('Data Karyawan');
?>
<div class="grid gap-6 lg:grid-cols-3">
  <div>
    <?php kartu_awal($edit ? 'Ubah Data Akun' : 'Tambah Karyawan / Admin'); ?>
    <form method="POST" data-validasi novalidate>
      <input type="hidden" name="id" value="<?= e($nilai['id']) ?>">
      <?php isian('NIP (angka saja)', 'nip', 'text', $nilai['nip'], 'required data-angka inputmode="numeric" autocomplete="off"'); ?>
      <?php isian('Nama Lengkap', 'nama', 'text', $nilai['nama'], 'required'); ?>
      <?php isian('Email Kantor / Pribadi', 'email', 'email', $nilai['email'], 'required'); ?>
      <?php isian('Nomor HP', 'no_hp', 'number', $nilai['no_hp'], 'data-nomor min="0" inputmode="numeric"'); ?>

      <div class="mb-3">
        <label class="label">Alamat</label>
        <textarea name="alamat" rows="2" class="kolom text-sm"><?= e($nilai['alamat']) ?></textarea>
      </div>

      <?php pilih('Peran Akun', 'role', array('karyawan' => 'Karyawan (Portal Karyawan)', 'admin' => 'Admin (Portal Admin)'), $nilai['role']); ?>
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
    <?php kartu_awal('Daftar Akun Pengguna'); ?>
    <input id="cari" class="kolom mb-3 text-xs" placeholder="Cari nama atau NIP...">
    <?php tabel_awal(array('Karyawan', 'Peran', 'Aksi'), 'tabel-cari'); ?>
    <?php foreach ($semua as $r):
      $boleh_hapus = (alasan_tidak_boleh_hapus($r) === '');
    ?>
      <tr>
        <td data-label="Karyawan">
          <div class="flex items-center gap-3">
            <?= avatar($r, 'h-9 w-9', 'text-sm') ?>
            <div>
              <span class="font-semibold text-stone-900"><?= e($r['nama']) ?></span>
              <br><span class="text-xs text-stone-500">NIP: <?= e($r['nip']) ?> • <?= e($r['email']) ?></span>
            </div>
          </div>
        </td>
        <td data-label="Peran">
          <span class="text-xs font-semibold text-black">
            <?= e(nama_peran($r['role'])) ?>
          </span>
        </td>
        <td data-label="Aksi" class="whitespace-nowrap">
          <div class="flex gap-1">
            <a href="?edit=<?= e($r['id']) ?>" class="btn btn-zinc btn-kecil">Ubah</a>
            <?php if ($boleh_hapus): ?>
              <form method="POST" data-konfirmasi="Hapus akun ini beserta seluruh datanya?">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= e($r['id']) ?>">
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