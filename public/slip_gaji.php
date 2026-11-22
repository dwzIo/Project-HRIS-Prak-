<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_login();

$user_id = $_SESSION['id'];
$is_admin = adalah_admin();

// Jika ada parameter detail/cetak slip tertentu
$detail_id = isset($_GET['id']) ? $_GET['id'] : '';
$detail_slip = null;

if ($detail_id !== '') {
    if ($is_admin) {
        $detail_slip = satu("
      SELECT p.*, u.nip, u.nama, u.jabatan, d.nama AS nama_departemen
      FROM payrolls p
      JOIN users u ON u.id = p.user_id
      LEFT JOIN departments d ON d.id = u.departemen_id
      WHERE p.id = ?
    ", array($detail_id));
    } else {
        $detail_slip = satu("
      SELECT p.*, u.nip, u.nama, u.jabatan, d.nama AS nama_departemen
      FROM payrolls p
      JOIN users u ON u.id = p.user_id
      LEFT JOIN departments d ON d.id = u.departemen_id
      WHERE p.id = ? AND p.user_id = ? AND p.status IN ('Diterbitkan', 'Dibayar')
    ", array($detail_id, $user_id));
    }
}

// Filter tahun untuk daftar riwayat
$tahun_pilih = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Daftar riwayat slip gaji untuk karyawan yang sedang login
$riwayat_slip = daftar("
  SELECT p.*, u.nip, u.nama, u.jabatan, d.nama AS nama_departemen
  FROM payrolls p
  JOIN users u ON u.id = p.user_id
  LEFT JOIN departments d ON d.id = u.departemen_id
  WHERE p.user_id = ? AND p.status IN ('Diterbitkan', 'Dibayar') AND p.periode_tahun = ?
  ORDER BY p.periode_bulan DESC
", array($user_id, $tahun_pilih));

// Ambil slip terbaru karyawan
$slip_terbaru = !empty($riwayat_slip) ? $riwayat_slip[0] : null;

top('Slip Gaji Karyawan');
?>

<!-- Mode Tampilan Cetak / Rincian Slip Gaji -->
<?php if ($detail_slip):
    $tot_penerimaan = (int)$detail_slip['gaji_pokok'] + (int)$detail_slip['tunjangan_transport'] + (int)$detail_slip['tunjangan_makan'] + (int)$detail_slip['bonus'];
    $tot_potongan = (int)$detail_slip['potongan_bpjs'] + (int)$detail_slip['potongan_pajak'] + (int)$detail_slip['potongan_lain'];
    $thp = (int)$detail_slip['total_gaji'];
?>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-2 sembunyi-cetak">
        <a href="<?= $is_admin ? 'admin_payroll.php' : 'slip_gaji.php' ?>" class="btn btn-garis btn-kecil">
            ← Kembali ke Daftar
        </a>
        <button onclick="window.print()" class="btn btn-utama btn-kecil">
            <?= ikon('cetak') ?><span class="ml-1.5">Cetak / Simpan PDF</span>
        </button>
    </div>

    <!-- Format Slip Gaji Resmi -->
    <div class="area-cetak mx-auto max-w-3xl rounded-xl border border-stone-200 bg-white p-6 md:p-8 shadow-sm">
        <!-- Kop Perusahaan -->
        <div class="flex items-center justify-between border-b-2 border-stone-800 pb-4">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-teal-800">MyHR ENTERPRISE</h2>
                <p class="text-xs text-stone-500">PT Human Resource Solusindo Indonesia</p>
                <p class="text-[11px] text-stone-400">Jl. Jenderal Sudirman Kav. 52-53, Jakarta Selatan</p>
            </div>
            <div class="text-right">
                <span class="inline-block rounded border border-stone-300 bg-stone-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-stone-800">
                    SLIP GAJI (CONFIDENTIAL)
                </span>
                <p class="mt-1 text-xs text-stone-500">Periode: <strong class="text-stone-800"><?= nama_bulan($detail_slip['periode_bulan']) . ' ' . $detail_slip['periode_tahun'] ?></strong></p>
            </div>
        </div>

        <!-- Data Karyawan -->
        <div class="my-5 grid grid-cols-2 gap-4 rounded-lg bg-stone-50 p-4 text-xs md:grid-cols-4">
            <div>
                <span class="text-stone-500 block">Nama Karyawan:</span>
                <span class="font-bold text-stone-900"><?= e($detail_slip['nama']) ?></span>
            </div>
            <div>
                <span class="text-stone-500 block">Nomor Induk (NIP):</span>
                <span class="font-bold text-stone-900"><?= e($detail_slip['nip']) ?></span>
            </div>
            <div>
                <span class="text-stone-500 block">Departemen:</span>
                <span class="font-semibold text-stone-900"><?= e($detail_slip['nama_departemen'] ?: 'Umum') ?></span>
            </div>
            <div>
                <span class="text-stone-500 block">Jabatan:</span>
                <span class="font-semibold text-stone-900"><?= e($detail_slip['jabatan'] ?: 'Staf') ?></span>
            </div>
        </div>

        <!-- Rincian Gaji 2 Kolom -->
        <div class="grid gap-6 md:grid-cols-2 text-xs">
            <!-- Kolom Penerimaan -->
            <div class="rounded-lg border border-stone-200 p-4">
                <h3 class="mb-3 font-bold uppercase tracking-wider text-teal-800 border-b border-stone-200 pb-1.5">A. Penerimaan (Earnings)</h3>
                <table class="w-full">
                    <tbody class="divide-y divide-stone-100">
                        <tr>
                            <td class="py-1.5 text-stone-600">Gaji Pokok</td>
                            <td class="py-1.5 text-right font-medium"><?= rupiah($detail_slip['gaji_pokok']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1.5 text-stone-600">Tunjangan Transport</td>
                            <td class="py-1.5 text-right font-medium"><?= rupiah($detail_slip['tunjangan_transport']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1.5 text-stone-600">Tunjangan Makan</td>
                            <td class="py-1.5 text-right font-medium"><?= rupiah($detail_slip['tunjangan_makan']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1.5 text-stone-600">Bonus / Insentif</td>
                            <td class="py-1.5 text-right font-medium"><?= rupiah($detail_slip['bonus']) ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-stone-300 font-bold text-stone-900">
                            <td class="pt-2">Total Penerimaan</td>
                            <td class="pt-2 text-right text-teal-800"><?= rupiah($tot_penerimaan) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Kolom Potongan -->
            <div class="rounded-lg border border-stone-200 p-4">
                <h3 class="mb-3 font-bold uppercase tracking-wider text-danger-800 border-b border-stone-200 pb-1.5">B. Potongan (Deductions)</h3>
                <table class="w-full">
                    <tbody class="divide-y divide-stone-100">
                        <tr>
                            <td class="py-1.5 text-stone-600">BPJS Ketenagakerjaan & Kes.</td>
                            <td class="py-1.5 text-right font-medium"><?= rupiah($detail_slip['potongan_bpjs']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1.5 text-stone-600">Pajak Penghasilan (PPh 21)</td>
                            <td class="py-1.5 text-right font-medium"><?= rupiah($detail_slip['potongan_pajak']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1.5 text-stone-600">Potongan Lainnya / Absensi</td>
                            <td class="py-1.5 text-right font-medium"><?= rupiah($detail_slip['potongan_lain']) ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-stone-300 font-bold text-stone-900">
                            <td class="pt-2">Total Potongan</td>
                            <td class="pt-2 text-right text-danger-700"><?= rupiah($tot_potongan) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Take Home Pay Box -->
        <div class="mt-6 flex flex-wrap items-center justify-between rounded-lg bg-teal-800 p-4 text-white">
            <div>
                <p class="text-xs uppercase tracking-wider text-teal-200">Gaji Bersih Diterima (Take Home Pay):</p>
                <p class="text-2xl font-bold tracking-tight"><?= rupiah($thp) ?></p>
            </div>
            <div class="text-right text-xs">
                <span class="inline-block rounded bg-teal-900 px-2.5 py-1 font-semibold text-emerald-300">
                    Status: <?= e($detail_slip['status']) ?>
                </span>
                <?php if ($detail_slip['tanggal_bayar']): ?>
                    <p class="mt-1 text-teal-200 text-[11px]">Dibayarkan: <?= tgl($detail_slip['tanggal_bayar']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($detail_slip['catatan'])): ?>
            <div class="mt-4 rounded-md bg-stone-50 p-2.5 text-xs text-stone-600 border border-stone-200">
                <strong>Catatan HR:</strong> <?= e($detail_slip['catatan']) ?>
            </div>
        <?php endif; ?>

        <!-- Tanda Tangan & Footer -->
        <div class="mt-8 flex justify-between border-t border-stone-200 pt-4 text-xs text-stone-500">
            <div>
                <p>Dicetak pada: <?= date('d/m/Y H:i') ?></p>
                <p class="italic text-[10px]">Dokumen ini dibuat otomatis secara digital oleh sistem HRIS MyHR.</p>
            </div>
            <div class="text-right">
                <p class="font-semibold text-stone-700">Departemen HR & Keuangan</p>
                <div class="h-10"></div>
                <p class="font-bold underline text-stone-800">MyHR Corporate System</p>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Tampilan Daftar Riwayat Slip Gaji Karyawan -->
    <?php if ($slip_terbaru): ?>
        <div class="kartu mb-6 border-l-4 border-l-teal-700 bg-gradient-to-r from-teal-50 to-white">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-teal-800">Slip Gaji Terakhir Anda</span>
                    <h2 class="text-2xl font-bold text-stone-900 mt-1"><?= rupiah($slip_terbaru['total_gaji']) ?></h2>
                    <p class="text-xs text-stone-500">Periode: <?= nama_bulan($slip_terbaru['periode_bulan']) . ' ' . $slip_terbaru['periode_tahun'] ?> • Status: <?= e($slip_terbaru['status']) ?></p>
                </div>
                <div>
                    <a href="?id=<?= e($slip_terbaru['id']) ?>" class="btn btn-utama">
                        <?= ikon('payroll') ?><span class="ml-2">Lihat & Cetak Rincian</span>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="kartu mb-4 flex flex-wrap items-center justify-between gap-3 py-3">
        <h2 class="text-base font-semibold">Riwayat Slip Gaji Tahun <?= $tahun_pilih ?></h2>
        <form method="GET" class="flex items-center gap-2">
            <select name="tahun" class="rounded-md border border-stone-300 bg-white px-2.5 py-1.5 text-xs">
                <?php for ($t = date('Y'); $t >= date('Y') - 3; $t--): ?>
                    <option value="<?= $t ?>" <?= $t === $tahun_pilih ? 'selected' : '' ?>><?= $t ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-garis btn-kecil">Pilih Tahun</button>
        </form>
    </div>

    <?php kartu_awal('Daftar Pembayaran Gaji'); ?>
    <?php if (count($riwayat_slip) === 0): ?>
        <?php kosong('Belum ada slip gaji yang diterbitkan untuk Anda di tahun ' . $tahun_pilih . '.'); ?>
    <?php else: ?>
        <?php tabel_awal(array('Periode Gaji', 'Total Penerimaan', 'Total Potongan', 'Take Home Pay', 'Status', 'Aksi')); ?>
        <?php foreach ($riwayat_slip as $s):
            $tot_p = (int)$s['gaji_pokok'] + (int)$s['tunjangan_transport'] + (int)$s['tunjangan_makan'] + (int)$s['bonus'];
            $tot_d = (int)$s['potongan_bpjs'] + (int)$s['potongan_pajak'] + (int)$s['potongan_lain'];
        ?>
            <tr>
                <td data-label="Periode Gaji" class="font-semibold text-stone-800">
                    <?= nama_bulan($s['periode_bulan']) . ' ' . $s['periode_tahun'] ?>
                </td>
                <td data-label="Total Penerimaan" class="text-xs text-stone-700">
                    <?= rupiah($tot_p) ?>
                </td>
                <td data-label="Total Potongan" class="text-xs text-danger-700">
                    -<?= rupiah($tot_d) ?>
                </td>
                <td data-label="Take Home Pay" class="font-bold text-teal-800">
                    <?= rupiah($s['total_gaji']) ?>
                </td>
                <td data-label="Status">
                    <?= lencana($s['status']) ?>
                    <?php if ($s['tanggal_bayar']): ?>
                        <br><span class="text-[10px] text-stone-400">Bayar: <?= tgl($s['tanggal_bayar']) ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="Aksi" class="whitespace-nowrap">
                    <a href="?id=<?= e($s['id']) ?>" class="btn btn-utama btn-kecil">
                        Lihat Slip
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php tabel_akhir(); ?>
    <?php endif; ?>
    <?php kartu_akhir(); ?>
<?php endif; ?>

<?php bottom(); ?>