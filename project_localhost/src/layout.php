<?php
// Kerangka halaman dan komponen tampilan yang dipakai bersama oleh semua halaman.
//
//   gaya()       : Tailwind, palet warna, dan kelas komponen
//   ikon()       : ikon SVG garis
//   top/bottom() : pembuka dan penutup halaman (sidebar, judul, pesan)
//   kartu_*, tabel_*, isian*, pilih, avatar, ... : potongan tampilan kecil
//
// Semua gaya memakai Tailwind (utility class dan @apply di fungsi gaya()), tanpa file CSS terpisah.


// ---------- Gaya: Tailwind via CDN + kelas komponen ----------

function gaya()
{ ?>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <script>
    // Palet tambahan. Semuanya dibuat kalem agar status mudah dibaca tanpa terlihat mencolok.
    //   danger : merah bata  (ditolak, hapus, error)
    //   warn   : kuning tanah (menunggu, terlambat, draft)
    //   info   : biru baja   (diproses, izin, diterbitkan)
    //   ok     : hijau hutan (disetujui, hadir, selesai)
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
            },
            warn: {
              50: '#faf6ec',
              100: '#f3ecd6',
              200: '#e4d6aa',
              700: '#76590f',
              800: '#5f470f'
            },
            info: {
              50: '#f0f4f8',
              100: '#e1e9f1',
              200: '#c3d3e3',
              700: '#2f4f73',
              800: '#263f5b'
            },
            ok: {
              50: '#f0f6f2',
              100: '#dfece3',
              200: '#bdd6c5',
              700: '#2f6b41',
              800: '#26553a'
            }
          }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap">
  <style type="text/tailwindcss">
    @layer base {
      body {
        font-family: 'IBM Plex Sans', system-ui, sans-serif;
      }

      input[type=number] {
        -moz-appearance: textfield;
      }

      input[type=number]::-webkit-outer-spin-button,
      input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
      }
    }

    @layer components {

      /* Tombol */
      .btn {
        @apply inline-flex items-center justify-center rounded-md px-3.5 py-2 text-sm font-medium transition-colors duration-150 motion-reduce:transition-none;
      }

      .btn-utama {
        @apply bg-teal-700 text-white hover:bg-teal-800;
      }

      .btn-sukses {
        @apply bg-ok-700 text-white hover:bg-ok-800;
      }

      .btn-garis {
        @apply border border-stone-300 bg-white text-stone-700 hover:bg-stone-100;
      }

      .btn-bahaya {
        @apply border border-danger-200 bg-white text-danger-700 hover:bg-danger-50;
      }

      .btn-kecil {
        @apply px-2.5 py-1 text-xs;
      }

      /* Isian formulir */
      .kolom {
        @apply block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base transition-colors focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600 md:text-sm;
      }

      .pilih-kecil {
        @apply rounded-md border border-stone-300 bg-white px-2.5 py-1.5 text-xs focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600;
      }

      .label {
        @apply mb-1 block text-sm font-medium text-stone-700;
      }

      .keterangan {
        @apply text-xs text-stone-500;
      }

      /* Kartu dan petak angka */
      .kartu {
        @apply rounded-lg border border-stone-200 bg-white p-4 md:p-5;
      }

      .petak {
        @apply rounded-md border border-stone-200 bg-stone-50 p-3;
      }

      .kelompok {
        @apply mb-3 rounded-md border border-stone-200 bg-stone-50 p-3;
      }

      /* Tabel. Di layar kecil (di bawah md) tiap baris menjadi kartu dan tiap sel
         menampilkan labelnya sendiri dari atribut data-label. */
      .tabel {
        @apply w-full text-left text-sm max-md:block;
      }

      .tabel tbody {
        @apply max-md:block;
      }

      .tabel thead {
        @apply max-md:hidden;
      }

      .tabel tr {
        @apply max-md:mb-3 max-md:block max-md:rounded-lg max-md:border max-md:border-stone-200 max-md:bg-white max-md:px-3.5 max-md:py-2;
      }

      .tabel th {
        @apply border-b border-stone-200 py-2 pr-4 text-xs font-medium text-stone-500;
      }

      .tabel td {
        @apply border-b border-stone-100 py-2.5 pr-4 align-middle max-md:flex max-md:items-center max-md:justify-between max-md:gap-4 max-md:py-1.5 max-md:pr-0 max-md:text-right max-md:last:border-b-0 max-md:before:shrink-0 max-md:before:text-left max-md:before:text-xs max-md:before:font-medium max-md:before:text-stone-500 max-md:before:content-[attr(data-label)] max-md:[&[data-label='']]:before:content-none;
      }

      /* Lencana status */
      .lencana {
        @apply inline-flex items-center rounded px-2 py-0.5 text-xs font-medium;
      }

      .lencana-netral {
        @apply bg-stone-100 text-stone-700;
      }

      .lencana-sekunder {
        @apply bg-slate-200 text-slate-800;
      }

      .lencana-peringatan {
        @apply bg-warn-100 text-warn-800;
      }

      .lencana-info {
        @apply bg-info-100 text-info-800;
      }

      .lencana-sukses {
        @apply bg-ok-100 text-ok-800;
      }

      .lencana-bahaya {
        @apply bg-danger-100 text-danger-800;
      }

      /* Pesan dan pemberitahuan */
      .notif {
        @apply rounded-md px-4 py-3 text-sm;
      }

      .notif-netral {
        @apply border-stone-200 bg-stone-50 text-stone-700;
      }

      .notif-peringatan {
        @apply bg-warn-50 text-warn-800;
      }

      .notif-info {
        @apply border-info-200 bg-info-50 text-info-800;
      }

      .notif-sukses {
        @apply border-ok-200 bg-ok-50 text-ok-800;
      }

      .notif-bahaya {
        @apply bg-danger-50 text-danger-800;
      }

      /* Teks menu sidebar: memudar saat sidebar diciutkan (layar md ke atas) */
      .teks-menu {
        @apply inline-block max-w-[12rem] overflow-hidden whitespace-nowrap align-middle transition-[opacity,max-width] duration-300 motion-reduce:transition-none md:group-data-[sidebar=tutup]:max-w-0 md:group-data-[sidebar=tutup]:opacity-0;
      }

      /* Tautan di dalam konten */
      .tautan {
        @apply font-medium text-teal-700 underline underline-offset-2 hover:text-teal-900;
      }
    }
  </style>
<?php }


// ---------- Ikon SVG sederhana (bentuk garis) ----------

function ikon($nama, $ukuran = 'h-5 w-5')
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
    'kalender' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'payroll' => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/>',
    'departemen' => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
    'cetak' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
    'keluar' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    'mata' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    'mata-coret' => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>',
    'panel' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>',
    'menu' => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>',
    'tutup' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    'check' => '<polyline points="20 6 9 17 4 12"/>',
    'kembali' => '<polyline points="15 18 9 12 15 6"/>',
    'peringatan' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
  );

  $svg_isi = isset($isi[$nama]) ? $isi[$nama] : $isi['dashboard'];

  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $ukuran . ' shrink-0" aria-hidden="true">' . $svg_isi . '</svg>';
}


// ---------- Menu navigasi ----------

function menu_portal($admin)
{
  if ($admin) {
    // Portal admin
    return array(
      'dashboard.php' => array('Dashboard Admin', 'dashboard'),
      'admin_departemen.php' => array('Departemen', 'departemen'),
      'admin_karyawan.php' => array('Data Karyawan', 'karyawan'),
      'admin_absensi.php' => array('Rekap Absensi', 'absensi'),
      'admin_cuti.php' => array('Persetujuan Cuti', 'cuti'),
      'admin_payroll.php' => array('Kelola Payroll', 'payroll'),
      'admin_pengajuan.php' => array('Pengajuan Lain', 'pengajuan')
    );
  }

  // Portal karyawan
  return array(
    'dashboard.php' => array('Dashboard', 'dashboard'),
    'absensi.php' => array('Absensi Saya', 'absensi'),
    'cuti.php' => array('Pengajuan Cuti', 'cuti'),
    'slip_gaji.php' => array('Slip Gaji', 'payroll'),
    'profil.php' => array('Profil Saya', 'profil'),
    'surat.php' => array('Surat Keterangan', 'surat'),
    'dokumen.php' => array('Dokumen Saya', 'dokumen')
  );
}


// ---------- Pembuka dan penutup halaman ----------

function top($judul)
{
  $pesan_tampil = ambil_pesan();
  $admin = adalah_admin();
  $peran = nama_peran(peran_sekarang());
  $menu = menu_portal($admin);
  $halaman = basename($_SERVER['PHP_SELF']);

  $u = satu("SELECT * FROM users WHERE id=?", array($_SESSION['id']));
  if (!$u) {
    // Akun sudah dihapus
    session_destroy();
    pindah('login.php');
  }
?>
<!DOCTYPE html>
<html lang="id" class="group">

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

<body class="min-h-screen bg-stone-50 text-stone-800 md:flex print:bg-white">

  <!-- Bilah atas (hanya di HP) -->
  <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-stone-200 bg-white px-4 py-3 md:hidden print:hidden">
    <button type="button" id="buka-menu" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false" class="-ml-2 rounded-md p-2 text-stone-600 transition-colors hover:bg-stone-100"><?= ikon('menu') ?></button>
    <div class="flex items-center gap-2">
      <span class="text-lg font-semibold text-teal-700">MyHR</span>
      <span class="lencana lencana-netral"><?= $admin ? 'Admin' : 'Karyawan' ?></span>
    </div>
    <div class="ml-auto"><?= avatar($u, 'h-8 w-8', 'text-sm') ?></div>
  </header>

  <!-- Lapisan gelap di belakang laci menu (HP) -->
  <div id="lapisan-menu" class="pointer-events-none fixed inset-0 z-40 bg-stone-900/50 opacity-0 transition-opacity duration-300 md:hidden print:hidden"></div>

  <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col gap-4 bg-stone-900 p-3 text-stone-300 transition-[transform,width] duration-300 ease-out motion-reduce:transition-none md:sticky md:inset-y-auto md:top-0 md:z-auto md:h-screen md:translate-x-0 md:shrink-0 md:overflow-hidden md:group-data-[sidebar=tutup]:w-[4.5rem] print:hidden">

    <div class="flex items-center justify-between md:group-data-[sidebar=tutup]:justify-center">
      <div class="teks-menu px-2 md:group-data-[sidebar=tutup]:px-0">
        <a href="dashboard.php" class="text-lg font-semibold text-white">MyHR</a>
        <div class="mt-1">
          <span class="rounded bg-stone-800 px-2 py-0.5 text-xs text-stone-300"><?= $admin ? 'Portal Admin' : 'Portal Karyawan' ?></span>
        </div>
      </div>
      <button type="button" id="tombol-sidebar" title="Ciutkan / lebarkan menu" aria-label="Ciutkan atau lebarkan menu" class="hidden rounded-md p-2 text-stone-400 transition-colors hover:bg-stone-800 hover:text-white md:block"><?= ikon('panel') ?></button>
      <button type="button" id="tutup-menu" aria-label="Tutup menu" class="rounded-md p-2 text-stone-400 transition-colors hover:bg-stone-800 hover:text-white md:hidden"><?= ikon('tutup') ?></button>
    </div>

    <nav class="flex flex-col gap-1 overflow-y-auto pr-1">
      <?php foreach ($menu as $url => $item):
        $kelas = 'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-[background-color,color,padding] duration-300 hover:bg-stone-800 hover:text-white md:py-2 md:group-data-[sidebar=tutup]:pl-3.5';
        if ($halaman === $url) {
          $kelas = $kelas . ' bg-stone-800 font-medium text-white';
        }
      ?>
        <a href="<?= $url ?>" title="<?= e($item[0]) ?>" class="<?= $kelas ?>"><?= ikon($item[1]) ?><span class="teks-menu"><?= e($item[0]) ?></span></a>
      <?php endforeach; ?>
    </nav>

    <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-stone-800 pt-3 md:group-data-[sidebar=tutup]:flex-col md:group-data-[sidebar=tutup]:flex-nowrap md:group-data-[sidebar=tutup]:[&_a]:gap-0">
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

  <main class="min-w-0 flex-1 px-4 py-5 md:px-10 md:py-8 print:m-0 print:max-w-full print:p-0">
    <div class="mx-auto max-w-5xl print:max-w-none">

      <div class="mb-5 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold md:text-2xl"><?= e($judul) ?></h1>
      </div>

      <?php if ($pesan_tampil):
        $jenis = 'notif-sukses';
        if ($pesan_tampil[1] === 'gagal') {
          $jenis = 'notif-bahaya';
        }
      ?>
        <div class="notif <?= $jenis ?> mb-5"><?= e($pesan_tampil[0]) ?></div>
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


// ---------- Potongan tampilan yang sering dipakai ----------

function kartu_awal($judul, $ekstra_kelas = '')
{
  echo '<section class="kartu mb-5 ' . $ekstra_kelas . '">';
  echo '<h2 class="mb-4 text-base font-semibold">' . $judul . '</h2>';
}

function kartu_akhir()
{
  echo '</section>';
}

// Petak angka ringkasan: label kecil di atas, nilai besar di bawah.
// $kotak : kelas pembungkus ('kartu' atau 'petak text-center')
// $url   : jika diisi, seluruh petak menjadi tautan
function kartu_angka($label, $nilai, $warna = 'text-stone-900', $kotak = 'kartu', $url = '')
{
  $isi = '<p class="text-xs text-stone-500">' . e($label) . '</p>';
  $isi .= '<p class="mt-auto pt-1 text-2xl font-semibold ' . $warna . '">' . e($nilai) . '</p>';

  if ($url !== '') {
    echo '<a href="' . e($url) . '" class="' . $kotak . ' flex h-full flex-col transition-colors hover:border-stone-400">' . $isi . '</a>';
  } else {
    echo '<div class="' . $kotak . ' flex h-full flex-col">' . $isi . '</div>';
  }
}

function kosong($teks)
{
  echo '<p class="py-3 text-center text-sm text-stone-500">' . $teks . '</p>';
}

function tabel_awal($kolom, $id = '')
{
  $atribut = '';
  if ($id !== '') {
    $atribut = ' id="' . $id . '"';
  }

  echo '<div class="overflow-x-auto"><table class="tabel"' . $atribut . '><thead><tr>';
  foreach ($kolom as $k) {
    echo '<th>' . $k . '</th>';
  }
  echo '</tr></thead><tbody>';
}

function tabel_akhir()
{
  echo '</tbody></table></div>';
}


// ---------- Isian formulir ----------

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

// Isian dengan label mengambang (halaman login dan lupa password)
function isian_login($label, $nama, $tipe = 'text', $nilai = '', $tambahan = '')
{
  echo '<div class="relative mb-3">';
  echo '<input type="' . $tipe . '" name="' . $nama . '" value="' . e($nilai) . '" placeholder=" " ' . $tambahan . ' class="peer block w-full rounded-md border border-stone-300 bg-white px-3.5 pb-2 pt-6 text-base transition-colors focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600 md:text-sm">';
  echo '<label class="pointer-events-none absolute left-3.5 top-2 text-xs text-stone-500 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-focus:top-2 peer-focus:text-xs">' . $label . '</label>';
  if ($tipe === 'password') {
    echo '<button type="button" data-mata title="Lihat / sembunyikan password" class="absolute right-3 top-3.5 text-stone-400 hover:text-stone-700">' . ikon('mata') . '<span class="hidden">' . ikon('mata-coret') . '</span></button>';
  }
  echo '</div>';
}


// ---------- Foto profil ----------

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


// ---------- Rangka halaman login (dipakai login.php, login_admin.php, login_karyawan.php) ----------

function login_atas(string $judul_tab, string $judul_kiri, string $teks_kiri, string $teks_pesan = '', string $jenis_pesan = 'gagal'): void
{
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul_tab) ?> | MyHR</title>
  <?php gaya(); ?>
</head>

<body class="min-h-screen bg-white text-stone-800 lg:flex">

  <!-- Kiri: panel statis (hanya layar lebar), tersusun vertikal: logo, gambar, teks. -->
  <section class="hidden min-h-screen flex-1 flex-col overflow-hidden bg-stone-50 px-12 py-10 lg:flex">
    <p class="text-2xl font-semibold text-teal-700">MyHR</p>

    <div class="relative my-6 min-h-[12rem] flex-1">
      <img src="assets/hris.jpg" alt="Ilustrasi pengelolaan data karyawan di sistem HR" class="absolute inset-0 h-full w-full object-contain mix-blend-multiply">
    </div>

    <div class="max-w-md">
      <h2 class="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl"><?= e($judul_kiri) ?></h2>
      <p class="mt-5 text-stone-600"><?= e($teks_kiri) ?></p>
    </div>
  </section>

  <!-- Kanan: isi (formulir / pilihan peran) -->
  <section class="flex min-h-screen w-full items-center justify-center border-stone-200 px-6 py-10 lg:w-[34rem] lg:border-l">
    <div class="w-full max-w-md">
      <p class="mb-8 text-2xl font-semibold text-teal-700 lg:hidden">MyHR</p>

      <?php if ($teks_pesan !== ''):
        $kelas = 'notif-bahaya';
        if ($jenis_pesan === 'sukses') {
          $kelas = 'notif-sukses';
        }
      ?>
        <div class="notif <?= $kelas ?> mb-4"><?= e($teks_pesan) ?></div>
      <?php endif; ?>
<?php
}

function login_bawah(): void
{
?>
    </div>
  </section>

  <script src="assets/app.js"></script>
</body>

</html>
<?php
}

// Formulir login untuk satu peran. Tautan "Lupa password" hanya muncul untuk karyawan.
function form_login(string $peran, string $nip_isi = ''): void
{
  $judul = 'Masuk sebagai Karyawan';
  if ($peran === 'admin') {
    $judul = 'Masuk sebagai Admin';
  }
?>
      <div id="form-login">
        <h1 class="mb-4 text-lg font-semibold"><?= e($judul) ?></h1>
        <form method="POST" data-validasi novalidate>
          <input type="hidden" name="aksi" value="login">
          <?php isian_login('NIP', 'nip', 'text', $nip_isi, 'required'); ?>
          <?php isian_login('Password', 'password', 'password', '', 'required'); ?>
          <button class="btn btn-utama h-11 w-full">Masuk</button>
        </form>

        <?php if ($peran === 'karyawan'): ?>
          <p class="mt-4 text-center text-sm">
            <a href="lupa_password.php" class="font-medium text-stone-700 hover:underline">Lupa password?</a>
          </p>
        <?php endif; ?>

        <p class="mt-6 border-t border-stone-200 pt-4 text-center text-sm text-stone-500">
          <?php if ($peran === 'admin'): ?>
            Bukan admin? <a href="login_karyawan.php" class="font-medium text-stone-700 hover:underline">Masuk sebagai karyawan</a>
          <?php else: ?>
            Admin HR? <a href="login_admin.php" class="font-medium text-stone-700 hover:underline">Masuk sebagai admin</a>
          <?php endif; ?>
        </p>
      </div>
<?php
}
