<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_karyawan();

// Pastikan sinkron ke WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

$id = $_SESSION['id'];
$hari_ini = date('Y-m-d');
$waktu_sekarang = date('H:i:s');
$jam_menit_sekarang = date('H:i');

// Ambil Jam Kerja Dinamis yang Telah Ditentukan Admin (mendukung rentang tanggal maupun default reguler)
$jk_aktif = ambil_jam_kerja($hari_ini);
$jam_masuk_buka  = $jk_aktif['jam_masuk_buka'];  // Jam mulai presensi
$jam_masuk_tepat = $jk_aktif['jam_masuk_tepat']; // Batas jam reguler tepat waktu
$jam_pulang_buka = $jk_aktif['jam_pulang_buka']; // Jam pulang kerja kantor

// Jalankan otomatisasi penentuan Alfa jika karyawan belum absen hingga jam pulang
sinkron_alfa_otomatis();

// Cek absensi hari ini setelah disinkronisasi
$absen_hari_ini = satu("SELECT * FROM attendances WHERE user_id=? AND tanggal=?", array($id, $hari_ini));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = isi('aksi');

    if (!$jk_aktif['ada_jadwal'] && in_array($aksi, array('masuk', 'pulang'))) {
        pesan('Tidak ada jam kerja pada tanggal tersebut (Hari Libur).', 'gagal');
        pindah('absensi.php');
    }

    if ($aksi === 'masuk') {
        if ($absen_hari_ini) {
            pesan('Data kehadiran Anda hari ini sudah tercatat.', 'gagal');
        } elseif ($jam_menit_sekarang < $jam_masuk_buka) {
            pesan("Presensi masuk belum dibuka. Gerbang kehadiran dibuka pukul $jam_masuk_buka WIB.", 'gagal');
        } elseif ($jam_menit_sekarang >= $jam_pulang_buka) {
            // Lewat jam pulang belum presensi, otomatis dicatat Alfa
            jalankan(
                "INSERT INTO attendances (id, user_id, tanggal, jam_masuk, status, catatan) VALUES (?, ?, ?, ?, 'Alfa', ?)",
                array(buat_id(), $id, $hari_ini, $waktu_sekarang, "Dinyatakan Alfa: Jam kerja hari ini telah selesai (lewat jam pulang $jam_pulang_buka WIB)")
            );
            pesan("Jam kerja kantor hari ini telah selesai (lewat jam pulang $jam_pulang_buka WIB). Anda dinyatakan ALFA (Tidak Hadir).", 'gagal');
        } elseif ($jam_menit_sekarang > $jam_masuk_tepat) {
            // Lewat jam reguler (misal lewat 08:30) sampai sebelum jam pulang = Terlambat
            jalankan(
                "INSERT INTO attendances (id, user_id, tanggal, jam_masuk, status, catatan) VALUES (?, ?, ?, ?, 'Terlambat', ?)",
                array(buat_id(), $id, $hari_ini, $waktu_sekarang, "Terlambat masuk kerja pada pukul $waktu_sekarang WIB (melewati jam reguler $jam_masuk_tepat WIB)")
            );
            pesan("Absen masuk berhasil dicatat. Status: TERLAMBAT (pukul $waktu_sekarang WIB).");
        } else {
            // Jam reguler tepat waktu
            jalankan(
                "INSERT INTO attendances (id, user_id, tanggal, jam_masuk, status, catatan) VALUES (?, ?, ?, ?, 'Hadir', ?)",
                array(buat_id(), $id, $hari_ini, $waktu_sekarang, "Hadir tepat waktu ($waktu_sekarang WIB)")
            );
            pesan("Berhasil absen masuk pada pukul $waktu_sekarang WIB (Hadir tepat waktu).");
        }
    } elseif ($aksi === 'pulang') {
        if (!$absen_hari_ini) {
            pesan('Anda belum melakukan absen masuk hari ini.', 'gagal');
        } elseif ($absen_hari_ini['status'] === 'Alfa') {
            pesan('Status kehadiran Anda adalah Alfa. Tidak dapat melakukan absen pulang.', 'gagal');
        } elseif ($absen_hari_ini['status'] === 'Izin' || $absen_hari_ini['status'] === 'Sakit') {
            pesan('Anda sedang izin/sakit. Tidak perlu melakukan absen pulang.', 'gagal');
        } elseif (!empty($absen_hari_ini['jam_pulang'])) {
            pesan('Anda sudah melakukan absen pulang sebelumnya.', 'gagal');
        } elseif ($jam_menit_sekarang < $jam_pulang_buka) {
            // Dilarang absen pulang sebelum jam kerja selesai
            pesan("Belum jam pulang kerja! Absen pulang hanya dapat dilakukan mulai pukul $jam_pulang_buka WIB (Waktu saat ini: $waktu_sekarang WIB).", 'gagal');
        } else {
            jalankan(
                "UPDATE attendances SET jam_pulang=? WHERE id=?",
                array($waktu_sekarang, $absen_hari_ini['id'])
            );
            pesan("Berhasil absen pulang pada pukul $waktu_sekarang WIB. Selamat beristirahat!");
        }
    } elseif ($aksi === 'izin_sakit') {
        $status_pilih = (isi('status') === 'Izin') ? 'Izin' : 'Sakit';
        $alasan = trim(isi('alasan'));

        if ($alasan === '') {
            pesan('Tuliskan alasan izin atau sakit Anda.', 'gagal');
        } elseif ($absen_hari_ini) {
            pesan('Data kehadiran hari ini sudah tercatat.', 'gagal');
        } else {
            $nama_file = null;
            $error_upload = '';

            // Upload file bukti jika dilampirkan
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
                jalankan(
                    "INSERT INTO attendances (id, user_id, tanggal, status, catatan, bukti) VALUES (?, ?, ?, ?, ?, ?)",
                    array(buat_id(), $id, $hari_ini, $status_pilih, $alasan, $nama_file)
                );
                pesan("Pemberitahuan $status_pilih hari ini berhasil disimpan beserta berkas bukti.");
            }
        }
    }
    pindah('absensi.php');
}

// Filter riwayat absensi per bulan dan tahun
$bulan_pilih = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun_pilih = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$format_periode = sprintf('%04d-%02d', $tahun_pilih, $bulan_pilih);

// Statistik bulan terpilih
$total_hadir = angka("SELECT COUNT(*) FROM attendances WHERE user_id=? AND strftime('%Y-%m', tanggal)=? AND status='Hadir'", array($id, $format_periode));
$total_terlambat = angka("SELECT COUNT(*) FROM attendances WHERE user_id=? AND strftime('%Y-%m', tanggal)=? AND status='Terlambat'", array($id, $format_periode));
$total_izin = angka("SELECT COUNT(*) FROM attendances WHERE user_id=? AND strftime('%Y-%m', tanggal)=? AND status='Izin'", array($id, $format_periode));
$total_sakit = angka("SELECT COUNT(*) FROM attendances WHERE user_id=? AND strftime('%Y-%m', tanggal)=? AND status='Sakit'", array($id, $format_periode));
$total_alfa = angka("SELECT COUNT(*) FROM attendances WHERE user_id=? AND strftime('%Y-%m', tanggal)=? AND status='Alfa'", array($id, $format_periode));

// Riwayat absensi
$riwayat = daftar(
    "SELECT * FROM attendances WHERE user_id=? AND strftime('%Y-%m', tanggal)=? ORDER BY tanggal DESC",
    array($id, $format_periode)
);

// Evaluasi tombol presensi saat ini
$terlambat_sekarang = ($jam_menit_sekarang > $jam_masuk_tepat && $jam_menit_sekarang < $jam_pulang_buka);
$sudah_lewat_pulang = ($jam_menit_sekarang >= $jam_pulang_buka);
$boleh_absen_pulang = ($absen_hari_ini && empty($absen_hari_ini['jam_pulang']) && in_array($absen_hari_ini['status'], array('Hadir', 'Terlambat')) && $jam_menit_sekarang >= $jam_pulang_buka);

top('Absensi Kehadiran');
?>

<?php if (!$jk_aktif['ada_jadwal']): ?>
    <!-- Pengumuman Tidak Ada Jam Kerja -->
    <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 flex items-start gap-3 shadow-sm">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white font-bold text-sm">!</span>
        <div>
            <h3 class="font-bold text-amber-950 text-sm">Pengumuman: Tidak ada jam kerja pada tanggal tersebut</h3>
            <p class="text-amber-800 mt-0.5">Hari ini (<strong><?= tgl($hari_ini) ?></strong>) libur atau belum ada jadwal presensi yang disetting oleh Admin. Anda tidak perlu melakukan presensi kehadiran.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Kartu Absensi Hari Ini -->
<div class="mb-6 grid gap-5 lg:grid-cols-3">
    <div class="lg:col-span-1">
        <?php kartu_awal('Absensi Hari Ini'); ?>
        <div class="text-center py-2">
            <p class="text-xs uppercase tracking-wider text-stone-500 font-semibold"><?= tgl($hari_ini) ?></p>
            <p id="jam-digital" class="my-2 text-3xl font-bold tracking-tight text-teal-700 font-mono"><?= date('H:i:s') ?></p>
            <span class="inline-block rounded bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600 mb-3">Waktu Indonesia Barat (WIB)</span>

            <?php if (!$jk_aktif['ada_jadwal']): ?>
                <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-4 text-center text-xs text-amber-900 my-2">
                    <span class="inline-block text-2xl mb-1">🏖️</span>
                    <p class="font-bold text-amber-950">Hari Libur / Tidak Ada Jam Kerja</p>
                    <p class="text-[11px] text-amber-800 mt-1">Tidak ada jadwal presensi yang aktif untuk hari ini.</p>
                </div>
            <?php elseif (!$absen_hari_ini): ?>
                <!-- Jadwal Jam Kerja yang Ditetapkan Admin -->
                <div class="mb-3 rounded-md border border-stone-200 bg-stone-50 p-2.5 text-left text-xs space-y-1">
                    <div class="flex items-center justify-between border-b border-stone-200 pb-1 mb-1">
                        <span class="font-semibold text-stone-700"><?= e($jk_aktif['nama']) ?></span>
                        <?php if ($jk_aktif['sumber'] === 'rentang'): ?>
                            <span class="rounded bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold text-teal-800">Periode: <?= tgl($jk_aktif['tanggal_mulai']) ?> - <?= tgl($jk_aktif['tanggal_selesai']) ?></span>
                        <?php else: ?>
                            <span class="rounded bg-stone-200 px-1.5 py-0.5 text-[10px] font-semibold text-stone-700">Reguler</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-between text-emerald-800">
                        <span>Jam Reguler (Tepat Waktu):</span>
                        <span class="font-semibold"><?= e($jam_masuk_buka) ?> - <?= e($jam_masuk_tepat) ?> WIB</span>
                    </div>
                    <div class="flex justify-between text-amber-800">
                        <span>Lewat Jam Reguler (Terlambat):</span>
                        <span class="font-semibold">> <?= e($jam_masuk_tepat) ?> s/d <?= e($jam_pulang_buka) ?> WIB</span>
                    </div>
                    <div class="flex justify-between text-danger-800">
                        <span>Tidak Presensi (Otomatis Alfa):</span>
                        <span class="font-semibold">≥ <?= e($jam_pulang_buka) ?> WIB</span>
                    </div>
                </div>

                <?php if ($terlambat_sekarang): ?>
                    <div class="mb-3 rounded-md bg-amber-50 p-2.5 text-xs text-amber-800 border border-amber-200 text-left">
                        Waktu telah melewati jam reguler (<strong><?= e($jam_masuk_tepat) ?> WIB</strong>). Anda akan tercatat dengan status <strong>Terlambat</strong>.
                    </div>
                    <form method="POST">
                        <input type="hidden" name="aksi" value="masuk">
                        <button class="btn btn-utama w-full py-2.5 text-sm font-semibold shadow-sm bg-amber-600 hover:bg-amber-700">
                            <?= ikon('absensi') ?><span class="ml-2">Absen Masuk (Terlambat)</span>
                        </button>
                    </form>
                <?php elseif ($jam_menit_sekarang < $jam_masuk_buka): ?>
                    <div class="rounded-md bg-stone-100 p-3 text-xs text-stone-600">
                        Presensi masuk dibuka pukul <strong><?= e($jam_masuk_buka) ?> WIB</strong>.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="aksi" value="masuk">
                        <button class="btn btn-utama w-full py-2.5 text-base font-semibold shadow-sm">
                            <?= ikon('absensi') ?><span class="ml-2">Absen Masuk Sekarang</span>
                        </button>
                    </form>
                <?php endif; ?>

            <?php elseif ($absen_hari_ini['status'] === 'Alfa'): ?>
                <div class="rounded-lg bg-danger-50 p-4 mt-2 text-left border border-danger-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-danger-700 font-semibold">Status Kehadiran Hari Ini:</span>
                        <?= lencana($absen_hari_ini['status']) ?>
                    </div>
                    <p class="mt-2 text-xs text-danger-800 font-medium"><?= e($absen_hari_ini['catatan']) ?></p>
                    <p class="mt-1 text-[11px] text-danger-600">Anda tidak melakukan presensi sampai jam pulang kantor (<?= e($jam_pulang_buka) ?> WIB).</p>
                </div>
            <?php elseif ($absen_hari_ini['status'] === 'Izin' || $absen_hari_ini['status'] === 'Sakit'): ?>
                <div class="rounded-lg bg-stone-100 p-4 mt-2 text-left border border-stone-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-stone-500 font-semibold">Keterangan Hari Ini:</span>
                        <?= lencana($absen_hari_ini['status']) ?>
                    </div>
                    <p class="mt-2 text-xs text-stone-700 font-medium"><?= e($absen_hari_ini['catatan']) ?></p>
                    <?php if (!empty($absen_hari_ini['bukti'])): ?>
                        <a href="unduh.php?jenis=bukti_absensi&id=<?= e($absen_hari_ini['id']) ?>&unduh=1" class="mt-2.5 inline-flex items-center gap-1 text-xs text-teal-700 underline font-medium">
                            Lihat Berkas Bukti Terlampir →
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Sudah Absen Masuk (Hadir / Terlambat) -->
                <div class="rounded-lg border border-stone-200 bg-stone-50 p-3 mb-3 text-left text-sm">
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">Jam Masuk:</span>
                        <span class="font-semibold text-teal-800"><?= e($absen_hari_ini['jam_masuk'] ?: '-') ?> WIB</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">Jam Pulang:</span>
                        <span class="font-semibold text-stone-800"><?= $absen_hari_ini['jam_pulang'] ? e($absen_hari_ini['jam_pulang']) . ' WIB' : 'Belum absen pulang' ?></span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-stone-500">Status:</span>
                        <span><?= lencana($absen_hari_ini['status']) ?></span>
                    </div>
                </div>

                <?php if (empty($absen_hari_ini['jam_pulang'])): ?>
                    <?php if ($boleh_absen_pulang): ?>
                        <form method="POST">
                            <input type="hidden" name="aksi" value="pulang">
                            <button class="btn btn-sukses w-full py-2.5 text-base font-semibold shadow-sm">
                                <?= ikon('absensi') ?><span class="ml-2">Absen Pulang Sekarang</span>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="rounded-md bg-stone-100 p-3 text-xs text-stone-600 border border-stone-200 text-left">
                            <span class="font-semibold text-stone-800">Belum jam pulang kantor.</span>
                            <br>Absen pulang baru dibuka mulai pukul <strong><?= e($jam_pulang_buka) ?> WIB</strong>.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="rounded-md bg-emerald-50 p-2.5 text-xs text-emerald-800 border border-emerald-200">
                        ✓ Kehadiran hari ini telah lengkap (Masuk & Pulang).
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php kartu_akhir(); ?>

        <?php if ($jk_aktif['ada_jadwal'] && !$absen_hari_ini): ?>
            <?php kartu_awal('Tidak Bisa Hadir / Izin Sakit'); ?>
            <form method="POST" enctype="multipart/form-data" data-validasi novalidate>
                <input type="hidden" name="aksi" value="izin_sakit">
                <?php pilih('Kategori', 'status', array('Sakit' => 'Sakit (Dengan Bukti / Surat Dokter)', 'Izin' => 'Izin Tidak Masuk')); ?>

                <div class="mb-3">
                    <label class="label">Alasan / Keterangan</label>
                    <textarea name="alasan" rows="2" class="kolom text-sm" placeholder="Contoh: Mengalami demam tinggi dan disarankan dokter istirahat" required></textarea>
                </div>

                <div class="mb-4">
                    <label class="label">Upload File Bukti (Surat Dokter / Resep / Surat Izin)</label>
                    <input type="file" name="bukti" accept=".pdf,.jpg,.jpeg,.png" class="kolom text-xs file:mr-2 file:rounded file:border-0 file:bg-stone-100 file:px-2 file:py-1 file:text-xs">
                    <p class="mt-1 text-[11px] text-stone-400">PDF, JPG, PNG (Maks 5 MB)</p>
                </div>

                <button class="btn btn-utama w-full">Kirim Keterangan Kehadiran</button>
            </form>
            <?php kartu_akhir(); ?>
        <?php endif; ?>
    </div>

    <!-- Ringkasan Statistik Bulanan & Riwayat -->
    <div class="lg:col-span-2 space-y-5">
        <div class="kartu">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 pb-3">
                <h2 class="text-base font-semibold">Rekap Kehadiran: <?= nama_bulan($bulan_pilih) . ' ' . $tahun_pilih ?></h2>
                <form method="GET" class="flex items-center gap-2">
                    <select name="bulan" class="rounded-md border border-stone-300 bg-white px-2 py-1 text-xs focus:border-teal-600 focus:outline-none">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === $bulan_pilih ? 'selected' : '' ?>><?= nama_bulan($m) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="tahun" class="rounded-md border border-stone-300 bg-white px-2 py-1 text-xs focus:border-teal-600 focus:outline-none">
                        <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?= $y ?>" <?= $y === $tahun_pilih ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <button class="btn btn-utama btn-kecil">Tampilkan</button>
                </form>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5 text-center">
                <div class="rounded-lg bg-stone-50 p-3 border border-stone-200">
                    <p class="text-xs text-stone-500">Tepat Waktu</p>
                    <p class="text-2xl font-bold text-emerald-700"><?= $total_hadir ?></p>
                </div>
                <div class="rounded-lg bg-stone-50 p-3 border border-stone-200">
                    <p class="text-xs text-stone-500">Terlambat</p>
                    <p class="text-2xl font-bold text-amber-600"><?= $total_terlambat ?></p>
                </div>
                <div class="rounded-lg bg-stone-50 p-3 border border-stone-200">
                    <p class="text-xs text-stone-500">Izin</p>
                    <p class="text-2xl font-bold text-sky-600"><?= $total_izin ?></p>
                </div>
                <div class="rounded-lg bg-stone-50 p-3 border border-stone-200">
                    <p class="text-xs text-stone-500">Sakit</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $total_sakit ?></p>
                </div>
                <div class="rounded-lg bg-stone-50 p-3 border border-stone-200">
                    <p class="text-xs text-stone-500">Alfa</p>
                    <p class="text-2xl font-bold text-danger-700"><?= $total_alfa ?></p>
                </div>
            </div>
        </div>

        <!-- Riwayat Tabel -->
        <?php kartu_awal('Riwayat Kehadiran Bulan Ini'); ?>
        <?php if (count($riwayat) === 0): ?>
            <?php kosong('Belum ada riwayat presensi untuk periode bulan ini.'); ?>
        <?php else: ?>
            <?php tabel_awal(array('Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Catatan & Bukti')); ?>
            <?php foreach ($riwayat as $b): ?>
                <tr>
                    <td data-label="Tanggal" class="font-medium text-stone-800"><?= tgl($b['tanggal']) ?></td>
                    <td data-label="Jam Masuk"><?= $b['jam_masuk'] ? e($b['jam_masuk']) . ' WIB' : '-' ?></td>
                    <td data-label="Jam Pulang"><?= $b['jam_pulang'] ? e($b['jam_pulang']) . ' WIB' : '-' ?></td>
                    <td data-label="Status"><?= lencana($b['status']) ?></td>
                    <td data-label="Catatan & Bukti">
                        <span class="text-stone-600"><?= e($b['catatan'] ?: '-') ?></span>
                        <?php if (!empty($b['bukti'])): ?>
                            <a href="unduh.php?jenis=bukti_absensi&id=<?= e($b['id']) ?>&unduh=1" class="ml-2 inline-flex items-center gap-1 text-xs font-semibold text-teal-700 underline">
                                Lihat Bukti
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php tabel_akhir(); ?>
        <?php endif; ?>
        <?php kartu_akhir(); ?>
    </div>
</div>

<script>
    // Jam digital live waktu lokal WIB
    function perbaruiJam() {
        const el = document.getElementById('jam-digital');
        if (el) {
            const d = new Date();
            el.textContent = d.toLocaleTimeString('id-ID', {
                hour12: false
            });
        }
    }
    setInterval(perbaruiJam, 1000);
</script>

<?php bottom(); ?>