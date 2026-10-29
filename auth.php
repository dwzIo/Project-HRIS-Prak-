<?php
// Wajib di-include paling atas setiap halaman (session_start harus di baris awal)
session_start();
include_once "koneksi.php";

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function tgl($s) { return date('d M Y', strtotime($s)); }
function wajib_login() { if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; } }
function wajib_admin() { wajib_login(); if ($_SESSION['role'] !== 'admin') { header("Location: dashboard.php"); exit; } }
function flash($teks = null, $tipe = 'success') {
  if ($teks !== null) { $_SESSION['flash'] = [$teks, $tipe]; return null; }
  if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
  return null;
}
// Query dengan prepared statement PDO (parameter $types dipertahankan agar halaman lain tidak berubah)
function q($sql, $types = "", $params = []) {
  global $konek;
  $st = $konek->prepare($sql);
  $st->execute($params);
  return $st;
}
function ambil($r) { return $r->fetch(PDO::FETCH_ASSOC) ?: null; }
function semua($r) { return $r->fetchAll(PDO::FETCH_ASSOC); }
function hitung($sql, $t = "", $p = []) { return (int) q($sql, $t, $p)->fetchColumn(); }
// Upload file: cek ekstensi & ukuran, nama diganti acak
function upload($f, $dir, $ext_ok, $maks = 2097152) {
  if (!isset($f) || $f['error'] !== 0) return [null, "Pilih file terlebih dahulu."];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $ext_ok)) return [null, "Tipe file harus: " . implode(", ", $ext_ok) . "."];
  if ($f['size'] > $maks) return [null, "Ukuran file maksimal " . ($maks / 1048576) . " MB."];
  $nama = bin2hex(random_bytes(8)) . "." . $ext;
  move_uploaded_file($f['tmp_name'], "uploads/$dir/$nama");
  return [$nama, null];
}
function badge($s) {
  $m = ['Menunggu'=>'warning text-dark','Diproses'=>'info text-dark','Disetujui'=>'success','Selesai'=>'success','Terverifikasi'=>'success','Ditolak'=>'danger','Perlu Diperbarui'=>'danger'];
  return "<span class='badge bg-" . ($m[$s] ?? 'secondary') . "'>" . e($s) . "</span>";
}
