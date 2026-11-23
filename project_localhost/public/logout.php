<?php
session_start();
$peran = '';
if (isset($_SESSION['role'])) {
  $peran = $_SESSION['role'];
}
session_unset();
session_destroy();
if ($peran === 'admin') {
  header("Location: login_admin.php");
} elseif ($peran === 'karyawan') {
  header("Location: login_karyawan.php");
} else {
  header("Location: login.php");
}
