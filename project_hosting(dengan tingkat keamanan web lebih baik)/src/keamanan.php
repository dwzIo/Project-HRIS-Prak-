<?php
// Konfigurasi keamanan terpusat: cookie session, header HTTP, CSRF, dan pembatasan login.
// Di-include oleh src/auth.php (dan logout.php) SEBELUM session dimulai.

// Isi dengan alamat IP reverse proxy / load balancer milik sendiri JIKA aplikasi berada di belakangnya
// (contoh: array('10.0.0.5')). Selama kosong, header X-Forwarded-For/Proto DIABAIKAN
// karena bisa dipalsukan klien. Di XAMPP / server langsung biarkan kosong.
if (!defined('PROXY_TERPERCAYA')) {
  define('PROXY_TERPERCAYA', array());
}

// Batas percobaan login gagal dalam satu jendela waktu (detik)
define('LOGIN_JENDELA', 900);        // 15 menit
define('LOGIN_MAKS_AKUN_IP', 5);     // per kombinasi NIP + IP  -> kunci sementara
define('LOGIN_MAKS_IP', 20);         // per IP (semua NIP)      -> melindungi dari tebak-tebakan massal
define('LOGIN_MAKS_NIP', 20);        // per NIP (semua IP)      -> melindungi dari serangan terdistribusi

// ---------- deteksi HTTPS & IP klien ----------
function proxy_terpercaya(): bool
{
  $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
  return in_array($remote, PROXY_TERPERCAYA, true);
}

function koneksi_https(): bool
{
  if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
    return true;
  }
  if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
    return true;
  }
  if (proxy_terpercaya() && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $bagian = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
    return strtolower(trim($bagian[0])) === 'https';
  }
  return false;
}

// IP klien. X-Forwarded-For hanya dipercaya jika permintaan datang dari proxy terpercaya;
// diambil alamat PALING KANAN yang bukan proxy kita (bagian kiri bisa dipalsukan klien).
function ip_klien(): string
{
  $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
  if (proxy_terpercaya() && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $daftar = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
    foreach ($daftar as $ip) {
      if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        continue;
      }
      if (in_array($ip, PROXY_TERPERCAYA, true)) {
        continue;
      }
      return $ip;
    }
  }
  return $remote;
}

// ---------- session ----------
function mulai_sesi_aman(): void
{
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }
  ini_set('session.use_strict_mode', '1');   // tolak session ID buatan penyerang
  ini_set('session.use_only_cookies', '1');
  ini_set('session.use_trans_sid', '0');
  session_name('myhr_sesi');                 // nama khusus agar tidak bentrok dengan aplikasi lain di localhost

  $secure = koneksi_https();
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(array(
      'lifetime' => 0,
      'path' => '/',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Lax'
    ));
  } else {
    // PHP < 7.3: SameSite diselipkan lewat path
    session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
  }
  session_start();
}

// ---------- header keamanan ----------
function kirim_header_keamanan(): void
{
  if (headers_sent()) {
    return;
  }
  header('X-Frame-Options: SAMEORIGIN');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
  // CSP sengaja HANYA berisi arahan yang aman: tidak membatasi script/style
  // karena aplikasi memakai Tailwind CDN dan skrip inline.
  header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
  if (koneksi_https()) {
    header('Strict-Transport-Security: max-age=15552000');
  }
  header_remove('X-Powered-By');
}

// ---------- CSRF ----------
function csrf_token(): string
{
  if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

// Hidden input untuk disisipkan di setiap <form method="POST">
function csrf_field(): string
{
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_valid(): bool
{
  $kirim = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
  $simpan = isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
  if (!is_string($kirim) || !is_string($simpan) || $simpan === '') {
    return false;
  }
  return hash_equals($simpan, $kirim);
}

// Dipanggil otomatis di akhir auth.php: SEMUA permintaan POST wajib membawa token yang benar,
// sehingga tidak ada endpoint POST yang terlewat.
function csrf_wajib(): void
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || csrf_valid()) {
    return;
  }
  pesan('Sesi formulir tidak valid atau sudah kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.', 'gagal');
  header('Location: ' . basename($_SERVER['PHP_SELF']));
  exit;
}

// ---------- pembatasan percobaan login ----------
// Hanya percobaan GAGAL yang dicatat (tabel login_attempts). Kunci bersifat SEMENTARA
// (jendela geser 15 menit), tidak ada kunci permanen, dan percobaan saat terkunci tidak memperpanjang kunci.
function login_kunci_nip(string $nip): string
{
  return substr(strtolower(trim($nip)), 0, 64);
}

// Mengembalikan sisa detik terkunci, atau 0 jika boleh mencoba.
function login_sisa_kunci(string $lingkup, string $nip): int
{
  $nip = login_kunci_nip($nip);
  $ip = ip_klien();
  $sekarang = time();
  $dari = $sekarang - LOGIN_JENDELA;

  $aturan = array(
    array("lingkup=? AND nip=? AND ip=?", array($lingkup, $nip, $ip), LOGIN_MAKS_AKUN_IP),
    array("lingkup=? AND ip=?", array($lingkup, $ip), LOGIN_MAKS_IP),
    array("lingkup=? AND nip=?", array($lingkup, $nip), LOGIN_MAKS_NIP)
  );

  $sisa = 0;
  foreach ($aturan as $a) {
    $offset = (int)$a[2] - 1;
    $param = $a[1];
    $param[] = $dari;
    // Waktu percobaan gagal ke-N terakhir; jika ada, berarti batas tercapai
    $r = satu("SELECT waktu FROM login_attempts WHERE " . $a[0] . " AND waktu>? ORDER BY waktu DESC LIMIT 1 OFFSET " . $offset, $param);
    if ($r) {
      $selesai = (int)$r['waktu'] + LOGIN_JENDELA;
      if ($selesai - $sekarang > $sisa) {
        $sisa = $selesai - $sekarang;
      }
    }
  }
  return $sisa > 0 ? $sisa : 0;
}

function login_catat_gagal(string $lingkup, string $nip): void
{
  jalankan(
    "INSERT INTO login_attempts (lingkup, nip, ip, waktu) VALUES (?, ?, ?, ?)",
    array($lingkup, login_kunci_nip($nip), substr(ip_klien(), 0, 45), time())
  );
  // Bersihkan catatan lama sesekali supaya tabel tidak membesar
  if (random_int(1, 25) === 1) {
    jalankan("DELETE FROM login_attempts WHERE waktu<?", array(time() - 86400));
  }
}

// Setelah login berhasil: hapus catatan gagal milik pasangan NIP + IP ini saja
function login_bersihkan(string $lingkup, string $nip): void
{
  jalankan(
    "DELETE FROM login_attempts WHERE lingkup=? AND nip=? AND ip=?",
    array($lingkup, login_kunci_nip($nip), ip_klien())
  );
}

function pesan_terkunci(int $detik): string
{
  $menit = (int)ceil($detik / 60);
  if ($menit < 1) {
    $menit = 1;
  }
  return 'Terlalu banyak percobaan gagal. Silakan coba lagi dalam ' . $menit . ' menit.';
}
