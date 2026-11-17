<?php
// Koneksi SQLite lewat PDO. Database = file database/hr.sqlite
// (sudah disertakan; dibuat otomatis dari schema.sql jika hilang).
$file = "database/hr.sqlite";
$baru = !file_exists($file);
$konek = new PDO("sqlite:" . $file);
$konek->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$konek->exec("PRAGMA foreign_keys = ON");
if ($baru) {
  $konek->exec(file_get_contents(__DIR__ . "/database/schema.sql"));
  // Akun awal: NIP "admin", password "admin123" (di-hash), role admin
  $st = $konek->prepare("INSERT INTO users (id,nip,nama,email,password,role) VALUES (?,'admin','Admin','hr@perusahaan.test',?,'admin')");
  $st->execute([bin2hex(random_bytes(8)), password_hash("admin123", PASSWORD_DEFAULT)]);
}

// Migrasi untuk database lama: tabel password_resets versi lama (tanpa kolom "status") dibuat ulang.
// Isinya hanya permintaan reset sementara, jadi data karyawan lain tidak terpengaruh.
$kolom_reset = $konek->query("PRAGMA table_info(password_resets)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (count($kolom_reset) > 0 && !in_array('status', $kolom_reset)) {
  $konek->exec("DROP TABLE password_resets");
  $konek->exec("CREATE TABLE password_resets (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Disetujui','Ditolak','Selesai')),
    catatan TEXT,
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL
  )");
}

// Migrasi untuk database lama: peran "superadmin" dihapus dan digabung ke "admin".
// Tabel users dibuat ulang (semua data dipertahankan); akun superadmin otomatis menjadi admin.
$sql_users = $konek->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
if ($sql_users && strpos($sql_users, 'superadmin') !== false) {
  $konek->exec("PRAGMA foreign_keys = OFF");
  $konek->beginTransaction();
  $konek->exec("CREATE TABLE users_baru (
    id TEXT PRIMARY KEY,
    nip TEXT NOT NULL UNIQUE,
    nama TEXT NOT NULL,
    email TEXT NOT NULL,
    no_hp TEXT, alamat TEXT, kontak_darurat TEXT, foto TEXT,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'karyawan' CHECK (role IN ('admin','karyawan')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
  )");
  $konek->exec("INSERT INTO users_baru (id,nip,nama,email,no_hp,alamat,kontak_darurat,foto,password,role,created_at)
                SELECT id,CAST(nip AS TEXT),CASE WHEN role='superadmin' AND nama='Super Admin' THEN 'Admin' ELSE nama END,email,no_hp,alamat,kontak_darurat,foto,password,
                       CASE WHEN role='superadmin' THEN 'admin' ELSE role END,created_at FROM users");
  $konek->exec("DROP TABLE users");
  $konek->exec("ALTER TABLE users_baru RENAME TO users");
  $konek->commit();
  $konek->exec("PRAGMA foreign_keys = ON");
}
