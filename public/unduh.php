<?php
// Menampilkan atau mengunduh file upload (hanya pemilik file atau admin)
// Contoh: unduh.php?jenis=dokumen&id=ABC123          -> dibuka di browser
//         unduh.php?jenis=surat&id=ABC123&unduh=1    -> diunduh
ini_set('display_errors', '0');          // peringatan PHP tidak boleh ikut masuk ke data gambar
ini_set('zlib.output_compression', 'Off');
include __DIR__ . "/../src/auth.php";
wajib_login();

function tidak_ada($teks) { http_response_code(404); echo $teks; exit; }

$jenis = '';
if (isset($_GET['jenis'])) { $jenis = $_GET['jenis']; }
$id = '';
if (isset($_GET['id'])) { $id = $_GET['id']; }

// jenis => tabel, kolom nama file, sub-folder di uploads
$peta = array(
  'foto' => array('users', 'foto', 'foto'),
  'bukti' => array('change_requests', 'bukti', 'bukti'),
  'dokumen' => array('documents', 'file', 'dokumen'),
  'surat' => array('letter_requests', 'file', 'surat')
);
$tipe_file = array(
  'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
  'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
);

if (!isset($peta[$jenis])) { tidak_ada('Jenis file tidak dikenal.'); }
$tabel = $peta[$jenis][0];
$kolom = $peta[$jenis][1];
$folder = $peta[$jenis][2];

$baris = satu("SELECT * FROM $tabel WHERE id=?", array($id)); // $tabel berasal dari daftar tetap di atas
if (!$baris || !$baris[$kolom]) { tidak_ada('Data file tidak ditemukan.'); }

$pemilik = $baris['id']; // foto: pemiliknya adalah pengguna itu sendiri (tabel users tidak punya kolom user_id)
if ($jenis !== 'foto') { $pemilik = $baris['user_id']; }
if (!adalah_admin() && $pemilik !== $_SESSION['id']) { http_response_code(403); exit('Anda tidak berhak membuka file ini.'); }

$nama_file = basename($baris[$kolom]);
$lokasi = FOLDER_UPLOAD . '/' . $folder . '/' . $nama_file;
if (!is_file($lokasi)) { tidak_ada('File tidak ada di server (uploads/' . $folder . '/' . $nama_file . ').'); }

$ext = strtolower(pathinfo($lokasi, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if (isset($tipe_file[$ext])) { $mime = $tipe_file[$ext]; }

$nama_unduh = $folder;
if (isset($baris['jenis'])) { $nama_unduh = preg_replace('/[^A-Za-z0-9]+/', '-', $baris['jenis']); }
$nama_unduh = $nama_unduh . '.' . $ext;

$cara = 'inline';
if (isset($_GET['unduh'])) { $cara = 'attachment'; }

session_write_close();
while (ob_get_level() > 0) { ob_end_clean(); } // buang output lain sebelum mengirim berkas
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($lokasi));
header('Content-Disposition: ' . $cara . '; filename="' . $nama_unduh . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache'); // agar foto/berkas baru selalu terambil
readfile($lokasi);
exit;
