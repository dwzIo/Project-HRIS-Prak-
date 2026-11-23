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

<?php if ($detail_slip):
  // Mode tampilan rincian / cetak slip gaji
  $tot_penerimaan = (int)$detail_slip['gaji_pokok'] + (int)$detail_slip['tunjangan_transport'] + (int)$detail_slip['tunjangan_makan'] + (int)$detail_slip['bonus'];
  $tot_potongan = (int)$detail_slip['potongan_bpjs'] + (int)$detail_slip['potongan_pajak'] + (int)$detail_slip['potongan_lain'];
  $thp = (int)$detail_slip['total_gaji'];
?>
  <div class="mb-5 print:hidden flex flex-wrap items-center justify-between gap-2">
    <a href="<?= $is_admin ? 'admin_payroll.php' : 'slip_gaji.php' ?>" class="btn btn-garis btn-kecil gap-1">
      <?= ikon('kembali', 'h-4 w-4') ?><span>Kembali ke daftar</span>
    </a>
    <button onclick="window.print()" class="btn btn-utama btn-kecil gap-1.5">
      <?= ikon('cetak', 'h-4 w-4') ?><span>Cetak / Simpan PDF</span>
    </button>
  </div>

  <!-- Format slip gaji resmi -->
  <div class="mx-auto max-w-3xl rounded-lg border border-stone-200 bg-white p-6 md:p-8 print:m-0 print:w-full print:max-w-none print:border-0 print:p-0">

    <!-- Kop perusahaan -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-stone-800 pb-4">
      <div>
        <h2 class="text-xl font-semibold text-stone-900">MyHR Enterprise</h2>
        <p class="text-xs text-stone-600">PT Human Resource Solusindo Indonesia</p>
        <p class="keterangan">Jl. Jenderal Sudirman Kav. 52-53, Jakarta Selatan</p>
      </div>
      <div class="text-right">
        <span class="inline-block rounded border border-stone-300 bg-stone-50 px-2.5 py-1 text-xs font-semibold text-stone-800">Slip Gaji (Rahasia)</span>
        <p class="mt-1 text-xs text-stone-500">Periode: <strong class="text-stone-800"><?= nama_bulan($detail_slip['periode_bulan']) . ' ' . $detail_slip['periode_tahun'] ?></strong></p>
      </div>
    </div>

    <!-- Data karyawan -->
    <div class="my-5 grid grid-cols-2 gap-4 rounded-md bg-stone-50 p-4 text-xs md:grid-cols-4">
      <div>
        <span class="block text-stone-500">Nama Karyawan:</span>
        <span class="font-semibold text-stone-900"><?= e($detail_slip['nama']) ?></span>
      </div>
      <div>
        <span class="block text-stone-500">Nomor Induk (NIP):</span>
        <span class="font-semibold text-stone-900"><?= e($detail_slip['nip']) ?></span>
      </div>
      <div>
        <span class="block text-stone-500">Departemen:</span>
        <span class="font-semibold text-stone-900"><?= e($detail_slip['nama_departemen'] ?: 'Umum') ?></span>
      </div>
      <div>
        <span class="block text-stone-500">Jabatan:</span>
        <span class="font-semibold text-stone-900"><?= e($detail_slip['jabatan'] ?: 'Staf') ?></span>
      </div>
    </div>

    <!-- Rincian gaji dua kolom -->
    <div class="grid gap-6 text-xs md:grid-cols-2">

      <!-- Penerimaan -->
      <div class="rounded-md border border-stone-200 p-4">
        <h3 class="mb-3 border-b border-stone-200 pb-1.5 text-sm font-semibold text-stone-900">A. Penerimaan (Earnings)</h3>
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
            <tr class="border-t-2 border-stone-300 font-semibold text-stone-900">
              <td class="pt-2">Total Penerimaan</td>
              <td class="pt-2 text-right"><?= rupiah($tot_penerimaan) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Potongan -->
      <div class="rounded-md border border-stone-200 p-4">
        <h3 class="mb-3 border-b border-stone-200 pb-1.5 text-sm font-semibold text-stone-900">B. Potongan (Deductions)</h3>
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
            <tr class="border-t-2 border-stone-300 font-semibold text-stone-900">
              <td class="pt-2">Total Potongan</td>
              <td class="pt-2 text-right text-danger-700"><?= rupiah($tot_potongan) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>

    </div>

    <!-- Gaji bersih (take home pay) -->
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-stone-300 bg-stone-100 p-4">
      <div>
        <p class="text-xs text-stone-600">Gaji bersih diterima (take home pay):</p>
        <p class="text-2xl font-semibold text-stone-900"><?= rupiah($thp) ?></p>
      </div>
      <div class="text-right text-xs">
        <span class="lencana lencana-netral border border-stone-300 bg-white">Status: <?= e($detail_slip['status']) ?></span>
        <?php if ($detail_slip['tanggal_bayar']): ?>
          <p class="keterangan mt-1">Dibayarkan: <?= tgl($detail_slip['tanggal_bayar']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($detail_slip['catatan'])): ?>
      <div class="petak mt-4 text-xs text-stone-600">
        <strong>Catatan HR:</strong> <?= e($detail_slip['catatan']) ?>
      </div>
    <?php endif; ?>

    <!-- Tanda tangan dan keterangan -->
    <div class="mt-8 flex flex-wrap justify-between gap-4 border-t border-stone-200 pt-4 text-xs text-stone-500">
      <div>
        <p>Dicetak pada: <?= date('d/m/Y H:i') ?></p>
        <p class="text-[11px]">Dokumen ini dibuat otomatis secara digital oleh sistem HRIS MyHR.</p>
      </div>
      <div class="text-right">
        <p class="font-semibold text-stone-700">Departemen HR & Keuangan</p>
        <div class="h-10"></div>
        <p class="font-semibold text-stone-800 underline">MyHR Corporate System</p>
      </div>
    </div>

  </div>

<?php else: ?>
  <!-- Daftar riwayat slip gaji -->

  <?php if ($slip_terbaru): ?>
    <div class="kartu mb-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs font-medium text-stone-500">Slip gaji terakhir Anda</p>
          <h2 class="mt-1 text-2xl font-semibold text-stone-900"><?= rupiah($slip_terbaru['total_gaji']) ?></h2>
          <p class="keterangan mt-0.5">
            Periode: <?= nama_bulan($slip_terbaru['periode_bulan']) . ' ' . $slip_terbaru['periode_tahun'] ?>
            <br>Status: <?= e($slip_terbaru['status']) ?>
          </p>
        </div>
        <a href="?id=<?= e($slip_terbaru['id']) ?>" class="btn btn-utama gap-2">
          <?= ikon('payroll', 'h-4 w-4') ?><span>Lihat & cetak rincian</span>
        </a>
      </div>
    </div>
  <?php endif; ?>

  <div class="kartu mb-4 flex flex-wrap items-center justify-between gap-3 py-3">
    <h2 class="text-base font-semibold">Riwayat Slip Gaji Tahun <?= $tahun_pilih ?></h2>
    <form method="GET" class="flex items-center gap-2">
      <select name="tahun" class="pilih-kecil">
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
        <td data-label="Periode Gaji" class="font-medium text-stone-800"><?= nama_bulan($s['periode_bulan']) . ' ' . $s['periode_tahun'] ?></td>
        <td data-label="Total Penerimaan" class="text-xs text-stone-700"><?= rupiah($tot_p) ?></td>
        <td data-label="Total Potongan" class="text-xs text-danger-700">-<?= rupiah($tot_d) ?></td>
        <td data-label="Take Home Pay" class="font-semibold text-stone-900"><?= rupiah($s['total_gaji']) ?></td>
        <td data-label="Status">
          <?= lencana($s['status']) ?>
          <?php if ($s['tanggal_bayar']): ?>
            <br><span class="keterangan">Bayar: <?= tgl($s['tanggal_bayar']) ?></span>
          <?php endif; ?>
        </td>
        <td data-label="Aksi" class="whitespace-nowrap">
          <a href="?id=<?= e($s['id']) ?>" class="btn btn-utama btn-kecil">Lihat Slip</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php tabel_akhir(); ?>
  <?php endif; ?>

  <?php kartu_akhir(); ?>
<?php endif; ?>

<?php bottom(); ?>
