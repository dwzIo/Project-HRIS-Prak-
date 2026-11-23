<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();

$bulan_pilih = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun_pilih = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Simpan / Edit / Hapus / Terbitkan Massal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');

  if ($aksi === 'simpan') {
    $id_payroll = isi('id');
    $user_id = isi('user_id');
    $periode_bulan = (int)isi('periode_bulan');
    $periode_tahun = (int)isi('periode_tahun');

    $gaji_pokok = max(0, (int)isi('gaji_pokok'));
    $tunjangan_transport = max(0, (int)isi('tunjangan_transport'));
    $tunjangan_makan = max(0, (int)isi('tunjangan_makan'));
    $bonus = max(0, (int)isi('bonus'));

    $potongan_bpjs = max(0, (int)isi('potongan_bpjs'));
    $potongan_pajak = max(0, (int)isi('potongan_pajak'));
    $potongan_lain = max(0, (int)isi('potongan_lain'));

    $total_penerimaan = $gaji_pokok + $tunjangan_transport + $tunjangan_makan + $bonus;
    $total_potongan = $potongan_bpjs + $potongan_pajak + $potongan_lain;
    $total_gaji = max(0, $total_penerimaan - $total_potongan);

    $status = isi('status');
    if (!in_array($status, array('Draft', 'Diterbitkan', 'Dibayar'))) {
      $status = 'Draft';
    }
    $tanggal_bayar = isi('tanggal_bayar') ?: null;
    $catatan = trim(isi('catatan'));

    if ($user_id === '') {
      pesan('Karyawan wajib dipilih.', 'gagal');
    } else {
      if ($id_payroll !== '') {
        jalankan(
          "UPDATE payrolls SET 
      gaji_pokok=?, tunjangan_transport=?, tunjangan_makan=?, bonus=?, 
      potongan_bpjs=?, potongan_pajak=?, potongan_lain=?, total_gaji=?, 
      status=?, tanggal_bayar=?, catatan=? 
     WHERE id=?",
          array(
            $gaji_pokok,
            $tunjangan_transport,
            $tunjangan_makan,
            $bonus,
            $potongan_bpjs,
            $potongan_pajak,
            $potongan_lain,
            $total_gaji,
            $status,
            $tanggal_bayar,
            $catatan,
            $id_payroll
          )
        );
        pesan('Data slip gaji berhasil diperbarui.');
      } else {
        // Cek duplikasi payroll karyawan pada periode yang sama
        $ada = satu("SELECT id FROM payrolls WHERE user_id=? AND periode_bulan=? AND periode_tahun=?", array($user_id, $periode_bulan, $periode_tahun));
        if ($ada) {
          pesan("Slip gaji karyawan ini untuk periode " . nama_bulan($periode_bulan) . " $periode_tahun sudah dibuat.", 'gagal');
        } else {
          jalankan(
            "INSERT INTO payrolls (
       id, user_id, periode_bulan, periode_tahun,
       gaji_pokok, tunjangan_transport, tunjangan_makan, bonus,
       potongan_bpjs, potongan_pajak, potongan_lain, total_gaji,
       status, tanggal_bayar, catatan
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array(
              buat_id(),
              $user_id,
              $periode_bulan,
              $periode_tahun,
              $gaji_pokok,
              $tunjangan_transport,
              $tunjangan_makan,
              $bonus,
              $potongan_bpjs,
              $potongan_pajak,
              $potongan_lain,
              $total_gaji,
              $status,
              $tanggal_bayar,
              $catatan
            )
          );
          pesan('Slip gaji baru berhasil diterbitkan.');
        }
      }
    }
  } elseif ($aksi === 'terbitkan_semua') {
    jalankan(
      "UPDATE payrolls SET status='Diterbitkan' WHERE periode_bulan=? AND periode_tahun=? AND status='Draft'",
      array($bulan_pilih, $tahun_pilih)
    );
    pesan("Seluruh slip gaji draft periode " . nama_bulan($bulan_pilih) . " $tahun_pilih berhasil diterbitkan ke karyawan.");
  } elseif ($aksi === 'hapus') {
    $id_del = isi('id');
    jalankan("DELETE FROM payrolls WHERE id=?", array($id_del));
    pesan('Slip gaji dihapus.');
  }

  pindah("admin_payroll.php?bulan=$bulan_pilih&tahun=$tahun_pilih");
}

// Data form edit jika dipilih
$edit = null;
if (isset($_GET['edit'])) {
  $edit = satu("SELECT * FROM payrolls WHERE id=?", array($_GET['edit']));
}
$nilai = array(
  'id' => '',
  'user_id' => '',
  'periode_bulan' => $bulan_pilih,
  'periode_tahun' => $tahun_pilih,
  'gaji_pokok' => 0,
  'tunjangan_transport' => 0,
  'tunjangan_makan' => 0,
  'bonus' => 0,
  'potongan_bpjs' => 0,
  'potongan_pajak' => 0,
  'potongan_lain' => 0,
  'status' => 'Diterbitkan',
  'tanggal_bayar' => date('Y-m-d'),
  'catatan' => ''
);
if ($edit) {
  $nilai = $edit;
}

// Daftar karyawan untuk dropdown
$karyawan_list = daftar("
 SELECT u.id, u.nip, u.nama, u.gaji_pokok, d.nama AS departemen
 FROM users u
 LEFT JOIN departments d ON d.id = u.departemen_id
 WHERE u.role = 'karyawan'
 ORDER BY u.nama ASC
");
$opsi_karyawan = array('' => 'Pilih karyawan');
$gaji_default_map = array();
foreach ($karyawan_list as $k) {
  $ket_dept = $k['departemen'] ? ' (' . $k['departemen'] . ')' : '';
  $opsi_karyawan[$k['id']] = $k['nip'] . ' - ' . $k['nama'] . $ket_dept;
  $gaji_default_map[$k['id']] = (int)$k['gaji_pokok'];
}

// Ambil daftar slip gaji periode terpilih
$daftar_payroll = daftar("
 SELECT p.*, u.nip, u.nama, u.jabatan, d.nama AS nama_departemen
 FROM payrolls p
 JOIN users u ON u.id = p.user_id
 LEFT JOIN departments d ON d.id = u.departemen_id
 WHERE p.periode_bulan = ? AND p.periode_tahun = ?
 ORDER BY u.nama ASC
", array($bulan_pilih, $tahun_pilih));

// Hitung ringkasan periode
$total_anggaran = 0;
$total_dibayar = 0;
$total_draft = 0;
foreach ($daftar_payroll as $p) {
  $total_anggaran += (int)$p['total_gaji'];
  if ($p['status'] === 'Dibayar') {
    $total_dibayar++;
  }
  if ($p['status'] === 'Draft') {
    $total_draft++;
  }
}

top('Manajemen Payroll & Gaji Karyawan');
?>

<!-- Filter periode -->
<div class="kartu mb-6 flex flex-wrap items-center justify-between gap-4">
  <div>
    <h2 class="text-base font-semibold">Periode Payroll: <?= nama_bulan($bulan_pilih) . ' ' . $tahun_pilih ?></h2>
    <p class="keterangan mt-0.5">Buat, kelola, dan terbitkan slip gaji karyawan secara transparan.</p>
  </div>
  <form method="GET" class="flex flex-wrap items-center gap-2">
    <select name="bulan" class="pilih-kecil">
      <?php for ($b = 1; $b <= 12; $b++): ?>
        <option value="<?= $b ?>" <?= $b === $bulan_pilih ? 'selected' : '' ?>><?= nama_bulan($b) ?></option>
      <?php endfor; ?>
    </select>
    <select name="tahun" class="pilih-kecil">
      <?php for ($t = date('Y') - 1; $t <= date('Y') + 1; $t++): ?>
        <option value="<?= $t ?>" <?= $t === $tahun_pilih ? 'selected' : '' ?>><?= $t ?></option>
      <?php endfor; ?>
    </select>
    <button class="btn btn-utama btn-kecil">Pilih Periode</button>
  </form>
</div>

<!-- Ringkasan periode -->
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
  <?php kartu_angka('Total pengeluaran gaji', rupiah($total_anggaran)); ?>
  <?php kartu_angka('Karyawan sudah diterbitkan', count($daftar_payroll) . ' Slip'); ?>

  <div class="kartu flex items-center justify-between gap-3">
    <div>
      <p class="text-xs text-stone-500">Slip berstatus draft</p>
      <p class="mt-1 text-2xl font-semibold text-warn-700"><?= $total_draft ?> Slip</p>
    </div>
    <?php if ($total_draft > 0): ?>
      <form method="POST" data-konfirmasi="Terbitkan seluruh slip gaji draft ke karyawan?"><?= csrf_field() ?>
        <input type="hidden" name="aksi" value="terbitkan_semua">
        <button class="btn btn-sukses btn-kecil">Terbitkan Semua</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">

  <!-- Form input / ubah slip gaji -->
  <div class="lg:col-span-1">
    <?php kartu_awal($edit ? 'Ubah Slip Gaji' : 'Buat Slip Gaji Karyawan'); ?>
    <form method="POST" data-validasi novalidate id="form-payroll"><?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= e($nilai['id']) ?>">
      <input type="hidden" name="periode_bulan" value="<?= $bulan_pilih ?>">
      <input type="hidden" name="periode_tahun" value="<?= $tahun_pilih ?>">

      <?php if ($edit): ?>
        <div class="mb-3">
          <label class="label">Karyawan</label>
          <input type="hidden" name="user_id" value="<?= e($nilai['user_id']) ?>">
          <p class="rounded-md border border-stone-200 bg-stone-50 p-2 text-sm font-medium"><?= e($opsi_karyawan[$nilai['user_id']]) ?></p>
        </div>
      <?php else: ?>
        <?php pilih('Karyawan', 'user_id', $opsi_karyawan, $nilai['user_id'], 'id="select-user" required'); ?>
      <?php endif; ?>

      <!-- Komponen penerimaan -->
      <div class="kelompok">
        <p class="mb-2 text-sm font-semibold text-stone-800">Komponen penerimaan (Rp)</p>
        <?php isian('Gaji Pokok', 'gaji_pokok', 'number', $nilai['gaji_pokok'], 'id="gaji_pokok" min="0" required'); ?>
        <div class="grid grid-cols-2 gap-3">
          <?php isian('Tunj. Transport', 'tunjangan_transport', 'number', $nilai['tunjangan_transport'], 'id="tunj_transport" min="0"'); ?>
          <?php isian('Tunj. Makan', 'tunjangan_makan', 'number', $nilai['tunjangan_makan'], 'id="tunj_makan" min="0"'); ?>
        </div>
        <?php isian('Bonus / Insentif', 'bonus', 'number', $nilai['bonus'], 'id="bonus" min="0"'); ?>
      </div>

      <!-- Komponen potongan -->
      <div class="kelompok">
        <p class="mb-2 text-sm font-semibold text-stone-800">Komponen potongan (Rp)</p>
        <div class="grid grid-cols-2 gap-3">
          <?php isian('Potongan BPJS', 'potongan_bpjs', 'number', $nilai['potongan_bpjs'], 'id="pot_bpjs" min="0"'); ?>
          <?php isian('Pajak PPh 21', 'potongan_pajak', 'number', $nilai['potongan_pajak'], 'id="pot_pajak" min="0"'); ?>
        </div>
        <?php isian('Potongan Lainnya', 'potongan_lain', 'number', $nilai['potongan_lain'], 'id="pot_lain" min="0"'); ?>
      </div>

      <!-- Perkiraan take home pay -->
      <div class="mb-3 rounded-md border border-stone-200 bg-stone-100 p-3">
        <p class="keterangan">Total take home pay</p>
        <p id="preview-thp" class="text-xl font-semibold text-stone-900">Rp 0</p>
      </div>

      <?php pilih('Status Slip', 'status', array('Draft' => 'Draft (Hanya Admin)', 'Diterbitkan' => 'Diterbitkan (Dilihat Karyawan)', 'Dibayar' => 'Dibayar (Selesai Transfer)'), $nilai['status']); ?>
      <?php isian('Tanggal Pembayaran / Transfer', 'tanggal_bayar', 'date', $nilai['tanggal_bayar']); ?>

      <div class="mb-4">
        <label class="label">Catatan Tambahan (Opsional)</label>
        <input type="text" name="catatan" value="<?= e($nilai['catatan']) ?>" placeholder="Contoh: Termasuk lembur proyek weekend" class="kolom text-xs">
      </div>

      <div class="flex gap-2">
        <button class="btn btn-utama flex-1"><?= $edit ? 'Perbarui Slip' : 'Simpan Slip Gaji' ?></button>
        <?php if ($edit): ?>
          <a href="admin_payroll.php?bulan=<?= $bulan_pilih ?>&tahun=<?= $tahun_pilih ?>" class="btn btn-garis">Batal</a>
        <?php endif; ?>
      </div>
    </form>
    <?php kartu_akhir(); ?>
  </div>

  <!-- Tabel payroll periode terpilih -->
  <div class="lg:col-span-2">
    <?php kartu_awal('Daftar Slip Gaji Periode Ini'); ?>

    <div class="mb-3">
      <input id="cari" class="kolom text-xs" placeholder="Cari nama atau NIP...">
    </div>

    <?php if (count($daftar_payroll) === 0): ?>
      <?php kosong('Belum ada slip gaji yang dibuat pada periode ini.'); ?>
    <?php else: ?>
      <?php tabel_awal(array('Karyawan', 'Penerimaan', 'Potongan', 'Gaji Bersih', 'Status', 'Aksi'), 'tabel-cari'); ?>
      <?php foreach ($daftar_payroll as $pr):
        $tot_pen = (int)$pr['gaji_pokok'] + (int)$pr['tunjangan_transport'] + (int)$pr['tunjangan_makan'] + (int)$pr['bonus'];
        $tot_pot = (int)$pr['potongan_bpjs'] + (int)$pr['potongan_pajak'] + (int)$pr['potongan_lain'];
      ?>
        <tr>
          <td data-label="Karyawan">
            <span class="font-medium text-stone-900"><?= e($pr['nama']) ?></span>
            <br><span class="keterangan">NIP: <?= e($pr['nip']) ?></span>
            <?php if ($pr['nama_departemen']): ?>
              <br><span class="lencana lencana-netral mt-0.5"><?= e($pr['nama_departemen']) ?></span>
            <?php endif; ?>
          </td>
          <td data-label="Penerimaan" class="text-xs text-stone-700"><?= rupiah($tot_pen) ?></td>
          <td data-label="Potongan" class="text-xs text-danger-700">-<?= rupiah($tot_pot) ?></td>
          <td data-label="Gaji Bersih" class="text-xs font-semibold text-stone-900"><?= rupiah($pr['total_gaji']) ?></td>
          <td data-label="Status">
            <?= lencana($pr['status']) ?>
            <?php if ($pr['tanggal_bayar']): ?>
              <br><span class="keterangan">Tgl: <?= tgl($pr['tanggal_bayar']) ?></span>
            <?php endif; ?>
          </td>
          <td data-label="Aksi" class="whitespace-nowrap">
            <div class="flex gap-1.5">
              <a href="slip_gaji.php?id=<?= e($pr['id']) ?>" target="_blank" class="btn btn-utama btn-kecil" title="Lihat & cetak slip">Cetak</a>
              <a href="?bulan=<?= $bulan_pilih ?>&tahun=<?= $tahun_pilih ?>&edit=<?= e($pr['id']) ?>" class="btn btn-garis btn-kecil">Ubah</a>
              <form method="POST" data-konfirmasi="Hapus slip gaji untuk <?= e($pr['nama']) ?>?"><?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= e($pr['id']) ?>">
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

<script>
  // Peta gaji pokok default karyawan (dikirim dari PHP)
  const mapGaji = <?= json_encode($gaji_default_map) ?>;
  const selectUser = document.getElementById('select-user');
  const inputGajiPokok = document.getElementById('gaji_pokok');

  function nilaiKolom(id) {
    const kolom = document.getElementById(id);
    return Number(kolom ? kolom.value : 0) || 0;
  }

  function hitungTHP() {
    const totalPenerimaan = nilaiKolom('gaji_pokok') + nilaiKolom('tunj_transport') + nilaiKolom('tunj_makan') + nilaiKolom('bonus');
    const totalPotongan = nilaiKolom('pot_bpjs') + nilaiKolom('pot_pajak') + nilaiKolom('pot_lain');
    const thp = Math.max(0, totalPenerimaan - totalPotongan);

    const prev = document.getElementById('preview-thp');
    if (prev) {
      prev.textContent = 'Rp ' + thp.toLocaleString('id-ID');
    }
  }

  // Pilih karyawan baru: isi gaji pokok otomatis jika masih 0
  if (selectUser && inputGajiPokok) {
    selectUser.addEventListener('change', function() {
      const uid = selectUser.value;
      if (uid && mapGaji[uid] !== undefined && Number(inputGajiPokok.value) === 0) {
        inputGajiPokok.value = mapGaji[uid];
        hitungTHP();
      }
    });
  }

  document.querySelectorAll('#form-payroll input[type=number]').forEach(function(kolom) {
    kolom.addEventListener('input', hitungTHP);
  });

  hitungTHP();
</script>

<?php bottom(); ?>
