<?php
// Koneksi SQLite lewat PDO. Database = file database/hr.sqlite
// (sudah disertakan; dibuat otomatis dari schema.sql jika hilang).
$file = __DIR__ . "/../database/hr.sqlite";
$baru = !file_exists($file);
$konek = new PDO("sqlite:" . $file);
$konek->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$konek->exec("PRAGMA foreign_keys = ON");

if ($baru) {
  $konek->exec(file_get_contents(__DIR__ . "/../database/schema.sql"));
  // Akun awal: NIP "admin", password "admin123" (di-hash), role admin
  $st = $konek->prepare("INSERT INTO users (id,nip,nama,email,password,role) VALUES (?,'admin','Admin','hr@perusahaan.test',?,'admin')");
  $st->execute([bin2hex(random_bytes(8)), password_hash("admin123", PASSWORD_DEFAULT)]);
} else {
  // Pastikan tabel-tabel baru otomatis terbuat jika menggunakan file SQLite lama
  $konek->exec("
    CREATE TABLE IF NOT EXISTS settings (
      kunci TEXT PRIMARY KEY,
      nilai TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS departments (
      id TEXT PRIMARY KEY,
      nama TEXT NOT NULL UNIQUE,
      keterangan TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS attendances (
      id TEXT PRIMARY KEY,
      user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      tanggal TEXT NOT NULL,
      jam_masuk TEXT,
      jam_pulang TEXT,
      status TEXT NOT NULL DEFAULT 'Hadir' CHECK (status IN ('Hadir','Izin','Sakit','Terlambat','Alfa')),
      catatan TEXT,
      bukti TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(user_id, tanggal)
    );
    CREATE TABLE IF NOT EXISTS leave_requests (
      id TEXT PRIMARY KEY,
      user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      jenis_cuti TEXT NOT NULL,
      tanggal_mulai TEXT NOT NULL,
      tanggal_selesai TEXT NOT NULL,
      jumlah_hari INTEGER NOT NULL,
      alasan TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Disetujui','Ditolak')),
      catatan_admin TEXT,
      bukti TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS payrolls (
      id TEXT PRIMARY KEY,
      user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      periode_bulan INTEGER NOT NULL,
      periode_tahun INTEGER NOT NULL,
      gaji_pokok INTEGER NOT NULL DEFAULT 0,
      tunjangan_transport INTEGER NOT NULL DEFAULT 0,
      tunjangan_makan INTEGER NOT NULL DEFAULT 0,
      bonus INTEGER NOT NULL DEFAULT 0,
      potongan_bpjs INTEGER NOT NULL DEFAULT 0,
      potongan_pajak INTEGER NOT NULL DEFAULT 0,
      potongan_lain INTEGER NOT NULL DEFAULT 0,
      total_gaji INTEGER NOT NULL DEFAULT 0,
      status TEXT NOT NULL DEFAULT 'Draft' CHECK (status IN ('Draft','Diterbitkan','Dibayar')),
      tanggal_bayar TEXT,
      catatan TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(user_id, periode_bulan, periode_tahun)
    );
    CREATE TABLE IF NOT EXISTS work_schedules (
      id TEXT PRIMARY KEY,
      nama TEXT NOT NULL,
      tanggal_mulai TEXT NOT NULL,
      tanggal_selesai TEXT NOT NULL,
      jam_masuk_buka TEXT NOT NULL,
      jam_masuk_tepat TEXT NOT NULL,
      jam_pulang_buka TEXT NOT NULL,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
  ");

  // Isi default pengaturan jam kerja jika belum ada
  $defaults = array(
    'jam_masuk_buka' => '08:00',
    'jam_masuk_tepat' => '08:30',
    'jam_pulang_buka' => '17:00'
  );
  foreach ($defaults as $k => $v) {
    $cek = $konek->prepare("SELECT 1 FROM settings WHERE kunci=?");
    $cek->execute(array($k));
    if (!$cek->fetchColumn()) {
      $ins = $konek->prepare("INSERT INTO settings (kunci, nilai) VALUES (?, ?)");
      $ins->execute(array($k, $v));
    }
  }

  // Tambahkan kolom baru ke tabel users jika belum ada
  $kolom_users = $konek->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
  if (!in_array('departemen_id', $kolom_users)) {
    $konek->exec("ALTER TABLE users ADD COLUMN departemen_id TEXT REFERENCES departments(id) ON DELETE SET NULL");
  }
  if (!in_array('jabatan', $kolom_users)) {
    $konek->exec("ALTER TABLE users ADD COLUMN jabatan TEXT");
  }
  if (!in_array('gaji_pokok', $kolom_users)) {
    $konek->exec("ALTER TABLE users ADD COLUMN gaji_pokok INTEGER DEFAULT 0");
  }

  // Tambahkan kolom bukti ke tabel attendances jika belum ada
  $kolom_att = $konek->query("PRAGMA table_info(attendances)")->fetchAll(PDO::FETCH_COLUMN, 1);
  if (!in_array('bukti', $kolom_att)) {
    $konek->exec("ALTER TABLE attendances ADD COLUMN bukti TEXT");
  }
}

// Catatan percobaan login GAGAL untuk pembatasan (rate limiting). Dibuat otomatis jika belum ada.
$konek->exec("
  CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lingkup TEXT NOT NULL,
    nip TEXT NOT NULL,
    ip TEXT NOT NULL,
    waktu INTEGER NOT NULL
  );
  CREATE INDEX IF NOT EXISTS idx_login_attempts_nip_ip ON login_attempts (lingkup, nip, ip, waktu);
  CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts (lingkup, ip, waktu);
");
