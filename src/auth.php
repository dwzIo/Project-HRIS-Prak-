<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

include_once __DIR__ . "/koneksi.php";
define('FOLDER_UPLOAD', __DIR__ . '/../uploads');


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

function proses_login(string $peran_halaman): string
{
  $nip = trim(isi('nip'));
  $user = satu("SELECT * FROM users WHERE nip=?", array($nip));

  if (!$user || !password_verify(isi('password'), $user['password'])) {
    return 'NIP atau password salah.';
  }
  if ($user['role'] !== $peran_halaman) {
    return 'NIP atau password salah.';
  }

  session_regenerate_id(true);
  $_SESSION['login'] = true;
  $_SESSION['id'] = $user['id'];
  $_SESSION['nama'] = $user['nama'];
  $_SESSION['role'] = $user['role'];
  pindah('dashboard.php');
  return '';
}
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

function jam_kerja_standar()
{
  return array(
    'jam_masuk_buka'  => '08:00',
    'jam_masuk_tepat' => '08:30',
    'jam_pulang_buka' => '17:00',
  );
}

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

function sinkron_alfa_otomatis()
{
  $hari_ini = date('Y-m-d');
  $jam_sekarang = date('H:i');

  $jk = ambil_jam_kerja($hari_ini);
  if (!$jk['ada_jadwal']) {
    return;
  }

  $jam_pulang = $jk['jam_pulang_buka'];

  if ($jam_sekarang >= $jam_pulang) {
    $karyawan = daftar("SELECT id, nama FROM users WHERE role='karyawan'");
    foreach ($karyawan as $k) {
      $ada = satu("SELECT id FROM attendances WHERE user_id=? AND tanggal=?", array($k['id'], $hari_ini));
      if (!$ada) {
        jalankan(
          "INSERT INTO attendances (id, user_id, tanggal, status, catatan, schedule_id) VALUES (?, ?, ?, 'Alfa', ?, ?)",
          array(buat_id(), $k['id'], $hari_ini, "Otomatis Alfa: Tidak melakukan presensi hingga jam pulang ($jam_pulang WIB)", $jk['id'])
        );
      }
    }
  }
}

function alasan_tidak_boleh_hapus($target)
{
  if ($target['id'] === $_SESSION['id']) {
    return 'Anda tidak bisa menghapus akun yang sedang dipakai.';
  }
  return '';
}

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
  $nama_baru = bin2hex(random_bytes(8)) . '.' . $ext;
  if (!move_uploaded_file($f['tmp_name'], $tujuan . '/' . $nama_baru)) {
    return array(null, 'File gagal disimpan ke folder uploads/' . $folder . '. Periksa izin tulis folder.');
  }
  return array($nama_baru, null);
}

function lencana($status)
{
  if (in_array($status, array('Disetujui', 'Selesai', 'Terverifikasi', 'Hadir', 'Aktif'))) {
    $jenis = 'sukses';
  } elseif ($status === 'Belum Absen') {
    $jenis = 'belum';
  } else {
    $jenis = 'lain';
  }

  return '<span class="lencana lencana-' . $jenis . '">' . e($status) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
  pesan('Ukuran data yang dikirim melebihi batas server (post_max_size di php.ini).', 'gagal');
  pindah(basename($_SERVER['PHP_SELF']));
}
