<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();

if (!function_exists('semua')) {
  function semua($sql, $param = array())
  {
    return daftar($sql, $param);
  }
}

$tanggal_pilih = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$status_pilih = isset($_GET['status']) ? $_GET['status'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');

  if ($aksi === 'simpan_jam_kerja' || $aksi === 'simpan_jam_default' || $aksi === 'simpan_jadwal_rentang') {
    $jam_masuk_buka = trim(isi('jam_masuk_buka'));
    $jam_masuk_tepat = trim(isi('jam_masuk_tepat'));
    $jam_pulang_buka = trim(isi('jam_pulang_buka'));
    $tanggal_mulai = trim(isi('tanggal_mulai'));
    $tanggal_selesai = trim(isi('tanggal_selesai'));

    if ($jam_masuk_buka === '' || $jam_masuk_tepat === '' || $jam_pulang_buka === '') {
      pesan('Semua kolom jam kerja wajib diisi.', 'gagal');
    } elseif ($jam_masuk_buka >= $jam_masuk_tepat) {
      pesan('Jam mulai presensi harus lebih awal daripada batas jam reguler tepat waktu.', 'gagal');
    } elseif ($jam_masuk_tepat >= $jam_pulang_buka) {
      pesan('Batas jam tepat waktu harus lebih awal daripada jam pulang kerja.', 'gagal');
    } elseif ($tanggal_mulai === '' || $tanggal_selesai === '') {
      pesan('Tanggal mulai dan tanggal selesai berlaku wajib diisi. Jam kerja hanya berlaku pada rentang tanggal yang diatur.', 'gagal');
    } elseif ($tanggal_mulai > $tanggal_selesai) {
      pesan('Tanggal mulai tidak boleh lebih besar dari tanggal selesai.', 'gagal');
    } else {
      jalankan("DELETE FROM work_schedules WHERE tanggal_mulai <= ? AND tanggal_selesai >= ?", array($tanggal_selesai, $tanggal_mulai));

      jalankan(
        "INSERT INTO work_schedules (id, nama, tanggal_mulai, tanggal_selesai, jam_masuk_buka, jam_masuk_tepat, jam_pulang_buka) VALUES (?, 'Jadwal Kerja', ?, ?, ?, ?, ?)",
        array(buat_id(), $tanggal_mulai, $tanggal_selesai, $jam_masuk_buka, $jam_masuk_tepat, $jam_pulang_buka)
      );
      pesan("Pengaturan jam kerja berhasil disimpan untuk rentang " . tgl($tanggal_mulai) . " s/d " . tgl($tanggal_selesai) . ". Berlaku otomatis setiap hari tanpa perlu diatur ulang.");
    }

  } elseif ($aksi === 'reset_jam_standar') {
    $std = jam_kerja_standar();
    jalankan(
      "UPDATE work_schedules SET jam_masuk_buka=?, jam_masuk_tepat=?, jam_pulang_buka=? WHERE tanggal_selesai >= ?",
      array($std['jam_masuk_buka'], $std['jam_masuk_tepat'], $std['jam_pulang_buka'], date('Y-m-d'))
    );
    pesan('Jam kerja pada jadwal yang sedang/akan berjalan dikembalikan ke standar: Masuk 08:00 - 08:30 WIB (Pulang 17:00 WIB).');

  } elseif ($aksi === 'hapus_jadwal_rentang') {
    $id_jadwal = isi('id');
    jalankan("DELETE FROM work_schedules WHERE id=?", array($id_jadwal));
    pesan('Jadwal dihapus. Tanggal pada rentang tersebut tidak lagi memiliki jam kerja (dianggap libur).');

  } elseif ($aksi === 'simpan_manual') {
    $user_id = isi('user_id');
    $tgl_absen = isi('tanggal');
    $jam_masuk = trim(isi('jam_masuk'));
    $jam_pulang = trim(isi('jam_pulang'));
    $status = isi('status');
    $catatan = trim(isi('catatan'));

    if ($user_id === '' || $tgl_absen === '') {
      pesan('Karyawan dan tanggal wajib dipilih.', 'gagal');
    } else {
      $ada = satu("SELECT id, bukti FROM attendances WHERE user_id=? AND tanggal=?", array($user_id, $tgl_absen));

      $nama_file = $ada ? $ada['bukti'] : null;
      $error_upload = '';
      if (ada_file('bukti')) {
        $hasil_upload = upload('bukti', 'bukti_absensi', array('pdf', 'jpg', 'jpeg', 'png'), 5);
        if ($hasil_upload[1]) {
          $error_upload = $hasil_upload[1];
        } else {
          $nama_file = $hasil_upload[0];
        }
      }

      if ($error_upload !== '') {
        pesan($error_upload, 'gagal');
      } else {
        if ($ada) {
          jalankan(
            "UPDATE attendances SET jam_masuk=?, jam_pulang=?, status=?, catatan=?, bukti=? WHERE id=?",
            array($jam_masuk ?: null, $jam_pulang ?: null, $status, $catatan, $nama_file, $ada['id'])
          );
          pesan('Data absensi berhasil diperbarui.');
        } else {
          jalankan(
            "INSERT INTO attendances (id, user_id, tanggal, jam_masuk, jam_pulang, status, catatan, bukti, schedule_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array(buat_id(), $user_id, $tgl_absen, $jam_masuk ?: null, $jam_pulang ?: null, $status, $catatan, $nama_file, ambil_jam_kerja($tgl_absen)['id'])
          );
          pesan('Data absensi manual berhasil ditambahkan.');
        }
      }
    }

  } elseif ($aksi === 'hapus') {
    $id = isi('id');
    jalankan("DELETE FROM attendances WHERE id=?", array($id));
    pesan('Catatan absensi dihapus.');
  }

  pindah("admin_absensi.php?tanggal=" . urlencode($tanggal_pilih) . "&status=" . urlencode($status_pilih));
}

$hari_ini = date('Y-m-d');
jalankan("DELETE FROM attendances WHERE status='Alfa' AND catatan LIKE 'Otomatis Alfa%' AND tanggal < ?", array($hari_ini));

sinkron_alfa_otomatis($hari_ini);

$jadwal_aktif_rentang = satu("SELECT * FROM work_schedules WHERE tanggal_mulai <= ? AND tanggal_selesai >= ? ORDER BY created_at DESC LIMIT 1", array($hari_ini, $hari_ini));
if (!$jadwal_aktif_rentang) {
  $jadwal_aktif_rentang = satu("SELECT * FROM work_schedules ORDER BY tanggal_mulai DESC LIMIT 1");
}

$jam_std = jam_kerja_standar();
$val_masuk_buka = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['jam_masuk_buka'] : $jam_std['jam_masuk_buka'];
$val_masuk_tepat = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['jam_masuk_tepat'] : $jam_std['jam_masuk_tepat'];
$val_pulang_buka = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['jam_pulang_buka'] : $jam_std['jam_pulang_buka'];
$val_tgl_mulai = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['tanggal_mulai'] : $hari_ini;
$val_tgl_selesai = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['tanggal_selesai'] : date('Y-m-d', strtotime('+1 month'));

$jk_aktif_pilih = ambil_jam_kerja($tanggal_pilih);

$params = array($tanggal_pilih);
$sql_karyawan = "
  SELECT 
    u.id AS user_id, 
    u.nip, 
    u.nama, 
    a.id, 
    a.jam_masuk, 
    a.jam_pulang, 
    a.status, 
    a.catatan, 
    a.bukti
  FROM users u
  LEFT JOIN attendances a ON a.user_id = u.id AND a.tanggal = ?
  WHERE u.role = 'karyawan'
  ORDER BY u.nama ASC
";

$semua_karyawan = daftar($sql_karyawan, $params);

$total_karyawan = count($semua_karyawan);
$total_hadir = 0;
$total_terlambat = 0;
$total_izin_sakit = 0;
$total_alfa = 0;

foreach ($semua_karyawan as $row) {
  if ($row['status'] === 'Hadir') {
    $total_hadir++;
  } elseif ($row['status'] === 'Terlambat') {
    $total_terlambat++;
  } elseif ($row['status'] === 'Izin' || $row['status'] === 'Sakit') {
    $total_izin_sakit++;
  } elseif ($row['status'] === 'Alfa') {
    $total_alfa++;
  }
}

$tampil_karyawan = array();
foreach ($semua_karyawan as $k) {
  if ($status_pilih === '') {
    $tampil_karyawan[] = $k;
  } elseif ($status_pilih === 'Belum Absen') {
    if (empty($k['status'])) {
      $tampil_karyawan[] = $k;
    }
  } else {
    if ($k['status'] === $status_pilih) {
      $tampil_karyawan[] = $k;
    }
  }
}

$opsi_karyawan = array('' => '-- Pilih Karyawan --');
$list_user = daftar("SELECT id, nip, nama FROM users WHERE role='karyawan' ORDER BY nama ASC");
foreach ($list_user as $u) {
  $opsi_karyawan[$u['id']] = $u['nip'] . ' - ' . $u['nama'];
}

top('Rekap & Monitoring Absensi Karyawan');
?>

<div class="kartu mb-6">
  <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-200 pb-3">
    <div>
      <h2 class="text-base font-semibold text-stone-800">Atur Jam Kerja Kantor & Rentang Tanggal</h2>
      <p class="text-xs text-stone-500">Atur batas presensi harian dan tentukan rentang tanggal berlakunya sekaligus dalam satu form praktis.</p>
    </div>
    <div class="flex items-center gap-2">
      <span class="rounded bg-stone-100 px-2.5 py-1 text-xs font-medium text-black border border-stone-300">
        Zona Waktu: WIB (Asia/Jakarta)
      </span>
      <form method="POST" data-konfirmasi="Kembalikan jam pada jadwal yang sedang/akan berjalan ke standar (08:00 - 08:30 WIB | Pulang 17:00 WIB)?" class="inline">
        <input type="hidden" name="aksi" value="reset_jam_standar">
        <button type="submit" class="btn btn-garis btn-kecil text-stone-600 hover:text-stone-800" title="Kembalikan ke standar bawaan">
          Reset Default Standar
        </button>
      </form>
    </div>
  </div>

  <form method="POST" class="mt-4" data-validasi novalidate>
    <input type="hidden" name="aksi" value="simpan_jam_kerja">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div>
        <label class="label">1. Jam Mulai Presensi</label>
        <input type="time" name="jam_masuk_buka" value="<?= e($val_masuk_buka) ?>" class="kolom text-sm" required>
        <p class="mt-1 text-[11px] text-stone-400">Presensi sistem mulai dibuka</p>
      </div>
      <div>
        <label class="label">2. Batas Tepat Waktu (Reguler)</label>
        <input type="time" name="jam_masuk_tepat" value="<?= e($val_masuk_tepat) ?>" class="kolom text-sm" required>
        <p class="mt-1 text-[11px] font-medium text-black">Lewat jam ini = Dihitung Terlambat</p>
      </div>
      <div>
        <label class="label">3. Jam Pulang Kerja</label>
        <input type="time" name="jam_pulang_buka" value="<?= e($val_pulang_buka) ?>" class="kolom text-sm" required>
        <p class="mt-1 text-[11px] font-medium text-black">Tidak absen hingga jam ini = Otomatis Alfa</p>
      </div>
    </div>

    <div class="mt-4 pt-4 border-t border-stone-100 flex flex-wrap items-end justify-between gap-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1">
        <div>
          <label class="label">Tanggal Mulai Berlaku</label>
          <input type="date" name="tanggal_mulai" value="<?= e($val_tgl_mulai) ?>" class="kolom text-sm" required>
          <p class="mt-1 text-[11px] text-stone-400">Tanggal awal jam kerja ini mulai aktif</p>
        </div>
        <div>
          <label class="label">Tanggal Selesai Berlaku</label>
          <input type="date" name="tanggal_selesai" value="<?= e($val_tgl_selesai) ?>" class="kolom text-sm" required>
          <p class="mt-1 text-[11px] text-stone-400">Pilih rentang (misal 1 bulan / 1 tahun)</p>
        </div>
      </div>
      <div class="w-full sm:w-auto">
        <button type="submit" class="btn btn-utama w-full sm:w-auto h-[42px] px-6 flex items-center justify-center gap-2">
          <?= ikon('check') ?> Simpan Jam Kerja
        </button>
        <p class="mt-1 text-[11px] text-black font-medium text-center sm:text-right">✓ Berlaku otomatis, tidak perlu diatur tiap hari</p>
      </div>
    </div>
  </form>

  <?php if ($jadwal_aktif_rentang): ?>
    <div class="mt-4 pt-3 border-t border-stone-100 flex items-center justify-between text-xs text-stone-600 bg-stone-50 p-2.5 rounded-md">
      <span>
        Rentang tersimpan: <strong><?= tgl($jadwal_aktif_rentang['tanggal_mulai']) ?> s/d <?= tgl($jadwal_aktif_rentang['tanggal_selesai']) ?></strong>
        (Masuk: <?= e($jadwal_aktif_rentang['jam_masuk_buka']) ?> - <?= e($jadwal_aktif_rentang['jam_masuk_tepat']) ?> WIB, Pulang: <?= e($jadwal_aktif_rentang['jam_pulang_buka']) ?> WIB)
      </span>
      <form method="POST" data-konfirmasi="Hapus jadwal ini? Tanggal pada rentang tersebut akan dianggap libur (tanpa presensi)." class="inline">
        <input type="hidden" name="aksi" value="hapus_jadwal_rentang">
        <input type="hidden" name="id" value="<?= e($jadwal_aktif_rentang['id']) ?>">
        <button class="text-black underline hover:text-black font-medium">Hapus Rentang</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<div class="kartu mb-6">
  <div class="border-b border-stone-100 pb-3">
    <h2 class="text-base font-semibold">Monitoring Kehadiran: <?= tgl($tanggal_pilih) ?></h2>
    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
      <span class="text-stone-500">Status jadwal tanggal ini:</span>
      <?php if ($jk_aktif_pilih['ada_jadwal']): ?>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-0.5 font-semibold text-black border border-stone-300">
          <?= e($jk_aktif_pilih['nama']) ?>: <strong><?= e($jk_aktif_pilih['jam_masuk_buka']) ?> - <?= e($jk_aktif_pilih['jam_masuk_tepat']) ?> WIB</strong> (Pulang <?= e($jk_aktif_pilih['jam_pulang_buka']) ?> WIB)
        </span>
      <?php else: ?>
        <span class="font-semibold text-black">
          <?= e($jk_aktif_pilih['pesan']) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <div class="pt-3">
    <form method="GET" class="flex flex-wrap items-center gap-2.5">
      <div>
        <input type="date" name="tanggal" value="<?= e($tanggal_pilih) ?>" class="rounded-md border border-stone-300 bg-white px-2.5 py-1.5 text-xs focus:border-teal-600 focus:outline-none" title="Pilih Tanggal">
      </div>
      <div>
        <select name="status" class="rounded-md border border-stone-300 bg-white px-2.5 py-1.5 text-xs focus:border-teal-600 focus:outline-none" title="Pilih Keterangan Kehadiran">
          <option value="">Semua Keterangan</option>
          <option value="Hadir" <?= $status_pilih === 'Hadir' ? 'selected' : '' ?>>Hadir (Tepat Waktu)</option>
          <option value="Terlambat" <?= $status_pilih === 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
          <option value="Izin" <?= $status_pilih === 'Izin' ? 'selected' : '' ?>>Izin</option>
          <option value="Sakit" <?= $status_pilih === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
          <option value="Alfa" <?= $status_pilih === 'Alfa' ? 'selected' : '' ?>>Alfa</option>
          <option value="Belum Absen" <?= $status_pilih === 'Belum Absen' ? 'selected' : '' ?>>Belum Absen</option>
        </select>
      </div>
      <button class="btn btn-utama btn-kecil">Terapkan Filter</button>
      <?php if ($status_pilih !== '' || $tanggal_pilih !== date('Y-m-d')): ?>
        <a href="admin_absensi.php" class="btn btn-garis btn-kecil">Reset Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$jk_aktif_pilih['ada_jadwal']): ?>
    <div class="mt-4 rounded-lg bg-yellow-100 p-3.5 text-xs text-black flex items-start gap-3 shadow-sm">
      <span class="mt-0.5 text-warn-800"><?= ikon('peringatan', 'h-5 w-5') ?></span>
      <div>
        <p class="font-bold text-black text-xs sm:text-sm">Pengumuman: Tidak ada jam kerja pada tanggal tersebut</p>
        <p class="text-black mt-0.5">Tanggal <strong><?= tgl($tanggal_pilih) ?></strong> tidak disetting jadwal presensi (hari libur atau di luar rentang jam kerja). Tidak ada kewajiban presensi kehadiran dan otomatisasi status Alfa dinonaktifkan untuk tanggal ini.</p>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
  <div class="kartu text-center">
    <p class="text-xs text-stone-500">Total Karyawan</p>
    <p class="text-2xl font-bold text-stone-800"><?= $total_karyawan ?></p>
  </div>
  <div class="kartu text-center">
    <p class="text-xs text-stone-500">Hadir Tepat Waktu</p>
    <p class="text-2xl font-bold text-black"><?= $total_hadir ?></p>
  </div>
  <div class="kartu text-center">
    <p class="text-xs text-stone-500">Terlambat</p>
    <p class="text-2xl font-bold text-black"><?= $total_terlambat ?></p>
  </div>
  <div class="kartu text-center">
    <p class="text-xs text-stone-500">Izin / Sakit</p>
    <p class="text-2xl font-bold text-black"><?= $total_izin_sakit ?></p>
  </div>
  <div class="kartu text-center">
    <p class="text-xs text-stone-500">Alfa (Tidak Hadir)</p>
    <p class="text-2xl font-bold text-black"><?= $total_alfa ?></p>
  </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <div class="lg:col-span-1">
    <?php kartu_awal('Input / Koreksi Manual'); ?>
    <p class="-mt-2 mb-4 text-xs text-stone-500">Gunakan form ini bila karyawan lupa absen atau perlu penyesuaian dinas luar / izin sakit.</p>
    <form method="POST" enctype="multipart/form-data" data-validasi novalidate>
      <input type="hidden" name="aksi" value="simpan_manual">
      <?php pilih('Karyawan', 'user_id', $opsi_karyawan, '', 'required'); ?>
      <?php isian('Tanggal', 'tanggal', 'date', $tanggal_pilih, 'required'); ?>
      <div class="grid grid-cols-2 gap-2">
        <?php isian('Jam Masuk', 'jam_masuk', 'time', '08:00'); ?>
        <?php isian('Jam Pulang', 'jam_pulang', 'time', '17:00'); ?>
      </div>
      <?php pilih('Status Kehadiran', 'status', array('Hadir' => 'Hadir (Tepat Waktu)', 'Terlambat' => 'Terlambat', 'Izin' => 'Izin', 'Sakit' => 'Sakit', 'Alfa' => 'Alfa (Tanpa Keterangan)')); ?>
      <div class="mb-3">
        <label class="label">Catatan</label>
        <input type="text" name="catatan" placeholder="Contoh: Koreksi absensi dinas luar / izin dokter" class="kolom text-sm">
      </div>
      <div class="mb-4">
        <label class="label">Lampiran Bukti (Opsional)</label>
        <input type="file" name="bukti" accept=".pdf,.jpg,.jpeg,.png" class="kolom text-xs file:mr-2 file:rounded file:border-0 file:bg-stone-100 file:px-2 file:py-1 file:text-xs">
        <p class="mt-1 text-[11px] text-stone-400">PDF, JPG, PNG (Maks 5 MB)</p>
      </div>
      <button class="btn btn-utama w-full">Simpan Data Absensi</button>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <div class="lg:col-span-2">
    <?php kartu_awal('Daftar Kehadiran: ' . tgl($tanggal_pilih)); ?>
    <div class="mb-3">
      <input id="cari" class="kolom text-xs" placeholder="Cari nama atau NIP karyawan...">
    </div>
    <?php if (count($tampil_karyawan) === 0): ?>
      <?php kosong($status_pilih !== '' ? 'Tidak ada data karyawan dengan keterangan "' . e($status_pilih) . '" pada tanggal ini.' : 'Tidak ada data karyawan ditemukan.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Karyawan', 'Jam Masuk', 'Jam Pulang', 'Status & Catatan', 'Aksi'), 'tabel-cari'); ?>
      <?php foreach ($tampil_karyawan as $row):
        $status_tampil = $row['status'] ?: 'Belum Absen';
      ?>
        <tr>
          <td data-label="Karyawan">
            <span class="font-medium text-stone-800"><?= e($row['nama']) ?></span>
            <br><span class="text-xs text-stone-400">NIP: <?= e($row['nip']) ?></span>
          </td>
          <td data-label="Jam Masuk"><?= e($row['jam_masuk'] ? $row['jam_masuk'] . ' WIB' : '-') ?></td>
          <td data-label="Jam Pulang"><?= e($row['jam_pulang'] ? $row['jam_pulang'] . ' WIB' : '-') ?></td>
          <td data-label="Status & Catatan">
            <?php if ($row['status']): ?>
              <?= lencana($row['status']) ?>
              <?php if (!empty($row['catatan'])): ?>
                <p class="text-[11px] text-stone-600 mt-0.5"><?= e($row['catatan']) ?></p>
              <?php endif; ?>
              <?php if (!empty($row['bukti'])): ?>
                <a href="unduh.php?jenis=bukti_absensi&id=<?= e($row['id']) ?>&unduh=1" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-black underline">
                  Lihat Surat / Bukti
                </a>
              <?php endif; ?>
            <?php else: ?>
              <?= lencana('Belum Absen') ?>
            <?php endif; ?>
          </td>
          <td data-label="Aksi" class="whitespace-nowrap">
            <?php if (!empty($row['id'])): ?>
              <form method="POST" data-konfirmasi="Hapus data absensi ini?" class="inline">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                <button class="btn btn-bahaya btn-kecil">Hapus</button>
              </form>
            <?php else: ?>
              <span class="text-xs text-stone-400">-</span>
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