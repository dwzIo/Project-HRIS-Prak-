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

        // Reset Jam Kerja Kembali ke Standar Default (08:00 - 08:30 WIB | Pulang 17:00 WIB)
    } elseif ($aksi === 'reset_jam_standar') {
        simpan_pengaturan('jam_masuk_buka', '08:00');
        simpan_pengaturan('jam_masuk_tepat', '08:30');
        simpan_pengaturan('jam_pulang_buka', '17:00');
        jalankan("DELETE FROM work_schedules");
        pesan('Jam kerja berhasil dikembalikan ke standar awal: Masuk 08:00 - 08:30 WIB (Pulang 17:00 WIB).');

        // Hapus Jadwal Rentang Khusus
    } elseif ($aksi === 'hapus_jadwal_rentang') {
        $id_jadwal = isi('id');
        jalankan("DELETE FROM work_schedules WHERE id=?", array($id_jadwal));
        pesan('Rentang tanggal khusus dihapus. Jam kerja kembali ke standar default.');

        // Input / Koreksi Manual Absensi
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
                        "INSERT INTO attendances (id, user_id, tanggal, jam_masuk, jam_pulang, status, catatan, bukti) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        array(buat_id(), $user_id, $tgl_absen, $jam_masuk ?: null, $jam_pulang ?: null, $status, $catatan, $nama_file)
                    );
                    pesan('Data absensi manual berhasil ditambahkan.');
                }
            }
        }

        // Hapus Catatan Absensi
    } elseif ($aksi === 'hapus') {
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
$opsi_karyawan = array('' => '-- Pilih Karyawan --');
$list_user = daftar("SELECT id, nip, nama FROM users WHERE role='karyawan' ORDER BY nama ASC");
foreach ($list_user as $u) {
    $opsi_karyawan[$u['id']] = $u['nip'] . ' - ' . $u['nama'];
}

top('Rekap & Monitoring Absensi Karyawan');
?>

<!-- Form Tunggal Pengaturan Jam Kerja & Jadwal Presensi -->
<div class="kartu border-l-4 border-l-teal-600 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-200 pb-3">
        <div>
            <h2 class="text-base font-semibold text-stone-800">Atur Jam Kerja Kantor & Rentang Tanggal</h2>
            <p class="text-xs text-stone-500">Atur batas presensi harian dan tentukan rentang tanggal berlakunya sekaligus dalam satu form praktis.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800 border border-teal-200">
                Zona Waktu: WIB (Asia/Jakarta)
            </span>
            <form method="POST" data-konfirmasi="Kembalikan jam kerja ke standar awal (08:00 - 08:30 WIB | Pulang 17:00 WIB)?" class="inline">
                <input type="hidden" name="aksi" value="reset_jam_standar">
                <button type="submit" class="btn btn-garis btn-kecil text-stone-600 hover:text-stone-800" title="Kembalikan ke standar bawaan">
                    Reset Default Standar
                </button>
            </form>
        </div>
    </div>

    <form method="POST" class="mt-4" data-validasi novalidate>
        <input type="hidden" name="aksi" value="simpan_jam_kerja">

        <!-- Kolom Jam Kerja -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label">1. Jam Mulai Presensi</label>
                <input type="time" name="jam_masuk_buka" value="<?= e($val_masuk_buka) ?>" class="kolom text-sm" required>
                <p class="mt-1 text-[11px] text-stone-400">Presensi sistem mulai dibuka</p>
            </div>
            <div>
                <label class="label">2. Batas Tepat Waktu (Reguler)</label>
                <input type="time" name="jam_masuk_tepat" value="<?= e($val_masuk_tepat) ?>" class="kolom text-sm" required>
                <p class="mt-1 text-[11px] font-medium text-amber-700">Lewat jam ini = Dihitung Terlambat</p>
            </div>
            <div>
                <label class="label">3. Jam Pulang Kerja</label>
                <input type="time" name="jam_pulang_buka" value="<?= e($val_pulang_buka) ?>" class="kolom text-sm" required>
                <p class="mt-1 text-[11px] font-medium text-danger-700">Tidak absen hingga jam ini = Otomatis Alfa</p>
            </div>
        </div>

        <!-- Kolom Rentang Tanggal & Tombol Simpan -->
        <div class="mt-4 pt-4 border-t border-stone-100 flex flex-wrap items-end justify-between gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1">
                <div>
                    <label class="label">Tanggal Mulai Berlaku</label>
                    <input type="date" name="tanggal_mulai" value="<?= e($val_tgl_mulai) ?>" class="kolom text-sm">
                    <p class="mt-1 text-[11px] text-stone-400">Tanggal awal jam kerja ini mulai aktif</p>
                </div>
                <div>
                    <label class="label">Tanggal Selesai Berlaku</label>
                    <input type="date" name="tanggal_selesai" value="<?= e($val_tgl_selesai) ?>" class="kolom text-sm">
                    <p class="mt-1 text-[11px] text-stone-400">Pilih rentang (misal 1 bulan / 1 tahun)</p>
                </div>
            </div>
            <div class="w-full sm:w-auto">
                <button type="submit" class="btn btn-utama w-full sm:w-auto h-[61px] px-6 flex items-center justify-center gap-2">
                    <?= ikon('check') ?> Simpan Jam Kerja
                </button>
                
            </div>
        </div>
    </form>

    <?php if ($jadwal_aktif_rentang): ?>
        <div class="mt-4 pt-3 border-t border-stone-100 flex items-center justify-between text-xs text-stone-600 bg-stone-50 p-2.5 rounded-md">
            <span>
                Rentang tersimpan: <strong><?= tgl($jadwal_aktif_rentang['tanggal_mulai']) ?> s/d <?= tgl($jadwal_aktif_rentang['tanggal_selesai']) ?></strong>
                (Masuk: <?= e($jadwal_aktif_rentang['jam_masuk_buka']) ?> - <?= e($jadwal_aktif_rentang['jam_masuk_tepat']) ?> WIB, Pulang: <?= e($jadwal_aktif_rentang['jam_pulang_buka']) ?> WIB)
            </span>
            <form method="POST" data-konfirmasi="Hapus rentang ini dan gunakan jam reguler default?" class="inline">
                <input type="hidden" name="aksi" value="hapus_jadwal_rentang">
                <input type="hidden" name="id" value="<?= e($jadwal_aktif_rentang['id']) ?>">
                <button class="text-danger-700 underline hover:text-danger-800 font-medium">Hapus Rentang</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Filter Bilah Monitoring (Tanggal, Departemen, dan Keterangan Kehadiran) -->
<div class="kartu mb-6">
    <!-- Baris Judul & Status Jadwal -->
    <div class="border-b border-stone-100 pb-3">
        <h2 class="text-base font-semibold">Monitoring Kehadiran: <?= tgl($tanggal_pilih) ?></h2>
        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
            <span class="text-stone-500">Status jadwal tanggal ini:</span>
            <?php if ($jk_aktif_pilih['ada_jadwal']): ?>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-2.5 py-0.5 font-semibold text-teal-800 border border-teal-200">
                    <?= e($jk_aktif_pilih['nama']) ?>: <strong><?= e($jk_aktif_pilih['jam_masuk_buka']) ?> - <?= e($jk_aktif_pilih['jam_masuk_tepat']) ?> WIB</strong> (Pulang <?= e($jk_aktif_pilih['jam_pulang_buka']) ?> WIB)
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 font-semibold text-amber-900 border border-amber-300">
                    <?= e($jk_aktif_pilih['pesan']) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Baris Filter: Selalu konsisten di bawah judul untuk semua kondisi -->
    <div class="pt-3">
        <form method="GET" class="flex flex-wrap items-center gap-2.5">
            <div>
                <input type="date" name="tanggal" value="<?= e($tanggal_pilih) ?>" class="rounded-md border border-stone-300 bg-white px-2.5 py-1.5 text-xs focus:border-teal-600 focus:outline-none" title="Pilih Tanggal">
            </div>
            <div>
                <select name="departemen" class="rounded-md border border-stone-300 bg-white px-2.5 py-1.5 text-xs focus:border-teal-600 focus:outline-none" title="Pilih Departemen">
                    <option value="">Semua Departemen</option>
                    <?php foreach ($daftar_dept as $dp): ?>
                        <option value="<?= e($dp['id']) ?>" <?= $dept_pilih === $dp['id'] ? 'selected' : '' ?>><?= e($dp['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
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
            <?php if ($dept_pilih !== '' || $status_pilih !== '' || $tanggal_pilih !== date('Y-m-d')): ?>
                <a href="admin_absensi.php" class="btn btn-garis btn-kecil">Reset Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$jk_aktif_pilih['ada_jadwal']): ?>
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3.5 text-xs text-amber-900 flex items-start gap-3 shadow-sm">
            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white font-bold text-xs mt-0.5">!</span>
            <div>
                <p class="font-bold text-amber-950 text-xs sm:text-sm">Pengumuman: Tidak ada jam kerja pada tanggal tersebut</p>
                <p class="text-amber-800 mt-0.5">Tanggal <strong><?= tgl($tanggal_pilih) ?></strong> tidak disetting jadwal presensi (hari libur atau di luar rentang jam kerja). Tidak ada kewajiban presensi kehadiran dan otomatisasi status Alfa dinonaktifkan untuk tanggal ini.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Kartu Ringkasan Kehadiran Hari Itu -->
<div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
    <div class="kartu text-center">
        <p class="text-xs text-stone-500">Total Karyawan</p>
        <p class="text-2xl font-bold text-stone-800"><?= $total_karyawan ?></p>
    </div>
    <div class="kartu text-center">
        <p class="text-xs text-stone-500">Hadir Tepat Waktu</p>
        <p class="text-2xl font-bold text-teal-700"><?= $total_hadir ?></p>
    </div>
    <div class="kartu text-center">
        <p class="text-xs text-stone-500">Terlambat</p>
        <p class="text-2xl font-bold text-amber-600"><?= $total_terlambat ?></p>
    </div>
    <div class="kartu text-center">
        <p class="text-xs text-stone-500">Izin / Sakit</p>
        <p class="text-2xl font-bold text-sky-700"><?= $total_izin_sakit ?></p>
    </div>
    <div class="kartu text-center">
        <p class="text-xs text-stone-500">Alfa (Tidak Hadir)</p>
        <p class="text-2xl font-bold text-danger-700"><?= $total_alfa ?></p>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Form Input / Edit Manual -->
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

    <!-- Tabel Absensi Karyawan -->
    <div class="lg:col-span-2">
        <?php kartu_awal('Daftar Kehadiran: ' . tgl($tanggal_pilih)); ?>
        <div class="mb-3">
            <input id="cari" class="kolom text-xs" placeholder="Cari nama atau NIP karyawan...">
        </div>
        <?php if (count($tampil_karyawan) === 0): ?>
            <?php kosong($status_pilih !== '' ? 'Tidak ada data karyawan dengan keterangan "' . e($status_pilih) . '" pada tanggal ini.' : 'Tidak ada data karyawan ditemukan.'); ?>
        <?php else: ?>
            <?php tabel_awal(array('Karyawan', 'Departemen', 'Jam Masuk', 'Jam Pulang', 'Status & Catatan', 'Aksi'), 'tabel-cari'); ?>
            <?php foreach ($tampil_karyawan as $row):
                $status_tampil = $row['status'] ?: 'Belum Absen';
            ?>
                <tr>
                    <td data-label="Karyawan">
                        <span class="font-medium text-stone-800"><?= e($row['nama']) ?></span>
                        <br><span class="text-xs text-stone-400">NIP: <?= e($row['nip']) ?></span>
                    </td>
                    <td data-label="Departemen" class="text-xs"><?= e($row['nama_departemen'] ?: '-') ?></td>
                    <td data-label="Jam Masuk"><?= e($row['jam_masuk'] ? $row['jam_masuk'] . ' WIB' : '-') ?></td>
                    <td data-label="Jam Pulang"><?= e($row['jam_pulang'] ? $row['jam_pulang'] . ' WIB' : '-') ?></td>
                    <td data-label="Status & Catatan">
                        <?php if ($row['status']): ?>
                            <?= lencana($row['status']) ?>
                            <?php if (!empty($row['catatan'])): ?>
                                <p class="text-[11px] text-stone-600 mt-0.5"><?= e($row['catatan']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($row['bukti'])): ?>
                                <a href="unduh.php?jenis=bukti_absensi&id=<?= e($row['id']) ?>&unduh=1" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-teal-700 underline">
                                    Lihat Surat / Bukti
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="inline-block rounded px-2 py-0.5 text-xs font-medium bg-stone-100 text-stone-600">Belum Absen</span>
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