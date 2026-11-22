<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();

// Form Tambah / Edit / Hapus Departemen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');

  if ($aksi === 'hapus') {
    $id = isi('id');
    jalankan("DELETE FROM departments WHERE id=?", array($id));
    pesan('Departemen berhasil dihapus.');
  } else {
    $id = isi('id'); // kosong = tambah baru
    $nama = trim(isi('nama'));
    $keterangan = trim(isi('keterangan'));

    if ($nama === '') {
      pesan('Nama departemen wajib diisi.', 'gagal');
    } else {
      $ada_nama = angka("SELECT COUNT(*) FROM departments WHERE nama=? AND id<>?", array($nama, $id));
      if ($ada_nama > 0) {
        pesan('Nama departemen sudah digunakan.', 'gagal');
      } else {
        if ($id !== '') {
          jalankan("UPDATE departments SET nama=?, keterangan=? WHERE id=?", array($nama, $keterangan, $id));
          pesan('Data departemen berhasil diperbarui.');
        } else {
          jalankan("INSERT INTO departments (id, nama, keterangan) VALUES (?, ?, ?)", array(buat_id(), $nama, $keterangan));
          pesan('Departemen baru berhasil ditambahkan.');
        }
      }
    }
  }
  pindah('admin_departemen.php');
}

$edit = null;
if (isset($_GET['edit'])) {
  $edit = satu("SELECT * FROM departments WHERE id=?", array($_GET['edit']));
}
$nilai = array('id' => '', 'nama' => '', 'keterangan' => '');
if ($edit) {
  $nilai = $edit;
}

// Ambil semua departemen beserta jumlah karyawannya
$semua_departemen = daftar("
 SELECT d.*, COUNT(u.id) AS total_karyawan 
 FROM departments d
 LEFT JOIN users u ON u.departemen_id = d.id AND u.role = 'karyawan'
 GROUP BY d.id
 ORDER BY d.nama ASC
");

top('Manajemen Departemen');
?>

<div class="grid gap-6 lg:grid-cols-3">

  <!-- Formulir tambah / ubah -->
  <div class="lg:col-span-1">
    <?php kartu_awal($edit ? 'Ubah Data Departemen' : 'Tambah Departemen Baru'); ?>
    <form method="POST" data-validasi novalidate>
      <input type="hidden" name="id" value="<?= e($nilai['id']) ?>">

      <?php isian('Nama Departemen / Divisi', 'nama', 'text', $nilai['nama'], 'required placeholder="Contoh: Teknologi Informasi"'); ?>

      <div class="mb-4">
        <label class="label">Keterangan / Deskripsi</label>
        <textarea name="keterangan" rows="3" class="kolom text-sm" placeholder="Deskripsi tugas atau ruang lingkup departemen..."><?= e($nilai['keterangan']) ?></textarea>
      </div>

      <div class="flex gap-2">
        <button class="btn btn-utama flex-1"><?= $edit ? 'Perbarui' : 'Tambahkan' ?></button>
        <?php if ($edit): ?>
          <a href="admin_departemen.php" class="btn btn-garis">Batal</a>
        <?php endif; ?>
      </div>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <!-- Daftar departemen -->
  <div class="lg:col-span-2">
    <?php kartu_awal('Daftar Departemen Perusahaan'); ?>

    <div class="mb-3">
      <input id="cari" class="kolom text-xs" placeholder="Cari nama departemen...">
    </div>

    <?php if (count($semua_departemen) === 0): ?>
      <?php kosong('Belum ada departemen yang dibuat.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Departemen', 'Keterangan', 'Jumlah Karyawan', 'Aksi'), 'tabel-cari'); ?>
      <?php foreach ($semua_departemen as $d): ?>
        <tr>
          <td data-label="Departemen" class="font-medium text-stone-800"><?= e($d['nama']) ?></td>
          <td data-label="Keterangan" class="text-xs text-stone-600"><?= e($d['keterangan'] ?: '-') ?></td>
          <td data-label="Jumlah Karyawan">
            <span class="lencana lencana-netral"><?= (int)$d['total_karyawan'] ?> Orang</span>
          </td>
          <td data-label="Aksi" class="whitespace-nowrap">
            <div class="flex gap-1.5">
              <a href="?edit=<?= e($d['id']) ?>" class="btn btn-garis btn-kecil">Ubah</a>
              <form method="POST" data-konfirmasi="Hapus departemen '<?= e($d['nama']) ?>'? Relasi pada karyawan akan diatur menjadi kosong.">
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
