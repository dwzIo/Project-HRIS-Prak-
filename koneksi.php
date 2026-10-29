<?php
// Koneksi SQLite lewat PDO. File database dibuat otomatis saat pertama dijalankan.
$file = __DIR__ . "/database/hr.db";
$baru = !file_exists($file);
$konek = new PDO("sqlite:" . $file);
$konek->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$konek->exec("PRAGMA foreign_keys = ON");
if ($baru) {
  $konek->exec(file_get_contents(__DIR__ . "/database/schema.sql"));
  // Akun admin awal: NIP "admin", password "admin123" (di-hash)
  $st = $konek->prepare("INSERT INTO users (nip,nama,email,password,role) VALUES ('admin','Admin HR','hr@perusahaan.test',?,'admin')");
  $st->execute([password_hash("admin123", PASSWORD_DEFAULT)]);
}
