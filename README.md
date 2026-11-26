# MyHR – Portal HRIS Sederhana

Aplikasi web manajemen SDM (HRIS) berbasis **PHP native + SQLite**. Karyawan dapat melakukan presensi, mengajukan cuti, meminta surat keterangan, dan mengelola dokumen pribadi. Admin (HR) mengatur jadwal kerja, memantau absensi, serta menyetujui berbagai pengajuan.

Tanpa framework dan tanpa server database terpisah: cukup PHP dan satu file database.

## Fitur

### Portal Karyawan
| Menu | Fungsi |
| --- | --- |
| **Dashboard** | Ringkasan sisa cuti, pengajuan yang sedang diproses, serta riwayat cuti dan surat terbaru. |
| **Absensi Saya** | Absen masuk dan pulang dengan jam digital. Status otomatis *Hadir* atau *Terlambat*. Bisa mengirim pemberitahuan *Izin* atau *Sakit* beserta bukti (PDF/JPG/PNG, maks 5 MB). Tersedia rekap bulanan. |
| **Pengajuan Cuti** | Cuti Tahunan, Sakit, Menikah, Melahirkan, dan Khusus. Kuota cuti tahunan 12 hari per tahun, dengan lampiran bukti opsional. Pengajuan yang masih menunggu bisa dibatalkan. |
| **Profil Saya** | Ganti foto profil (dengan fitur potong foto) dan password. Perubahan alamat, nomor HP, dan email harus lewat pengajuan ke admin dengan bukti pendukung. Setelah ada perubahan yang disetujui, ada masa tunggu 90 hari sebelum pengajuan berikutnya. |
| **Surat Keterangan** | Meminta surat keterangan kerja, surat pengantar, surat rekomendasi, surat izin pendidikan lanjutan, atau salinan kontrak kerja, lalu mengunduh hasilnya setelah selesai. |
| **Dokumen Saya** | Mengunggah KTP, Kartu Keluarga, NPWP, Ijazah, Sertifikat, BPJS, dan Kontrak kerja (PDF/JPG/PNG, maks 3 MB). |
| **Lupa password** | Karyawan mengajukan reset (NIP + email), menunggu persetujuan admin, lalu mengisi password baru. |

### Portal Admin
| Menu | Fungsi |
| --- | --- |
| **Dashboard Admin** | Ringkasan data karyawan serta cuti dan surat yang menunggu tindakan. |
| **Data Karyawan** | Tambah, ubah, dan hapus akun karyawan atau admin. Akun yang sedang dipakai tidak bisa dihapus. |
| **Rekap Absensi** | Mengatur jadwal kerja per rentang tanggal (jam buka presensi, batas tepat waktu, jam pulang), mengembalikan jam ke standar, menambah absensi manual, dan menghapus catatan. |
| **Persetujuan Cuti** | Menyetujui atau menolak cuti dengan catatan, dengan filter status dan pencarian. |
| **Pengajuan Lain** | Memproses perubahan data profil, reset password, pengajuan surat (status *Diproses / Selesai / Ditolak*, dengan unggah file surat PDF/DOC/DOCX), serta memeriksa dokumen karyawan dengan pratinjau berkas. |

## Aturan Bisnis Penting

- **Jam kerja standar:** presensi dibuka 08:00, tepat waktu sampai 08:30, dan jam pulang mulai 17:00 (WIB). Jam yang berlaku selalu berasal dari jadwal yang diatur admin.
- **Tanggal di luar jadwal dianggap libur.** Karyawan tidak bisa absen dan tidak ada status Alfa otomatis.
- **Alfa otomatis:** pada hari kerja, setelah jam pulang terlewati, karyawan yang belum punya catatan absensi tercatat *Alfa*.
- **Zona waktu:** `Asia/Jakarta`.
- **Setiap karyawan hanya punya satu catatan absensi per tanggal.**

## Teknologi

- PHP 8+ dengan ekstensi **PDO SQLite** (`pdo_sqlite`)
- SQLite 3 (file `database/hr.sqlite`)
- Tailwind CSS via CDN (perlu koneksi internet saat membuka aplikasi)
- JavaScript vanilla (`public/assets/app.js`)

## Struktur Proyek

```
project_localhost/
├── database/
│   ├── hr.sqlite        # database (dibuat otomatis dari schema.sql jika belum ada)
│   ├── schema.sql       # skema tabel
│   └── .htaccess        # blokir akses langsung dari web
├── src/
│   ├── koneksi.php      # koneksi PDO + pembuatan database & akun admin awal
│   ├── auth.php         # session, login, peran, helper query, upload, alfa otomatis
│   └── layout.php       # kerangka halaman, menu, gaya, dan komponen UI
├── public/              # document root web server
│   ├── index.php        # pengalihan ke halaman login
│   ├── login*.php       # login (pilihan peran), login karyawan, login admin
│   ├── lupa_password.php
│   ├── dashboard.php
│   ├── absensi.php, cuti.php, profil.php, surat.php, dokumen.php      # karyawan
│   ├── admin_karyawan.php, admin_absensi.php,
│   │   admin_cuti.php, admin_pengajuan.php                            # admin
│   ├── unduh.php        # penyaji file upload dengan pengecekan hak akses
│   ├── logout.php
│   └── assets/          # app.js dan gambar
└── uploads/             # foto, dokumen, bukti, surat (tidak bisa diakses langsung)
```

## Cara Menjalankan

### Opsi 1 – PHP built-in server (paling cepat)

```bash
cd project_localhost
php -S localhost:8000 -t public
```

Buka <http://localhost:8000>.

### Opsi 2 – XAMPP / Laragon / Apache

1. Salin folder `project_localhost` ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Pastikan ekstensi `pdo_sqlite` aktif di `php.ini`.
3. Buka `http://localhost/project_localhost/public/`.

> Pastikan folder `database/` dan `uploads/` bisa ditulis oleh PHP.

## Akun dan Login

Jika `database/hr.sqlite` belum ada, aplikasi membuatnya otomatis dari `schema.sql` beserta satu akun admin awal. Akun karyawan dibuat oleh admin lewat menu *Data Karyawan*. Login admin dan karyawan memakai halaman terpisah, dan akun hanya bisa masuk lewat halaman sesuai perannya.

Untuk mengulang dari awal, hapus `database/hr.sqlite` (dan isi `uploads/`), lalu buka aplikasi lagi.

## Skema Database

| Tabel | Isi |
| --- | --- |
| `users` | Akun, profil, password (hash), dan role (`admin` / `karyawan`). |
| `attendances` | Absensi harian (Hadir, Izin, Sakit, Terlambat, Alfa), unik per user per tanggal. |
| `work_schedules` | Jadwal jam kerja per rentang tanggal. |
| `leave_requests` | Pengajuan cuti dan keputusan admin. |
| `change_requests` | Pengajuan perubahan data profil. |
| `letter_requests` | Permintaan surat keterangan dan file hasilnya. |
| `documents` | Dokumen pribadi karyawan beserta status verifikasi. |
| `password_resets` | Permintaan reset password yang perlu persetujuan admin. |

Semua `id` berupa teks acak 16 karakter (bukan auto increment).

## Keamanan

- Password disimpan dengan `password_hash()` dan diverifikasi dengan `password_verify()`.
- Semua query memakai *prepared statement* (PDO), dan keluaran HTML di-*escape*.
- `session_regenerate_id()` dipanggil saat login, dan peran selalu dibaca dari database, bukan dari session.
- File upload diberi nama acak, dibatasi tipe dan ukurannya, dan hanya bisa diakses lewat `unduh.php` oleh pemilik file atau admin.
- `database/` dan `uploads/` diblokir dari akses langsung lewat `.htaccess` (pada Apache). Pada server lain, atur aturan yang setara.

## Catatan

- Batas ukuran upload juga dipengaruhi `upload_max_filesize` dan `post_max_size` di `php.ini`.
- Aplikasi ini ditujukan untuk pembelajaran dan penggunaan skala kecil. Untuk produksi, aktifkan HTTPS dan tambahkan perlindungan CSRF.
