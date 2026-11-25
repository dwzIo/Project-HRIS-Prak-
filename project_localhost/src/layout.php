<?php
// Tailwind via CDN + beberapa kelas komponen kecil agar markup tidak berulang
function gaya()
{ ?>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <script>
    // Warna "danger" = merah standar (tidak terlalu terang) untuk notifikasi dan tombol bahaya
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            danger: {
              50: '#fdf2f1',
              100: '#fadfdc',
              200: '#f2bdb7',
              600: '#b9332a',
              700: '#9f2a22',
              800: '#82221c'
            }
          }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap">
  <style type="text/tailwindcss">
    @layer base {
      body { font-family: 'IBM Plex Sans', system-ui, sans-serif; }
      input[type=number] { -moz-appearance: textfield; }
      input[type=number]::-webkit-outer-spin-button, input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    }
    @layer components {
      .btn { @apply inline-flex items-center justify-center rounded-md px-3.5 py-2 text-sm font-medium transition duration-150 active:scale-[0.98] motion-reduce:transition-none motion-reduce:active:scale-100; }
      .btn-utama { @apply bg-teal-700 text-white hover:bg-teal-800; }
      .btn-sukses { @apply bg-emerald-700 text-white hover:bg-emerald-800; }
      .btn-garis { @apply border border-stone-300 bg-white text-stone-700 hover:bg-stone-100; }
      .btn-bahaya { @apply border border-danger-200 bg-white text-danger-700 hover:bg-danger-50; }
      .btn-kecil { @apply px-2.5 py-1 text-xs; }
      .kolom { @apply block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base transition-colors md:text-sm focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600; }
      .label { @apply mb-1 block text-sm font-medium text-stone-700; }
      .kartu { @apply rounded-lg border border-stone-200 bg-white p-4 md:p-5; }
      .tabel { @apply w-full text-left text-sm; }
      .tabel th { @apply border-b border-stone-200 py-2 pr-4 text-xs font-semibold uppercase tracking-wide text-stone-500; }
      .tabel td { @apply border-b border-stone-100 py-2.5 pr-4 align-middle; }
    }
  </style>
  <style>
    /* Sidebar: di layar lebar bisa diciutkan (hanya ikon) dengan animasi lebar */
    .teks-menu {
      display: inline-block;
      max-width: 12rem;
      white-space: nowrap;
      overflow: hidden;
      vertical-align: middle;
      transition: opacity .2s ease, max-width .3s ease;
    }

    @media (min-width: 768px) {
      #sidebar {
        width: 16rem;
        overflow: hidden;
        transition: width .3s ease;
      }

      html[data-sidebar="tutup"] #sidebar {
        width: 4.5rem;
      }

      html[data-sidebar="tutup"] .teks-menu {
        opacity: 0;
        max-width: 0;
      }

      /* Sidebar ciut: semua ikon berada tepat di tengah dan satu garis lurus */
      #sidebar nav a {
        transition: background-color .15s ease, color .15s ease, padding-left .3s ease;
      }

      html[data-sidebar="tutup"] #sidebar .kepala-sidebar {
        justify-content: center;
      }

      html[data-sidebar="tutup"] #sidebar .judul-sidebar {
        padding-left: 0;
        padding-right: 0;
      }

      html[data-sidebar="tutup"] #sidebar nav a {
        padding-left: 14px;
      }

      html[data-sidebar="tutup"] #sidebar .blok-akun {
        flex-direction: column;
        flex-wrap: nowrap;
        align-items: center;
      }

      html[data-sidebar="tutup"] #sidebar .blok-akun a {
        gap: 0;
      }
    }

    @media (prefers-reduced-motion: reduce) {

      #sidebar,
      .teks-menu {
        transition: none;
      }
    }

    /* Tabel di layar kecil berubah menjadi kartu: tiap sel menampilkan labelnya */
    @media (max-width: 767px) {
      .tabel-kartu thead {
        display: none;
      }

      .tabel-kartu,
      .tabel-kartu tbody {
        display: block;
      }

      .tabel-kartu tr {
        display: block;
        margin-bottom: .75rem;
        padding: .5rem .875rem;
        border: 1px solid #e7e5e4;
        border-radius: .5rem;
        background: #fff;
      }

      .tabel-kartu td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .4rem 0;
        border-bottom: 1px solid #f5f5f4;
        text-align: right;
      }

      .tabel-kartu td:last-child {
        border-bottom: 0;
      }

      .tabel-kartu td::before {
        content: attr(data-label);
        flex-shrink: 0;
        font-size: .75rem;
        font-weight: 600;
        color: #78716c;
        text-align: left;
      }

      .tabel-kartu td[data-label=""]::before {
        content: none;
      }
    }

    /* Cetak Dokumen / Surat */
    @media print {
      body {
        background: #fff !important;
      }

      #sidebar,
      header,
      #buka-menu,
      .sembunyi-cetak {
        display: none !important;
      }

      main {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
      }

      .area-cetak {
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
      }
    }
  </style>
<?php }

// Ikon SVG sederhana (bentuk garis) untuk menu
function ikon($nama)
{
  $isi = array(
    'dashboard' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
    'profil' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'surat' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    'dokumen' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
    'karyawan' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'pengajuan' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    'absensi' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'cuti' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/>',
    'payroll' => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/>',
    'departemen' => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
    'cetak' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
    'keluar' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    'mata' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    'mata-coret' => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>',
    'panel' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>',
    'menu' => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>',
    'tutup' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'
  );
  $svg_isi = isset($isi[$nama]) ? $isi[$nama] : $isi['dashboard'];
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">' . $svg_isi . '</svg>';
}

function top($judul)
{
  $pesan_tampil = ambil_pesan();
  $admin = adalah_admin();
  $peran = nama_peran(peran_sekarang());

  if ($admin) {
    // Navigasi Portal Admin
    $menu = array(
      'dashboard.php' => array('Dashboard Admin', 'dashboard'),
      'admin_departemen.php' => array('Departemen', 'departemen'),
      'admin_karyawan.php' => array('Data Karyawan', 'karyawan'),
      'admin_absensi.php' => array('Rekap Absensi', 'absensi'),
      'admin_cuti.php' => array('Persetujuan Cuti', 'cuti'),
      'admin_pengajuan.php' => array('Pengajuan Lain', 'pengajuan')
    );
  } else {
    // Navigasi Portal Karyawan
    $menu = array(
      'dashboard.php' => array('Dashboard', 'dashboard'),
      'absensi.php' => array('Absensi Saya', 'absensi'),
      'cuti.php' => array('Pengajuan Cuti', 'cuti'),
      'profil.php' => array('Profil Saya', 'profil'),
      'surat.php' => array('Surat Keterangan', 'surat'),
      'dokumen.php' => array('Dokumen Saya', 'dokumen')
    );
  }

  $halaman = basename($_SERVER['PHP_SELF']);
  $u = satu("SELECT * FROM users WHERE id=?", array($_SESSION['id']));
  if (!$u) {
    session_destroy();
    pindah('login.php');
  } // akun sudah dihapus
?>
  <!DOCTYPE html>
  <html lang="id">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> | MyHR</title>
    <script>
      try {
        if (localStorage.getItem("sidebar") === "tutup") {
          document.documentElement.dataset.sidebar = "tutup";
        }
      } catch (e) {}
    </script>
    <?php gaya(); ?>
  </head>

  <body class="min-h-screen bg-stone-50 text-stone-800 md:flex">

    <!-- Bilah atas: hanya di HP -->
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-stone-200 bg-white px-4 py-3 md:hidden">
      <button type="button" id="buka-menu" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false" class="-ml-2 rounded-md p-2 text-stone-600 transition-colors hover:bg-stone-100"><?= ikon('menu') ?></button>
      <div class="flex items-center gap-2">
        <span class="text-lg font-semibold text-teal-700">MyHR</span>
        <span class="rounded bg-teal-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-teal-800"><?= $admin ? 'Admin' : 'Karyawan' ?></span>
      </div>
      <div class="ml-auto"><?= avatar($u, 'h-8 w-8', 'text-sm') ?></div>
    </header>

    <!-- Lapisan gelap di belakang laci menu (HP) -->
    <div id="lapisan-menu" class="pointer-events-none fixed inset-0 z-40 bg-stone-900/50 opacity-0 transition-opacity duration-300 md:hidden"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col gap-4 bg-stone-900 p-3 text-stone-300 transition-transform duration-300 ease-out motion-reduce:transition-none md:sticky md:inset-y-auto md:top-0 md:z-auto md:h-screen md:translate-x-0 md:shrink-0">
      <div class="kepala-sidebar flex items-center justify-between">
        <div class="teks-menu judul-sidebar px-2">
          <a href="dashboard.php" class="text-lg font-semibold text-white">MyHR</a>
          <div class="mt-0.5"><span class="rounded bg-teal-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-300"><?= $admin ? 'Portal Admin' : 'Portal Karyawan' ?></span></div>
        </div>
        <button type="button" id="tombol-sidebar" title="Ciutkan / lebarkan menu" aria-label="Ciutkan atau lebarkan menu" class="hidden rounded-md p-2 text-stone-400 transition-colors hover:bg-stone-800 hover:text-white md:block"><?= ikon('panel') ?></button>
        <button type="button" id="tutup-menu" aria-label="Tutup menu" class="rounded-md p-2 text-stone-400 transition-colors hover:bg-stone-800 hover:text-white md:hidden"><?= ikon('tutup') ?></button>
      </div>
      <nav class="flex flex-col gap-1 overflow-y-auto pr-1">
        <?php foreach ($menu as $url => $item):
          $kelas = 'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors hover:bg-stone-800 hover:text-white md:py-2';
          if ($halaman === $url) {
            $kelas = $kelas . ' bg-stone-800 text-white font-medium';
          }
        ?>
          <a href="<?= $url ?>" title="<?= e($item[0]) ?>" class="<?= $kelas ?>"><?= ikon($item[1]) ?><span class="teks-menu"><?= e($item[0]) ?></span></a>
        <?php endforeach; ?>
      </nav>
      <div class="blok-akun mt-auto flex flex-wrap items-center gap-2 border-t border-stone-800 pt-3">
        <?= avatar($u) ?>
        <div class="min-w-0 text-sm">
          <div class="teks-menu">
            <p class="truncate font-medium text-white"><?= e($_SESSION['nama']) ?></p>
            <p class="text-xs text-stone-400"><?= e($peran) ?></p>
          </div>
          <a href="logout.php" title="Keluar" class="mt-1 flex items-center gap-1.5 text-stone-400 transition-colors hover:text-white"><?= ikon('keluar') ?><span class="teks-menu underline">Keluar</span></a>
        </div>
      </div>
    </aside>

    <main class="min-w-0 flex-1 px-4 py-5 md:px-10 md:py-8">
      <div class="mx-auto max-w-5xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 md:mb-5">
          <h1 class="text-xl font-semibold md:text-2xl"><?= e($judul) ?></h1>
        </div>
        <?php if ($pesan_tampil):
          $warna = 'border-emerald-200 bg-emerald-50 text-emerald-800';
          if ($pesan_tampil[1] === 'gagal') {
            $warna = 'border-danger-200 bg-danger-50 text-danger-800';
          }
        ?>
          <div class="mb-5 rounded-md border px-4 py-3 text-sm <?= $warna ?>"><?= e($pesan_tampil[0]) ?></div>
        <?php endif; ?>
      <?php }

    function bottom()
    { ?>
      </div>
    </main>
    <script src="assets/app.js"></script>
  </body>

  </html>
<?php }

    // ---------- potongan tampilan yang sering dipakai ----------
    function kartu_awal($judul, $ekstra_kelas = '')
    {
      echo '<section class="kartu mb-5 ' . $ekstra_kelas . '"><h2 class="mb-4 text-base font-semibold">' . $judul . '</h2>';
    }
    function kartu_akhir()
    {
      echo '</section>';
    }
    function kosong($teks)
    {
      echo '<p class="text-sm text-stone-500 py-3 text-center">' . $teks . '</p>';
    }

    function tabel_awal($kolom, $id = '')
    {
      $atribut = '';
      if ($id !== '') {
        $atribut = ' id="' . $id . '"';
      }
      echo '<div class="overflow-x-auto"><table class="tabel tabel-kartu"' . $atribut . '><thead><tr>';
      foreach ($kolom as $k) {
        echo '<th>' . $k . '</th>';
      }
      echo '</tr></thead><tbody>';
    }
    function tabel_akhir()
    {
      echo '</tbody></table></div>';
    }

    function isian($label, $nama, $tipe = 'text', $nilai = '', $tambahan = '')
    {
      echo '<div class="mb-3"><label class="label">' . $label . '</label>';
      echo '<input type="' . $tipe . '" name="' . $nama . '" value="' . e($nilai) . '" class="kolom" ' . $tambahan . '></div>';
    }

    function isian_sandi($label, $nama, $tambahan = '')
    {
      echo '<div class="mb-3"><label class="label">' . $label . '</label><div class="relative">';
      echo '<input type="password" name="' . $nama . '" class="kolom pr-10" autocomplete="new-password" ' . $tambahan . '>';
      echo '<button type="button" data-mata title="Lihat / sembunyikan password" class="absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 transition-colors hover:text-stone-700">' . ikon('mata') . '<span class="hidden">' . ikon('mata-coret') . '</span></button>';
      echo '</div></div>';
    }

    function pilih($label, $nama, $opsi, $terpilih = '', $tambahan = '')
    {
      echo '<div class="mb-3"><label class="label">' . $label . '</label><select name="' . $nama . '" class="kolom" ' . $tambahan . '>';
      foreach ($opsi as $nilai => $teks) {
        $tanda = '';
        if ((string)$nilai === (string)$terpilih) {
          $tanda = ' selected';
        }
        echo '<option value="' . e($nilai) . '"' . $tanda . '>' . e($teks) . '</option>';
      }
      echo '</select></div>';
    }

    function isian_login($label, $nama, $tipe = 'text', $nilai = '', $tambahan = '')
    {
      echo '<div class="relative mb-3">';
      echo '<input type="' . $tipe . '" name="' . $nama . '" value="' . e($nilai) . '" placeholder=" " ' . $tambahan . ' class="peer block w-full rounded-xl border border-stone-300 bg-white px-4 pb-2 pt-6 text-base transition-colors focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600 md:text-sm">';
      echo '<label class="pointer-events-none absolute left-4 top-2 text-xs text-stone-500 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-focus:top-2 peer-focus:text-xs">' . $label . '</label>';
      if ($tipe === 'password') {
        echo '<button type="button" data-mata title="Lihat / sembunyikan password" class="absolute right-3 top-3.5 text-stone-400 hover:text-stone-700">' . ikon('mata') . '<span class="hidden">' . ikon('mata-coret') . '</span></button>';
      }
      echo '</div>';
    }

    function foto_url($u)
    {
      return 'unduh.php?jenis=foto&id=' . urlencode($u['id']) . '&v=' . urlencode((string)$u['foto']);
    }

    function avatar($u, $ukuran = 'h-10 w-10', $teks = 'text-lg')
    {
      if (!empty($u['foto'])) {
        return '<img src="' . e(foto_url($u)) . '" alt="Foto ' . e($u['nama']) . '" class="' . $ukuran . ' shrink-0 rounded-full object-cover">';
      }
      $huruf = '?';
      if (isset($u['nama']) && preg_match('/^./su', $u['nama'], $cocok)) {
        $huruf = $cocok[0];
      }
      if (function_exists('mb_strtoupper')) {
        $huruf = mb_strtoupper($huruf);
      } else {
        $huruf = strtoupper($huruf);
      }
      return '<div class="flex ' . $ukuran . ' shrink-0 items-center justify-center rounded-full bg-stone-200 font-semibold text-stone-600 ' . $teks . '">' . e($huruf) . '</div>';
    }
