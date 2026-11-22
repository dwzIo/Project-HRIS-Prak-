<?php
include __DIR__ . "/../src/auth.php";
include __DIR__ . "/../src/layout.php";
wajib_admin();
$boleh = array('alamat' => 'Alamat', 'no_hp' => 'Nomor HP', 'email' => 'Email', 'kontak_darurat' => 'Kontak darurat');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $aksi = isi('aksi');
  $id = isi('id');

  if ($aksi === 'data') {
    // Cari data pengajuan yang diklik admin
    $c = satu("SELECT * FROM change_requests WHERE id=? AND status='Menunggu'", array($id));
    if ($c) {
      $status = (isi('keputusan') === 'Disetujui') ? 'Disetujui' : 'Ditolak';
      $catatan = trim(isi('catatan'));

      // Ambil SEMUA data perubahan milik karyawan ini yang diajukan di waktu (created_at) yang sama
      $semua_perubahan = daftar(
        "SELECT * FROM change_requests WHERE user_id=? AND created_at=? AND status='Menunggu'",
        array($c['user_id'], $c['created_at'])
      );

      // Jika disetujui, update semua data baru ke tabel users
      if ($status === 'Disetujui') {
        foreach ($semua_perubahan as $item) {
          if (isset($boleh[$item['field']])) {
            jalankan("UPDATE users SET " . $item['field'] . "=? WHERE id=?", array($item['nilai_baru'], $item['user_id']));
          }
        }
      }

      // Update status semua baris dalam pengajuan tersebut
      jalankan(
        "UPDATE change_requests SET status=?, catatan=? WHERE user_id=? AND created_at=? AND status='Menunggu'",
        array($status, $catatan, $c['user_id'], $c['created_at'])
      );

      pesan("Pengajuan berhasil di-$status.");
    }
  } elseif ($aksi === 'reset') {
    $r = satu("SELECT * FROM password_resets WHERE id=?", array($id));
    if ($r && $r['status'] === 'Menunggu' && $r['expires_at'] >= time()) {
      $status = (isi('keputusan') === 'Disetujui') ? 'Disetujui' : 'Ditolak';
      $berlaku = ($status === 'Disetujui') ? 3600 : 86400;
      jalankan("UPDATE password_resets SET status=?, catatan=?, expires_at=? WHERE id=?", array($status, trim(isi('catatan')), time() + $berlaku, $id));
      pesan('Permintaan reset password ' . strtolower($status) . '.');
    } else {
      pesan('Permintaan sudah diputuskan atau kedaluwarsa.', 'gagal');
    }
  } elseif ($aksi === 'surat') {
    $status = isi('status');
    if (!in_array($status, array('Diproses', 'Selesai', 'Ditolak'))) {
      $status = 'Diproses';
    }
    $nama_file = null;
    $error = '';
    if (ada_file('file')) {
      $hasil = upload('file', 'surat', array('pdf', 'doc', 'docx'), 5);
      if ($hasil[1]) {
        $error = $hasil[1];
      } else {
        $nama_file = $hasil[0];
      }
    }
    if ($error !== '') {
      pesan($error, 'gagal');
    } else {
      if ($nama_file) {
        jalankan("UPDATE letter_requests SET status=?, file=? WHERE id=?", array($status, $nama_file, $id));
      } else {
        jalankan("UPDATE letter_requests SET status=? WHERE id=?", array($status, $id));
      }
      pesan('Status surat diperbarui.');
    }
  } elseif ($aksi === 'dokumen') {
    $status = (isi('status') === 'Terverifikasi') ? 'Terverifikasi' : 'Perlu Diperbarui';
    jalankan("UPDATE documents SET status=? WHERE id=?", array($status, $id));
    pesan('Status dokumen diperbarui.');
  }
  pindah('admin_pengajuan.php');
}

$urut_menunggu = "CASE WHEN status='Menunggu' THEN 0 ELSE 1 END";
$perubahan = daftar("SELECT c.*, u.nama, u.nip FROM change_requests c JOIN users u ON u.id=c.user_id ORDER BY " . str_replace('status', 'c.status', $urut_menunggu) . ", c.created_at DESC, c.rowid DESC");
$surat = daftar("SELECT l.*, u.nama FROM letter_requests l JOIN users u ON u.id=l.user_id ORDER BY CASE WHEN l.status IN ('Menunggu','Diproses') THEN 0 ELSE 1 END, l.created_at DESC, l.rowid DESC");
$dokumen = daftar("SELECT d.*, u.nama FROM documents d JOIN users u ON u.id=d.user_id ORDER BY " . str_replace('status', 'd.status', $urut_menunggu) . ", d.uploaded_at DESC, d.rowid DESC");
$resets = daftar("SELECT r.*, u.nip, u.nama FROM password_resets r JOIN users u ON u.id=r.user_id ORDER BY CASE WHEN r.status='Menunggu' THEN 0 ELSE 1 END, r.created_at DESC");

top('Pengajuan karyawan');
?>
<?php kartu_awal('Permintaan reset password'); ?>
<p class="-mt-2 mb-4 text-sm text-stone-500">Karyawan baru bisa mengganti passwordnya setelah permintaan disetujui di sini.</p>
<?php if (count($resets) == 0): kosong('Tidak ada permintaan reset password.');
else: tabel_awal(array('Karyawan', 'Diminta', 'Status', 'Keputusan')); ?>
  <?php foreach ($resets as $r):
    $statusTampil = $r['status'];
    if (($r['status'] === 'Menunggu' || $r['status'] === 'Disetujui') && $r['expires_at'] < time()) {
      $statusTampil = 'Kedaluwarsa';
    }
  ?>
    <tr>
      <td data-label="Karyawan"><?= e($r['nama']) ?><br class="max-md:hidden"><span class="text-xs text-stone-500 max-md:ml-2"><?= e($r['nip']) ?></span></td>
      <td data-label="Diminta"><?= e(date('d M Y H:i', $r['created_at'])) ?></td>
      <td data-label="Status"><?= lencana($statusTampil) ?></td>
      <td data-label="Keputusan">
        <?php if ($statusTampil === 'Menunggu'): ?>
          <form method="POST" class="flex flex-wrap items-center gap-1">
            <input type="hidden" name="aksi" value="reset"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
            <input name="catatan" placeholder="Catatan" class="kolom w-32 py-1">
            <button name="keputusan" value="Disetujui" class="btn btn-utama btn-kecil">Setujui</button>
            <button name="keputusan" value="Ditolak" class="btn btn-bahaya btn-kecil">Tolak</button>
          </form>
        <?php else: ?><span class="text-xs text-stone-500"><?= e($r['catatan']) ?></span><?php endif; ?>
      </td>
    </tr>
<?php endforeach;
  tabel_akhir();
endif; ?>
<?php kartu_akhir(); ?>

<?php kartu_awal('Perubahan data pribadi'); ?>
<?php if (count($perubahan) == 0): kosong('Tidak ada pengajuan.');
else: tabel_awal(array('Karyawan', 'Waktu', 'Data', 'Lama', 'Baru', 'Bukti', 'Keputusan')); ?>
  <?php foreach ($perubahan as $r): ?>
    <tr>
      <td data-label="Karyawan"><?= e($r['nama']) ?><br class="max-md:hidden"><span class="text-xs text-stone-500 max-md:ml-2"><?= e($r['nip']) ?></span></td>
      <td data-label="Waktu"><?= tgl($r['created_at']) ?></td>
      <td data-label="Data"><?= $boleh[$r['field']] ?></td>
      <td data-label="Lama"><?= e($r['nilai_lama']) ?: '-' ?></td>
      <td data-label="Baru"><?= e($r['nilai_baru']) ?></td>
      <td data-label="Bukti">
        <?php if ($r['bukti']):
          $ext_b = strtolower(pathinfo($r['bukti'], PATHINFO_EXTENSION));
        ?>
          <button type="button" onclick="lihatDokumen('unduh.php?jenis=bukti&id=<?= e($r['id']) ?>', 'Bukti: <?= e($r['nama']) ?> - <?= e($boleh[$r['field']]) ?>', '<?= $ext_b ?>')" class="text-teal-700 underline font-semibold cursor-pointer">
            Lihat
          </button>
          <?php else: ?>-<?php endif; ?>
      </td>
      <td data-label="Keputusan">
        <?php if ($r['status'] === 'Menunggu'): ?>
          <form method="POST" class="flex flex-wrap items-center gap-1">
            <input type="hidden" name="aksi" value="data"><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <input name="catatan" placeholder="Catatan" class="kolom w-32 py-1">
            <button name="keputusan" value="Disetujui" class="btn btn-utama btn-kecil" title="Menyetujui perubahan ini dan perubahan lain di waktu yang sama">Setujui</button>
            <button name="keputusan" value="Ditolak" class="btn btn-bahaya btn-kecil">Tolak</button>
          </form>
          <?php else: ?><?= lencana($r['status']) ?> <span class="text-xs text-stone-500"><?= e($r['catatan']) ?></span><?php endif; ?>
      </td>
    </tr>
<?php endforeach;
  tabel_akhir();
endif; ?>
<?php kartu_akhir(); ?>

<?php kartu_awal('Permintaan surat'); ?>
<?php if (count($surat) == 0): kosong('Tidak ada permintaan surat.');
else: tabel_awal(array('Karyawan', 'Surat', 'Keperluan', 'Status', 'Proses')); ?>
  <?php foreach ($surat as $r): ?>
    <tr>
      <td data-label="Karyawan"><?= e($r['nama']) ?></td>
      <td data-label="Surat"><?= e($r['jenis']) ?></td>
      <td data-label="Keperluan"><?= e($r['keperluan']) ?></td>
      <td data-label="Status"><?= lencana($r['status']) ?></td>
      <td data-label="Proses">
        <form method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-1">
          <input type="hidden" name="aksi" value="surat"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <select name="status" class="kolom w-28 py-1">
            <?php foreach (array('Diproses', 'Selesai', 'Ditolak') as $s): ?>
              <option <?= $s === $r['status'] ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <input type="file" name="file" accept=".pdf,.doc,.docx" class="w-40 text-xs">
          <button class="btn btn-utama btn-kecil">Simpan</button>
        </form>
      </td>
    </tr>
<?php endforeach;
  tabel_akhir();
endif; ?>
<?php kartu_akhir(); ?>

<?php kartu_awal('Verifikasi dokumen'); ?>
<?php if (count($dokumen) == 0): kosong('Belum ada dokumen yang diunggah.');
else: tabel_awal(array('Karyawan', 'Dokumen', 'Status', '')); ?>
  <?php foreach ($dokumen as $r):
    $ext_d = strtolower(pathinfo($r['file'], PATHINFO_EXTENSION));
  ?>
    <tr>
      <td data-label="Karyawan"><?= e($r['nama']) ?></td>
      <td data-label="Dokumen">
        <button type="button" onclick="lihatDokumen('unduh.php?jenis=dokumen&id=<?= e($r['id']) ?>', 'Dokumen: <?= e($r['jenis']) ?> (<?= e($r['nama']) ?>)', '<?= $ext_d ?>')" class="inline-flex items-center gap-1.5 text-teal-700 underline font-semibold cursor-pointer hover:text-teal-900">
          <?= ikon('dokumen') ?>
          <span><?= e($r['jenis']) ?></span>
        </button>
      </td>
      <td data-label="Status"><?= lencana($r['status']) ?></td>
      <td data-label="">
        <form method="POST" class="flex gap-1">
          <input type="hidden" name="aksi" value="dokumen"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button name="status" value="Terverifikasi" class="btn btn-utama btn-kecil">Verifikasi</button>
          <button name="status" value="Perlu Diperbarui" class="btn btn-bahaya btn-kecil">Perlu diperbarui</button>
        </form>
      </td>
    </tr>
<?php endforeach;
  tabel_akhir();
endif; ?>
<?php kartu_akhir(); ?>

<!-- Modal Preview Dokumen dengan Tombol Silang (X) -->
<div id="modal-preview" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm p-4 flex items-center justify-center transition-opacity" role="dialog" aria-modal="true">
  <div class="relative w-full max-w-3xl rounded-xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-150">
    <!-- Header Modal -->
    <div class="flex items-center justify-between border-b border-stone-200 px-5 py-3.5 bg-stone-50">
      <div class="flex items-center gap-2">
        <span class="text-teal-700"><?= ikon('dokumen') ?></span>
        <h3 id="modal-judul" class="text-sm font-bold text-stone-800 truncate max-w-lg">Preview Dokumen</h3>
      </div>
      <!-- Tombol Silang (X) untuk kembali / menutup -->
      <button type="button" onclick="tutupModal()" class="flex h-8 w-8 items-center justify-center rounded-lg text-stone-400 hover:bg-stone-200 hover:text-stone-700 transition" title="Tutup (Esc)">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Area Isi Dokumen (Gambar / PDF) -->
    <div id="modal-isi" class="p-4 overflow-auto flex items-center justify-center bg-stone-100 min-h-[350px]">
      <!-- Gambar / iframe dimuat secara dinamis -->
    </div>

    <!-- Footer Modal -->
    <div class="flex items-center justify-between border-t border-stone-200 px-5 py-3 bg-white">
      <a id="modal-unduh" href="#" download class="btn btn-garis btn-kecil flex items-center gap-1.5 text-xs font-semibold">
        <?= ikon('dokumen') ?>
        <span>Unduh Dokumen Asli</span>
      </a>
      <button type="button" onclick="tutupModal()" class="btn btn-utama btn-kecil px-4 text-xs font-semibold">
        Tutup Tampilan
      </button>
    </div>
  </div>
</div>

<script>
  function lihatDokumen(url, judul, ekstensi) {
    document.getElementById('modal-judul').textContent = judul || 'Preview Dokumen';
    document.getElementById('modal-unduh').href = url + '&unduh=1';

    const container = document.getElementById('modal-isi');
    container.innerHTML = '<p class="text-xs text-stone-400">Memuat berkas...</p>';

    ekstensi = (ekstensi || '').toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ekstensi)) {
      const img = document.createElement('img');
      img.src = url;
      img.alt = judul;
      img.className = 'max-h-[70vh] max-w-full rounded object-contain shadow-md mx-auto';
      img.onload = function() {
        container.innerHTML = '';
        container.appendChild(img);
      };
      img.onerror = function() {
        container.innerHTML = '<p class="text-xs text-danger-700 font-medium">Gagal memuat gambar atau format tidak didukung.</p>';
      };
    } else if (ekstensi === 'pdf') {
      const iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.className = 'w-full h-[70vh] rounded border border-stone-200 bg-white shadow-inner';
      container.innerHTML = '';
      container.appendChild(iframe);
    } else {
      container.innerHTML = `
      <div class="text-center py-8">
        <p class="text-sm font-semibold text-stone-700 mb-1">Pratinjau langsung tidak tersedia</p>
        <p class="text-xs text-stone-500 mb-4">Format berkas .${ekstensi} dapat dibuka setelah diunduh.</p>
        <a href="${url}&unduh=1" class="btn btn-utama btn-kecil inline-flex items-center gap-1">
          Unduh File (${ekstensi.toUpperCase()})
        </a>
      </div>
    `;
    }

    document.getElementById('modal-preview').classList.remove('hidden');
  }

  function tutupModal() {
    const modal = document.getElementById('modal-preview');
    modal.classList.add('hidden');
    document.getElementById('modal-isi').innerHTML = '';
  }

  // Tutup dengan tombol Escape di keyboard
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      tutupModal();
    }
  });

  // Tutup jika klik area latar belakang (luar kotak modal)
  document.getElementById('modal-preview').addEventListener('click', function(e) {
    if (e.target === this) {
      tutupModal();
    }
  });
</script>

<?php bottom(); ?>