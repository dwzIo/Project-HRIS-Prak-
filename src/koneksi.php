<?php
$file = __DIR__ . "/../database/hr.sqlite";
$baru = !file_exists($file);
$konek = new PDO("sqlite:" . $file);
$konek->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$konek->exec("PRAGMA foreign_keys = ON");

if ($baru) {
  $konek->exec(file_get_contents(__DIR__ . "/../database/schema.sql"));
  $st = $konek->prepare("INSERT INTO users (id,nip,nama,email,password,role) VALUES (?,'admin','Admin','hr@perusahaan.test',?,'admin')");
  $st->execute([bin2hex(random_bytes(8)), password_hash("admin123", PASSWORD_DEFAULT)]);
}