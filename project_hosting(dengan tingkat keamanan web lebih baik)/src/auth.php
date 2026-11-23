<?php
// Di-include paling atas setiap halaman (session dimulai lewat src/keamanan.php)
include_once __DIR__ . "/keamanan.php";
mulai_sesi_aman();       // cookie session aman (HttpOnly, SameSite, Secure bila HTTPS)
kirim_header_keamanan(); // header keamanan HTTP
// Sinkronisasi zona waktu resmi ke WIB (Waktu Indonesia Barat / UTC+7)
date_default_timezone_set('Asia/Jakarta');

include_once __DIR__ . "/koneksi.php";
define('FOLDER_UPLOAD', __DIR__ . '/../uploads'); // lokasi asli folder upload di server

// ---------- fungsi bantu umum ----------
function e($teks)
{
  return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}
function isi($nama)
{
  if (isset($_POST[$nama])) {
    return $_POST[$nama];
  }
  return '';
}
function tgl($waktu)
{
  return date('d M Y', strtotime($waktu));
}
// ID acak 16 karakter untuk semua tabel (bukan auto increment)
function buat_id()
{
  return bin2hex(random_bytes(8));
}
function pindah($url)
{
  header("Location: $url");
  exit;
}

function wajib_login()
{
  if (!isset($_SESSION['login'])) {
    pindah('login.php'); // halaman pilih peran (admin / karyawan)
  }
}

// Alamat halaman login sesuai peran: admin dan karyawan punya halaman login sendiri
function url_login(string $peran = ''): string
{
  if ($peran === 'admin') {
    return 'login_admin.php';
  }
  if ($peran === 'karyawan') {
    return 'login_karyawan.php';
  }
  return 'login.php';
}

// Memproses formulir login untuk SATU peran saja ('admin' atau 'karyawan').
// Mengembalikan teks error, atau teks KOSONG jika sudah berhasil (lalu langsung pindah ke dashboard).
function proses_login(string $peran_halaman): string
{
  $nip = trim(isi('nip'));

  // Batasi percobaan: jika sudah terlalu banyak gagal, tolak sebelum memeriksa password
  $sisa = login_sisa_kunci('login', $nip);
  if ($sisa > 0) {
    return pesan_terkunci($sisa);
  }

  $user = satu("SELECT * FROM users WHERE nip=?", array($nip));

  // Hash palsu: password_verify tetap dijalankan walau NIP tidak ada, agar waktu respons tidak membocorkan NIP yang valid
  $hash = '$2y$10$abcdefghijklmnopqrstuuabcdefghijklmnopqrstuvwxyzABCDE';
  if ($user) {
    $hash = $user['password'];
  }
  $cocok = password_verify((string)isi('password'), $hash);

  if (!$user || !$cocok) {
    login_catat_gagal('login', $nip);
    return 'NIP atau password salah.';
  }
  // Password benar, tetapi akunnya milik peran lain: arahkan ke halaman yang tepat
  // (tetap dihitung sebagai percobaan gagal agar halaman ini tidak bisa dipakai menguji password)
  if ($user['role'] !== $peran_halaman) {
    login_catat_gagal('login', $nip);
    if ($peran_halaman === 'admin') {
      return 'Akun ini bukan akun admin. Silakan masuk lewat halaman login karyawan.';
    }
    return 'Akun ini adalah akun admin. Silakan masuk lewat halaman login admin.';
  }

  login_bersihkan('login', $nip);
  session_regenerate_id(true);                  // cegah session fixation
  $_SESSION['csrf'] = bin2hex(random_bytes(32)); // token CSRF baru setelah login
  $_SESSION['login'] = true;
  $_SESSION['id'] = $user['id'];
  $_SESSION['nama'] = $user['nama'];
  $_SESSION['role'] = $user['role'];
  pindah('dashboard.php');
  return ''; // tidak pernah tercapai: pindah() sudah menghentikan skrip
}
// Peran dibaca dari database (bukan dari session) agar perubahan peran langsung berlaku
function peran_sekarang()
{
  static $peran = null;
  if ($peran === null) {
    $r = satu("SELECT role FROM users WHERE id=?", array($_SESSION['id']));
    $peran = '';
    if ($r) {
      $peran = $r['role'];
    }
  }
  return $peran;
}
function adalah_admin()
{
  return peran_sekarang() === 'admin';
}
function nama_peran($role)
{
  if ($role === 'admin') {
    return 'Admin';
  }
  return 'Karyawan';
}
function wajib_admin()
{
  if (!isset($_SESSION['login'])) {
    pindah(url_login('admin'));
  }
  if (!adalah_admin()) {
    pindah('dashboard.php');
  }
}
function wajib_karyawan()
{
  if (!isset($_SESSION['login'])) {
    pindah(url_login('karyawan'));
  }
  if (adalah_admin()) {
    pindah('dashboard.php');
  }
}

// Helper format rupiah & nama bulan
function rupiah($angka)
{
  return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function nama_bulan($bln)
{
  $daftar = array(
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
  );
  return isset($daftar[(int)$bln]) ? $daftar[(int)$bln] : (string)$bln;
}

// Ambil & Simpan Pengaturan Sistem (Jam Kerja, dll)
function ambil_pengaturan($kunci, $default = '')
{
  $r = satu("SELECT nilai FROM settings WHERE kunci=?", array($kunci));
  return $r ? $r['nilai'] : $default;
}

function simpan_pengaturan($kunci, $nilai)
{
  jalankan("INSERT OR REPLACE INTO settings (kunci, nilai) VALUES (?, ?)", array($kunci, (string)$nilai));
}

// Ambil Jam Kerja yang berlaku pada tanggal tertentu:
// Cek apakah tanggal berada dalam rentang jadwal yang ditentukan admin di work_schedules.
// Jika tanggal berada di luar rentang jadwal atau belum diatur, kembalikan status bahwa tidak ada jam kerja.
function ambil_jam_kerja($tanggal = null)
{
  if ($tanggal === null) {
    $tanggal = date('Y-m-d');
  }

  try {
    $jadwal = satu(
      "SELECT * FROM work_schedules WHERE ? >= tanggal_mulai AND ? <= tanggal_selesai ORDER BY tanggal_mulai DESC, created_at DESC LIMIT 1",
      array($tanggal, $tanggal)
    );
  } catch (Exception $e) {
    $jadwal = null;
  }

  if ($jadwal) {
    return array(
      'ada_jadwal' => true,
      'sumber' => 'rentang',
      'id' => $jadwal['id'],
      'nama' => $jadwal['nama'],
      'tanggal_mulai' => $jadwal['tanggal_mulai'],
      'tanggal_selesai' => $jadwal['tanggal_selesai'],
      'jam_masuk_buka' => $jadwal['jam_masuk_buka'],
      'jam_masuk_tepat' => $jadwal['jam_masuk_tepat'],
      'jam_pulang_buka' => $jadwal['jam_pulang_buka'],
      'pesan' => 'Jadwal Aktif'
    );
  }

  // Jika tanggal kosong, libur, atau di luar rentang jadwal yang disetting admin
  return array(
    'ada_jadwal' => false,
    'sumber' => 'kosong',
    'id' => null,
    'nama' => 'Tidak Ada Jam Kerja',
    'tanggal_mulai' => null,
    'tanggal_selesai' => null,
    'jam_masuk_buka' => null,
    'jam_masuk_tepat' => null,
    'jam_pulang_buka' => null,
    'pesan' => 'Tidak ada jam kerja pada tanggal tersebut'
  );
}

// Otomatisasi Status Alfa:
// HANYA berlaku untuk HARI INI (date('Y-m-d')) dan HANYA JIKA ADA JAM KERJA.
// Jika hari ini libur atau tidak ada jadwal, sistem TIDAK AKAN membuat status Alfa.
function sinkron_alfa_otomatis()
{
  $hari_ini = date('Y-m-d');
  $jam_sekarang = date('H:i');

  $jk = ambil_jam_kerja($hari_ini);
  // Jika hari ini tidak ada jam kerja / belum disetting jadwalnya, abaikan
  if (!$jk['ada_jadwal']) {
    return;
  }

  $jam_pulang = $jk['jam_pulang_buka'];

  // Hanya jalankan jika waktu hari ini sudah mencapai atau melewati jam pulang kantor
  if ($jam_sekarang >= $jam_pulang) {
    $karyawan = daftar("SELECT id, nama FROM users WHERE role='karyawan'");
    foreach ($karyawan as $k) {
      $ada = satu("SELECT id FROM attendances WHERE user_id=? AND tanggal=?", array($k['id'], $hari_ini));
      if (!$ada) {
        jalankan(
          "INSERT INTO attendances (id, user_id, tanggal, status, catatan) VALUES (?, ?, ?, 'Alfa', ?)",
          array(buat_id(), $k['id'], $hari_ini, "Otomatis Alfa: Tidak melakukan presensi hingga jam pulang ($jam_pulang WIB)")
        );
      }
    }
  }
}

// Aturan hapus akun. Mengembalikan alasan penolakan, atau teks KOSONG jika boleh dihapus.
function alasan_tidak_boleh_hapus($target)
{
  if ($target['id'] === $_SESSION['id']) {
    return 'Anda tidak bisa menghapus akun yang sedang dipakai.';
  }
  return '';
}

// Pesan singkat yang tampil sekali setelah aksi
function pesan($teks, $tipe = 'sukses')
{
  $_SESSION['pesan'] = array($teks, $tipe);
}
function ambil_pesan()
{
  if (!isset($_SESSION['pesan'])) {
    return null;
  }
  $p = $_SESSION['pesan'];
  unset($_SESSION['pesan']);
  return $p;
}

// ---------- database (PDO + prepared statement) ----------
function jalankan($sql, $parameter = array())
{
  global $konek;
  $st = $konek->prepare($sql);
  $st->execute($parameter);
  return $st;
}
function satu($sql, $parameter = array())
{
  $baris = jalankan($sql, $parameter)->fetch(PDO::FETCH_ASSOC);
  if ($baris) {
    return $baris;
  }
  return null;
}
function daftar($sql, $parameter = array())
{
  return jalankan($sql, $parameter)->fetchAll(PDO::FETCH_ASSOC);
}
function angka($sql, $parameter = array())
{
  return (int) jalankan($sql, $parameter)->fetchColumn();
}

// ---------- upload file ----------
function ada_file($nama)
{
  return isset($_FILES[$nama]) && $_FILES[$nama]['error'] !== UPLOAD_ERR_NO_FILE;
}
// MIME asli (dibaca dari ISI file lewat finfo) yang diizinkan untuk tiap ekstensi
function mime_diizinkan(string $ext): array
{
  $peta = array(
    'jpg' => array('image/jpeg'),
    'jpeg' => array('image/jpeg'),
    'png' => array('image/png'),
    'pdf' => array('application/pdf'),
    'doc' => array('application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'),
    // docx adalah arsip zip; finfo kadang melaporkannya sebagai zip/octet-stream, jadi isinya dicek ulang di bawah
    'docx' => array(
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/zip',
      'application/x-zip-compressed',
      'application/octet-stream'
    )
  );
  return isset($peta[$ext]) ? $peta[$ext] : array();
}

// Memeriksa isi file sebenarnya. Mengembalikan array(ekstensi_final, pesan_error).
function periksa_isi_file(string $lokasi, string $ext): array
{
  if (!class_exists('finfo')) {
    return array(null, 'Ekstensi PHP "fileinfo" belum aktif di server, sehingga file tidak dapat diperiksa.');
  }
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string)$finfo->file($lokasi);
  if (!in_array($mime, mime_diizinkan($ext), true)) {
    return array(null, 'Isi file tidak sesuai dengan tipe yang diizinkan.');
  }

  $kepala = (string)file_get_contents($lokasi, false, null, 0, 8);

  if ($ext === 'doc' && substr($kepala, 0, 4) !== "\xD0\xCF\x11\xE0") {
    return array(null, 'Isi file bukan dokumen Word (.doc) yang valid.');
  }
  if ($ext === 'docx') {
    if (substr($kepala, 0, 2) !== 'PK') {
      return array(null, 'Isi file bukan dokumen Word (.docx) yang valid.');
    }
    if (class_exists('ZipArchive')) {
      $zip = new ZipArchive();
      if ($zip->open($lokasi) !== true || $zip->locateName('word/document.xml') === false) {
        return array(null, 'Isi file bukan dokumen Word (.docx) yang valid.');
      }
      $zip->close();
    }
  }
  if ($mime === 'image/jpeg') {
    return array('jpg', null);
  }
  if ($mime === 'image/png') {
    return array('png', null);
  }
  return array($ext, null);
}

// Gambar dibaca ulang lalu disimpan sebagai file baru (re-encode) agar metadata/data tersisip terbuang.
// Jika GD tidak tersedia, gambar tetap divalidasi lewat getimagesize lalu disimpan apa adanya.
function simpan_gambar_aman(string $sumber, string $tujuan, string $mime): bool
{
  $info = @getimagesize($sumber);
  if (!$info || $info['mime'] !== $mime) {
    return false;
  }
  if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 25000000) { // batas ~25 megapiksel
    return false;
  }
  if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
    return move_uploaded_file($sumber, $tujuan);
  }

  if ($mime === 'image/jpeg') {
    $im = @imagecreatefromjpeg($sumber);
  } else {
    $im = @imagecreatefrompng($sumber);
  }
  if (!$im) {
    return false;
  }

  if ($mime === 'image/jpeg') {
    // Pertahankan orientasi foto dari kamera (EXIF) karena EXIF ikut terbuang saat re-encode
    if (function_exists('exif_read_data') && function_exists('imagerotate')) {
      $exif = @exif_read_data($sumber);
      $arah = isset($exif['Orientation']) ? (int)$exif['Orientation'] : 1;
      $putar = 0;
      if ($arah === 3) {
        $putar = 180;
      } elseif ($arah === 6) {
        $putar = -90;
      } elseif ($arah === 8) {
        $putar = 90;
      }
      if ($putar !== 0) {
        $hasil = imagerotate($im, $putar, 0);
        if ($hasil) {
          imagedestroy($im);
          $im = $hasil;
        }
      }
    }
    $ok = imagejpeg($im, $tujuan, 90);
  } else {
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $ok = imagepng($im, $tujuan, 6);
  }
  imagedestroy($im);
  return $ok;
}

function upload($nama, $folder, $ekstensi, $maks_mb)
{
  if (!ada_file($nama)) {
    return array(null, 'Pilih file terlebih dahulu.');
  }
  $f = $_FILES[$nama];
  if (is_array($f['error'])) {
    return array(null, 'Upload tidak valid.'); // tolak kiriman berbentuk array
  }
  if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
    return array(null, 'Ukuran file melebihi batas server (upload_max_filesize di php.ini).');
  }
  if ($f['error'] !== UPLOAD_ERR_OK) {
    return array(null, 'Upload gagal (kode error ' . $f['error'] . ').');
  }
  if (!is_uploaded_file($f['tmp_name'])) {
    return array(null, 'Upload tidak valid.');
  }
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $ekstensi, true)) {
    return array(null, 'Tipe file harus: ' . implode(', ', $ekstensi) . '.');
  }
  if ($f['size'] > $maks_mb * 1048576 || filesize($f['tmp_name']) > $maks_mb * 1048576) {
    return array(null, 'Ukuran file maksimal ' . $maks_mb . ' MB.');
  }

  // Jangan percaya ekstensi: periksa MIME dari isi file
  $periksa = periksa_isi_file($f['tmp_name'], $ext);
  if ($periksa[1] !== null) {
    return array(null, $periksa[1]);
  }
  $ext_final = $periksa[0];

  $tujuan = FOLDER_UPLOAD . '/' . $folder;
  if (!is_dir($tujuan) && !mkdir($tujuan, 0755, true)) {
    return array(null, 'Folder uploads/' . $folder . ' tidak bisa dibuat. Periksa izin folder.');
  }
  $nama_baru = bin2hex(random_bytes(16)) . '.' . $ext_final; // nama diganti acak
  $lokasi_baru = $tujuan . '/' . $nama_baru;

  if ($ext_final === 'jpg' || $ext_final === 'png') {
    $mime = $ext_final === 'jpg' ? 'image/jpeg' : 'image/png';
    if (!simpan_gambar_aman($f['tmp_name'], $lokasi_baru, $mime)) {
      return array(null, 'File gambar tidak valid atau rusak.');
    }
  } elseif (!move_uploaded_file($f['tmp_name'], $lokasi_baru)) {
    return array(null, 'File gagal disimpan ke folder uploads/' . $folder . '. Periksa izin tulis folder.');
  }
  @chmod($lokasi_baru, 0644);
  return array($nama_baru, null);
}

// Label status berwarna (mendukung status dasar, absensi, cuti, dan payroll).
// Kelas warna (lencana-*) didefinisikan di src/layout.php.
function lencana($status)
{
  $jenis = 'netral';

  if (in_array($status, array('Menunggu', 'Terlambat', 'Draft'))) {
    $jenis = 'peringatan';
  }
  if (in_array($status, array('Diproses', 'Izin', 'Diterbitkan'))) {
    $jenis = 'info';
  }
  if ($status === 'Sakit') {
    $jenis = 'sekunder';
  }
  if (in_array($status, array('Disetujui', 'Selesai', 'Terverifikasi', 'Hadir', 'Dibayar', 'Aktif'))) {
    $jenis = 'sukses';
  }
  if (in_array($status, array('Ditolak', 'Perlu Diperbarui', 'Alfa', 'Kedaluwarsa'))) {
    $jenis = 'bahaya';
  }

  return '<span class="lencana lencana-' . $jenis . '">' . e($status) . '</span>';
}

// Jika data kiriman melebihi post_max_size, PHP mengosongkan $_POST dan $_FILES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
  pesan('Ukuran data yang dikirim melebihi batas server (post_max_size di php.ini).', 'gagal');
  pindah(basename($_SERVER['PHP_SELF']));
}

// Semua permintaan POST wajib membawa token CSRF yang benar (lihat src/keamanan.php)
csrf_wajib();
