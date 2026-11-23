<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();

// Helper & fallback bila belum terdefinisi di src/auth.php
if (!function_exists('ambil_pengaturan_jam_kerja')) {
  function ambil_pengaturan_jam_kerja()
  {
    return array(
      'jam_masuk_buka'  => ambil_pengaturan('jam_masuk_buka', '08:00'),
      'jam_masuk_tepat' => ambil_pengaturan('jam_masuk_tepat', '08:30'),
      'jam_pulang_buka' => ambil_pengaturan('jam_pulang_buka', '17:00'),
    );
  }
}
if (!function_exists('semua')) {
  function semua($sql, $param = array())
  {
    return daftar($sql, $param);
  }
}
if (!function_exists('kembali')) {
  function kembali()
  {
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin_absensi.php';
    pindah($ref);
  }
}

$tanggal_pilih = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$dept_pilih = isset($_GET['departemen']) ? $_GET['departemen'] : '';
$status_pilih = isset($_GET['status']) ? $_GET['status'] : '';

// Proses Simpan Jam Kerja Tunggal / Reset / Input Manual / Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');

  // Simpan Pengaturan Jam Kerja (Sekaligus Default & Rentang Tanggal dalam 1 Form)
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
    } elseif ($tanggal_mulai !== '' && $tanggal_selesai !== '' && $tanggal_mulai > $tanggal_selesai) {
      pesan('Tanggal mulai tidak boleh lebih besar dari tanggal selesai.', 'gagal');
    } else {
      // 1. Simpan sebagai default permanen di tabel settings
      simpan_pengaturan('jam_masuk_buka', $jam_masuk_buka);
      simpan_pengaturan('jam_masuk_tepat', $jam_masuk_tepat);
      simpan_pengaturan('jam_pulang_buka', $jam_pulang_buka);

      // 2. Simpan rentang tanggal jika ditentukan
      if ($tanggal_mulai !== '' && $tanggal_selesai !== '') {
        // Bersihkan jadwal lama agar data tidak menumpuk dan tidak redundan
        jalankan("DELETE FROM work_schedules WHERE (tanggal_mulai <= ? AND tanggal_selesai >= ?) OR created_at < datetime('now', '-30 days')", array($tanggal_selesai, $tanggal_mulai));

        jalankan(
          "INSERT INTO work_schedules (id, nama, tanggal_mulai, tanggal_selesai, jam_masuk_buka, jam_masuk_tepat, jam_pulang_buka) VALUES (?, 'Jadwal Kerja', ?, ?, ?, ?, ?)",
          array(buat_id(), $tanggal_mulai, $tanggal_selesai, $jam_masuk_buka, $jam_masuk_tepat, $jam_pulang_buka)
        );
        pesan("Pengaturan jam kerja berhasil disimpan untuk rentang " . tgl($tanggal_mulai) . " s/d " . tgl($tanggal_selesai) . ". Berlaku otomatis setiap hari tanpa perlu diatur ulang.");
      } else {
        pesan("Jam kerja default berhasil disimpan ($jam_masuk_buka - $jam_masuk_tepat WIB, pulang $jam_pulang_buka WIB) dan berlaku permanen seterusnya.");
      }
    }
  } elseif ($aksi === 'reset_jam_standar') {
    // Reset Jam Kerja Kembali ke Standar Default (08:00 - 08:30 WIB | Pulang 17:00 WIB)
    simpan_pengaturan('jam_masuk_buka', '08:00');
    simpan_pengaturan('jam_masuk_tepat', '08:30');
    simpan_pengaturan('jam_pulang_buka', '17:00');
    jalankan("DELETE FROM work_schedules");
    pesan('Jam kerja berhasil dikembalikan ke standar awal: Masuk 08:00 - 08:30 WIB (Pulang 17:00 WIB).');
  } elseif ($aksi === 'hapus_jadwal_rentang') {
    // Hapus Jadwal Rentang Khusus
    $id_jadwal = isi('id');
    jalankan("DELETE FROM work_schedules WHERE id=?", array($id_jadwal));
    pesan('Rentang tanggal khusus dihapus. Jam kerja kembali ke standar default.');
  } elseif ($aksi === 'simpan_manual') {
    // Input / Koreksi Manual Absensi
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
            "INSERT INTO attendances (id, user_id, tanggal, jam_masuk, jam_pulang, status, catatan, bukti) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            array(buat_id(), $user_id, $tgl_absen, $jam_masuk ?: null, $jam_pulang ?: null, $status, $catatan, $nama_file)
          );
          pesan('Data absensi manual berhasil ditambahkan.');
        }
      }
    }
  } elseif ($aksi === 'hapus') {
    // Hapus Catatan Absensi
    $id = isi('id');
    jalankan("DELETE FROM attendances WHERE id=?", array($id));
    pesan('Catatan absensi dihapus.');
  }

  pindah("admin_absensi.php?tanggal=" . urlencode($tanggal_pilih) . "&departemen=" . urlencode($dept_pilih) . "&status=" . urlencode($status_pilih));
}

// Bersihkan catatan otomatis Alfa lama yang sempat tercipta di tanggal-tanggal lampau sebelum aturan disetting
$hari_ini = date('Y-m-d');
jalankan("DELETE FROM attendances WHERE status='Alfa' AND catatan LIKE 'Otomatis Alfa%' AND tanggal < ?", array($hari_ini));

// Jalankan sinkronisasi Alfa HANYA untuk hari ini jika jam pulang kerja sudah terlewati
sinkron_alfa_otomatis($hari_ini);

// Ambil konfigurasi jam kerja tersimpan
$jadwal_aktif_rentang = satu("SELECT * FROM work_schedules WHERE tanggal_mulai <= ? AND tanggal_selesai >= ? ORDER BY created_at DESC LIMIT 1", array($hari_ini, $hari_ini));
if (!$jadwal_aktif_rentang) {
  $jadwal_aktif_rentang = satu("SELECT * FROM work_schedules ORDER BY tanggal_mulai DESC LIMIT 1");
}

// Nilai input form awal
$val_masuk_buka = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['jam_masuk_buka'] : ambil_pengaturan('jam_masuk_buka', '08:00');
$val_masuk_tepat = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['jam_masuk_tepat'] : ambil_pengaturan('jam_masuk_tepat', '08:30');
$val_pulang_buka = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['jam_pulang_buka'] : ambil_pengaturan('jam_pulang_buka', '17:00');
$val_tgl_mulai = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['tanggal_mulai'] : $hari_ini;
$val_tgl_selesai = $jadwal_aktif_rentang ? $jadwal_aktif_rentang['tanggal_selesai'] : date('Y-m-d', strtotime('+1 month'));

// Cek aturan jam kerja yang aktif khusus pada tanggal yang sedang dipantau di filter
$jk_aktif_pilih = ambil_jam_kerja($tanggal_pilih);

// Data departemen untuk dropdown filter
$daftar_dept = daftar("SELECT id, nama FROM departments ORDER BY nama ASC");

// Query seluruh absensi karyawan berdasarkan filter tanggal & departemen
$params = array($tanggal_pilih);
$sql_karyawan = "
 SELECT 
  u.id AS user_id, 
  u.nip, 
  u.nama, 
  d.nama AS nama_departemen,
  a.id, 
  a.jam_masuk, 
  a.jam_pulang, 
  a.status, 
  a.catatan, 
  a.bukti
 FROM users u
 LEFT JOIN departments d ON d.id = u.departemen_id
 LEFT JOIN attendances a ON a.user_id = u.id AND a.tanggal = ?
 WHERE u.role = 'karyawan'
";

if ($dept_pilih !== '') {
  $sql_karyawan .= " AND u.departemen_id = ?";
  $params[] = $dept_pilih;
}
$sql_karyawan .= " ORDER BY u.nama ASC";

$semua_karyawan = daftar($sql_karyawan, $params);

// Ringkasan kehadiran pada tanggal terpilih (sebelum difilter status)
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

// Filter data karyawan yang ditampilkan di tabel berdasarkan status/keterangan yang dipilih
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

// Opsi list karyawan untuk input manual
$opsi_karyawan = array('' => 'Pilih karyawan');
$list_user = daftar("SELECT id, nip, nama FROM users WHERE role='karyawan' ORDER BY nama ASC");
foreach ($list_user as $u) {
  $opsi_karyawan[$u['id']] = $u['nip'] . ' - ' . $u['nama'];
}

top('Rekap & Monitoring Absensi Karyawan');
?>

<!-- Pengaturan jam kerja dan rentang tanggal berlakunya -->
<div class="kartu mb-6">
  <div class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-200 pb-3">
    <div>
      <h2 class="text-base font-semibold text-stone-800">Atur Jam Kerja Kantor & Rentang Tanggal</h2>
      <p class="keterangan mt-0.5">Atur batas presensi harian dan tentukan rentang tanggal berlakunya dalam satu form.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <span class="lencana lencana-netral">Zona waktu: WIB (Asia/Jakarta)</span>
      <form method="POST" data-konfirmasi="Kembalikan jam kerja ke standar awal (08:00 - 08:30 WIB | Pulang 17:00 WIB)?"><?= csrf_field() ?>
        <input type="hidden" name="aksi" value="reset_jam_standar">
        <button type="submit" class="btn btn-garis btn-kecil" title="Kembalikan ke standar bawaan">Reset ke standar</button>
      </form>
    </div>
  </div>

  <form method="POST" class="mt-4" data-validasi novalidate><?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan_jam_kerja">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div>
        <label class="label">Jam mulai presensi</label>
        <input type="time" name="jam_masuk_buka" value="<?= e($val_masuk_buka) ?>" class="kolom text-sm" required>
        <p class="keterangan mt-1">Presensi sistem mulai dibuka</p>
      </div>
      <div>
        <label class="label">Batas tepat waktu (reguler)</label>
        <input type="time" name="jam_masuk_tepat" value="<?= e($val_masuk_tepat) ?>" class="kolom text-sm" required>
        <p class="keterangan mt-1">Lewat jam ini dihitung terlambat</p>
      </div>
      <div>
        <label class="label">Jam pulang kerja</label>
        <input type="time" name="jam_pulang_buka" value="<?= e($val_pulang_buka) ?>" class="kolom text-sm" required>
        <p class="keterangan mt-1">Tidak absen hingga jam ini otomatis alfa</p>
      </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 border-t border-stone-100 pt-4 sm:grid-cols-2">
      <div>
        <label class="label">Tanggal mulai berlaku</label>
        <input type="date" name="tanggal_mulai" value="<?= e($val_tgl_mulai) ?>" class="kolom text-sm">
        <p class="keterangan mt-1">Tanggal awal jam kerja ini mulai aktif</p>
      </div>
      <div>
        <label class="label">Tanggal selesai berlaku</label>
        <input type="date" name="tanggal_selesai" value="<?= e($val_tgl_selesai) ?>" class="kolom text-sm">
        <p class="keterangan mt-1">Pilih rentang, misalnya 1 bulan atau 1 tahun</p>
      </div>
    </div>

    <div class="mt-4 flex justify-end">
      <button type="submit" class="btn btn-utama w-full gap-2 sm:w-auto">
        <?= ikon('check', 'h-4 w-4') ?><span>Simpan Jam Kerja</span>
      </button>
    </div>
  </form>

  <?php if ($jadwal_aktif_rentang): ?>
    <div class="petak mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-stone-600">
      <span>
        Rentang tersimpan: <strong><?= tgl($jadwal_aktif_rentang['tanggal_mulai']) ?> s/d <?= tgl($jadwal_aktif_rentang['tanggal_selesai']) ?></strong>
        (masuk: <?= e($jadwal_aktif_rentang['jam_masuk_buka']) ?> - <?= e($jadwal_aktif_rentang['jam_masuk_tepat']) ?> WIB, pulang: <?= e($jadwal_aktif_rentang['jam_pulang_buka']) ?> WIB)
      </span>
      <form method="POST" data-konfirmasi="Hapus rentang ini dan gunakan jam reguler default?"><?= csrf_field() ?>
        <input type="hidden" name="aksi" value="hapus_jadwal_rentang">
        <input type="hidden" name="id" value="<?= e($jadwal_aktif_rentang['id']) ?>">
        <button class="font-medium text-danger-700 underline underline-offset-2 hover:text-danger-800">Hapus rentang</button>
      </form>
    </div>
  <?php endif; ?>
</div>


<!-- Filter monitoring: tanggal, departemen, dan keterangan kehadiran -->
<div class="kartu mb-6">
  <div class="border-b border-stone-100 pb-3">
    <h2 class="text-base font-semibold">Monitoring Kehadiran: <?= tgl($tanggal_pilih) ?></h2>
    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
      <span class="text-stone-500">Status jadwal tanggal ini:</span>
      <?php if ($jk_aktif_pilih['ada_jadwal']): ?>
        <span class="lencana lencana-netral">
          <?= e($jk_aktif_pilih['nama']) ?>: <?= e($jk_aktif_pilih['jam_masuk_buka']) ?> - <?= e($jk_aktif_pilih['jam_masuk_tepat']) ?> WIB (pulang <?= e($jk_aktif_pilih['jam_pulang_buka']) ?> WIB)
        </span>
      <?php else: ?>
        <span class="lencana lencana-peringatan"><?= e($jk_aktif_pilih['pesan']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="pt-3">
    <form method="GET" class="flex flex-wrap items-center gap-2.5">
      <input type="date" name="tanggal" value="<?= e($tanggal_pilih) ?>" class="pilih-kecil" title="Pilih tanggal">

      <select name="departemen" class="pilih-kecil" title="Pilih departemen">
        <option value="">Semua Departemen</option>
        <?php foreach ($daftar_dept as $dp): ?>
          <option value="<?= e($dp['id']) ?>" <?= $dept_pilih === $dp['id'] ? 'selected' : '' ?>><?= e($dp['nama']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="pilih-kecil" title="Pilih keterangan kehadiran">
        <option value="">Semua Keterangan</option>
        <option value="Hadir" <?= $status_pilih === 'Hadir' ? 'selected' : '' ?>>Hadir (Tepat Waktu)</option>
        <option value="Terlambat" <?= $status_pilih === 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
        <option value="Izin" <?= $status_pilih === 'Izin' ? 'selected' : '' ?>>Izin</option>
        <option value="Sakit" <?= $status_pilih === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
        <option value="Alfa" <?= $status_pilih === 'Alfa' ? 'selected' : '' ?>>Alfa</option>
        <option value="Belum Absen" <?= $status_pilih === 'Belum Absen' ? 'selected' : '' ?>>Belum Absen</option>
      </select>

      <button class="btn btn-utama btn-kecil">Terapkan Filter</button>
      <?php if ($dept_pilih !== '' || $status_pilih !== '' || $tanggal_pilih !== date('Y-m-d')): ?>
        <a href="admin_absensi.php" class="btn btn-garis btn-kecil">Reset Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$jk_aktif_pilih['ada_jadwal']): ?>
    <div class="notif notif-peringatan mt-4 flex items-start gap-3 text-xs">
      <span class="mt-0.5"><?= ikon('peringatan', 'h-5 w-5') ?></span>
      <div>
        <p class="text-sm font-semibold">Tidak ada jam kerja pada tanggal tersebut</p>
        <p class="mt-0.5">Tanggal <strong><?= tgl($tanggal_pilih) ?></strong> tidak diatur jadwal presensinya (hari libur atau di luar rentang jam kerja). Tidak ada kewajiban presensi kehadiran dan otomatisasi status Alfa dinonaktifkan untuk tanggal ini.</p>
      </div>
    </div>
  <?php endif; ?>
</div>


<!-- Ringkasan kehadiran pada tanggal terpilih -->
<div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
  <?php kartu_angka('Total Karyawan', $total_karyawan, 'text-stone-900', 'kartu text-center'); ?>
  <?php kartu_angka('Hadir Tepat Waktu', $total_hadir, 'text-ok-700', 'kartu text-center'); ?>
  <?php kartu_angka('Terlambat', $total_terlambat, 'text-warn-700', 'kartu text-center'); ?>
  <?php kartu_angka('Izin / Sakit', $total_izin_sakit, 'text-info-700', 'kartu text-center'); ?>
  <?php kartu_angka('Alfa (Tidak Hadir)', $total_alfa, 'text-danger-700', 'kartu text-center'); ?>
</div>


<div class="grid gap-6 lg:grid-cols-3">

  <!-- Form input / koreksi manual -->
  <div class="lg:col-span-1">
    <?php kartu_awal('Input / Koreksi Manual'); ?>
    <p class="keterangan -mt-2 mb-4">Gunakan form ini bila karyawan lupa absen atau perlu penyesuaian dinas luar / izin sakit.</p>

    <form method="POST" enctype="multipart/form-data" data-validasi novalidate><?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan_manual">

      <?php pilih('Karyawan', 'user_id', $opsi_karyawan, '', 'required'); ?>
      <?php isian('Tanggal', 'tanggal', 'date', $tanggal_pilih, 'required'); ?>

      <div class="grid grid-cols-2 gap-3">
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
        <p class="keterangan mt-1">PDF, JPG, PNG (maks 5 MB)</p>
      </div>

      <button class="btn btn-utama w-full">Simpan Data Absensi</button>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <!-- Tabel absensi karyawan -->
  <div class="lg:col-span-2">
    <?php kartu_awal('Daftar Kehadiran: ' . tgl($tanggal_pilih)); ?>

    <div class="mb-3">
      <input id="cari" class="kolom text-xs" placeholder="Cari nama atau NIP karyawan...">
    </div>

    <?php if (count($tampil_karyawan) === 0): ?>
      <?php kosong($status_pilih !== '' ? 'Tidak ada data karyawan dengan keterangan "' . e($status_pilih) . '" pada tanggal ini.' : 'Tidak ada data karyawan ditemukan.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Karyawan', 'Departemen', 'Jam Masuk', 'Jam Pulang', 'Status & Catatan', 'Aksi'), 'tabel-cari'); ?>
      <?php foreach ($tampil_karyawan as $row): ?>
        <tr>
          <td data-label="Karyawan">
            <span class="font-medium text-stone-800"><?= e($row['nama']) ?></span>
            <br><span class="keterangan">NIP: <?= e($row['nip']) ?></span>
          </td>
          <td data-label="Departemen" class="text-xs"><?= e($row['nama_departemen'] ?: '-') ?></td>
          <td data-label="Jam Masuk"><?= e($row['jam_masuk'] ? $row['jam_masuk'] . ' WIB' : '-') ?></td>
          <td data-label="Jam Pulang"><?= e($row['jam_pulang'] ? $row['jam_pulang'] . ' WIB' : '-') ?></td>
          <td data-label="Status & Catatan">
            <?php if ($row['status']): ?>
              <?= lencana($row['status']) ?>
              <?php if (!empty($row['catatan'])): ?>
                <p class="mt-0.5 text-xs text-stone-600"><?= e($row['catatan']) ?></p>
              <?php endif; ?>
              <?php if (!empty($row['bukti'])): ?>
                <a href="unduh.php?jenis=bukti_absensi&id=<?= e($row['id']) ?>&unduh=1" class="tautan mt-1 inline-block text-xs">Lihat surat / bukti</a>
              <?php endif; ?>
            <?php else: ?>
              <span class="lencana lencana-netral">Belum Absen</span>
            <?php endif; ?>
          </td>
          <td data-label="Aksi" class="whitespace-nowrap">
            <?php if (!empty($row['id'])): ?>
              <form method="POST" data-konfirmasi="Hapus data absensi ini?"><?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                <button class="btn btn-bahaya btn-kecil">Hapus</button>
              </form>
            <?php else: ?>
              <span class="keterangan">-</span>
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
