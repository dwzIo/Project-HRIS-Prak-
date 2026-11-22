<?php
// Di-include paling atas setiap halaman (session_start harus di baris awal)
session_start();
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
    pindah('login.php');
  }
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
  wajib_login();
  if (!adalah_admin()) {
    pindah('dashboard.php');
  }
}
function wajib_karyawan()
{
  wajib_login();
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
function upload($nama, $folder, $ekstensi, $maks_mb)
{
  if (!ada_file($nama)) {
    return array(null, 'Pilih file terlebih dahulu.');
  }
  $f = $_FILES[$nama];
  if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
    return array(null, 'Ukuran file melebihi batas server (upload_max_filesize di php.ini).');
  }
  if ($f['error'] !== UPLOAD_ERR_OK) {
    return array(null, 'Upload gagal (kode error ' . $f['error'] . ').');
  }
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $ekstensi)) {
    return array(null, 'Tipe file harus: ' . implode(', ', $ekstensi) . '.');
  }
  if ($f['size'] > $maks_mb * 1048576) {
    return array(null, 'Ukuran file maksimal ' . $maks_mb . ' MB.');
  }
  $tujuan = FOLDER_UPLOAD . '/' . $folder;
  if (!is_dir($tujuan) && !mkdir($tujuan, 0777, true)) {
    return array(null, 'Folder uploads/' . $folder . ' tidak bisa dibuat. Periksa izin folder.');
  }
  $nama_baru = bin2hex(random_bytes(8)) . '.' . $ext; // nama diganti acak
  if (!move_uploaded_file($f['tmp_name'], $tujuan . '/' . $nama_baru)) {
    return array(null, 'File gagal disimpan ke folder uploads/' . $folder . '. Periksa izin tulis folder.');
  }
  return array($nama_baru, null);
}

// Label status berwarna (mendukung status dasar, absensi, cuti, dan payroll)
function lencana($status)
{
  $warna = 'bg-stone-100 text-stone-700';
  if (in_array($status, array('Menunggu', 'Terlambat', 'Draft'))) {
    $warna = 'bg-amber-100 text-amber-800';
  }
  if (in_array($status, array('Diproses', 'Izin', 'Diterbitkan'))) {
    $warna = 'bg-sky-100 text-sky-800';
  }
  if ($status === 'Sakit') {
    $warna = 'bg-purple-100 text-purple-800';
  }
  if (in_array($status, array('Disetujui', 'Selesai', 'Terverifikasi', 'Hadir', 'Dibayar', 'Aktif'))) {
    $warna = 'bg-emerald-100 text-emerald-800';
  }
  if (in_array($status, array('Ditolak', 'Perlu Diperbarui', 'Alfa', 'Kedaluwarsa'))) {
    $warna = 'bg-danger-100 text-danger-800';
  }
  return '<span class="inline-block rounded px-2 py-0.5 text-xs font-medium ' . $warna . '">' . e($status) . '</span>';
}

// Jika data kiriman melebihi post_max_size, PHP mengosongkan $_POST dan $_FILES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
  pesan('Ukuran data yang dikirim melebihi batas server (post_max_size di php.ini).', 'gagal');
  pindah(basename($_SERVER['PHP_SELF']));
}
