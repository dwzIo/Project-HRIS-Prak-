<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isi('aksi') === 'hapus') {
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
    $departemen_id = isi('departemen_id') ?: null;
    $jabatan = trim(isi('jabatan'));
    $gaji_pokok = max(0, (int)isi('gaji_pokok'));
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
    }

    if ($error === '') {
      if ($nip === '' || $nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'NIP, nama, dan email yang valid wajib diisi.';
      } elseif (!ctype_digit($nip) && !($target && $target['nip'] === $nip)) {
        $error = 'NIP hanya boleh berisi angka.';
      } elseif ($no_hp !== '' && !ctype_digit($no_hp)) {
        $error = 'Nomor HP hanya boleh berisi angka.';
      } elseif (angka("SELECT COUNT(*) FROM users WHERE nip=? AND id<>?", array($nip, $uid)) > 0) {
        $error = 'NIP sudah dipakai akun lain.';
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
        "UPDATE users SET nip=?, nama=?, email=?, no_hp=?, alamat=?, departemen_id=?, jabatan=?, gaji_pokok=?, role=? WHERE id=?",
        array($nip, $nama, $email, $no_hp, $alamat, $departemen_id, $jabatan, $gaji_pokok, $role, $uid)
      );
      if ($password !== '') {
        jalankan("UPDATE users SET password=? WHERE id=?", array(password_hash($password, PASSWORD_DEFAULT), $uid));
      }
      pesan('Data akun diperbarui.');
    } else {
      jalankan(
        "INSERT INTO users (id, nip, nama, email, no_hp, alamat, departemen_id, jabatan, gaji_pokok, role, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        array(buat_id(), $nip, $nama, $email, $no_hp, $alamat, $departemen_id, $jabatan, $gaji_pokok, $role, password_hash($password, PASSWORD_DEFAULT))
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
  'departemen_id' => '',
  'jabatan' => '',
  'gaji_pokok' => 0,
  'role' => 'karyawan'
);
if ($edit) {
  $nilai = $edit;
}

// Departemen untuk dropdown
$depts = daftar("SELECT * FROM departments ORDER BY nama");
$opsi_dept = array('' => 'Tanpa departemen');
foreach ($depts as $d) {
  $opsi_dept[$d['id']] = $d['nama'];
}

$semua = daftar("
  SELECT u.*, d.nama AS nama_departemen 
  FROM users u
  LEFT JOIN departments d ON d.id = u.departemen_id
  ORDER BY CASE u.role WHEN 'admin' THEN 0 ELSE 1 END, u.nama
");

$atribut_sandi = '';
if (!$edit) {
  $atribut_sandi = 'required';
}

top('Data Karyawan');
?>
<div class="grid gap-6 lg:grid-cols-3">

  <!-- Formulir tambah / ubah akun -->
  <div>
    <?php kartu_awal($edit ? 'Ubah Data Akun' : 'Tambah Karyawan / Admin'); ?>
    <form method="POST" data-validasi novalidate><?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e($nilai['id']) ?>">

      <?php isian('NIP (angka saja)', 'nip', 'text', $nilai['nip'], 'required data-angka inputmode="numeric" autocomplete="off"'); ?>
      <?php isian('Nama Lengkap', 'nama', 'text', $nilai['nama'], 'required'); ?>
      <?php isian('Email Kantor / Pribadi', 'email', 'email', $nilai['email'], 'required'); ?>
      <?php isian('Nomor HP', 'no_hp', 'number', $nilai['no_hp'], 'data-nomor min="0" inputmode="numeric"'); ?>

      <?php pilih('Departemen', 'departemen_id', $opsi_dept, $nilai['departemen_id']); ?>
      <?php isian('Jabatan / Posisi', 'jabatan', 'text', $nilai['jabatan'], 'placeholder="Contoh: Staff HR / Web Developer"'); ?>
      <?php isian('Gaji Pokok Standar (Rp)', 'gaji_pokok', 'number', $nilai['gaji_pokok'], 'min="0" placeholder="Contoh: 5000000"'); ?>

      <div class="mb-3">
        <label class="label">Alamat</label>
        <textarea name="alamat" rows="2" class="kolom text-sm"><?= e($nilai['alamat']) ?></textarea>
      </div>

      <?php pilih('Peran Akun', 'role', array('karyawan' => 'Karyawan (Portal Karyawan)', 'admin' => 'Admin (Portal Admin)'), $nilai['role']); ?>
      <?php isian_sandi($edit ? 'Password baru (kosongkan jika tidak diganti)' : 'Password', 'password', $atribut_sandi); ?>
      <?php isian_sandi('Konfirmasi password', 'konfirmasi', $atribut_sandi); ?>

      <div class="flex gap-2">
        <button class="btn btn-utama flex-1 sm:flex-none">Simpan</button>
        <?php if ($edit): ?>
          <a href="admin_karyawan.php" class="btn btn-garis flex-1 sm:flex-none">Batal</a>
        <?php endif; ?>
      </div>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <!-- Daftar akun -->
  <div class="lg:col-span-2">
    <?php kartu_awal('Daftar Akun Pengguna'); ?>
    <input id="cari" class="kolom mb-3 text-xs" placeholder="Cari nama, NIP, atau departemen...">

    <?php tabel_awal(array('Karyawan', 'Departemen & Jabatan', 'Peran', 'Aksi'), 'tabel-cari'); ?>
    <?php foreach ($semua as $r):
      $boleh_hapus = (alasan_tidak_boleh_hapus($r) === '');
    ?>
      <tr>
        <td data-label="Karyawan">
          <div class="flex items-center gap-3">
            <?= avatar($r, 'h-9 w-9', 'text-sm') ?>
            <div class="text-left">
              <span class="font-medium text-stone-900"><?= e($r['nama']) ?></span>
              <span class="keterangan block">NIP: <?= e($r['nip']) ?></span>
              <span class="keterangan block"><?= e($r['email']) ?></span>
            </div>
          </div>
        </td>
        <td data-label="Departemen & Jabatan" class="text-xs">
          <span class="font-medium text-stone-800"><?= e($r['nama_departemen'] ?: 'Belum diatur') ?></span>
          <br><span class="text-stone-500"><?= e($r['jabatan'] ?: 'Staf') ?></span>
        </td>
        <td data-label="Peran">
          <span class="lencana <?= $r['role'] === 'admin' ? 'lencana-info' : 'lencana-netral' ?>"><?= e(nama_peran($r['role'])) ?></span>
        </td>
        <td data-label="Aksi" class="whitespace-nowrap">
          <div class="flex gap-1.5">
            <a href="?edit=<?= e($r['id']) ?>" class="btn btn-garis btn-kecil">Ubah</a>
            <?php if ($boleh_hapus): ?>
              <form method="POST" data-konfirmasi="Hapus akun ini beserta seluruh datanya?"><?= csrf_field() ?>
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
