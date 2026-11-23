-- Semua id berupa teks acak (dibuat di PHP dengan buat_id()), bukan auto increment
CREATE TABLE users (
  id TEXT PRIMARY KEY,
  nip TEXT NOT NULL UNIQUE,
  nama TEXT NOT NULL,
  email TEXT NOT NULL,
  no_hp TEXT, alamat TEXT, kontak_darurat TEXT, foto TEXT,
  password TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'karyawan' CHECK (role IN ('admin','karyawan')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE change_requests (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  field TEXT NOT NULL, nilai_lama TEXT, nilai_baru TEXT NOT NULL, bukti TEXT,
  status TEXT DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Disetujui','Ditolak')),
  catatan TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE letter_requests (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  jenis TEXT NOT NULL, keperluan TEXT NOT NULL,
  status TEXT DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Diproses','Selesai','Ditolak')),
  file TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE documents (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  jenis TEXT NOT NULL, file TEXT NOT NULL,
  status TEXT DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Terverifikasi','Perlu Diperbarui')),
  uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP
);
-- Permintaan reset password: harus disetujui admin dulu sebelum password boleh diganti
CREATE TABLE password_resets (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Disetujui','Ditolak','Selesai')),
  catatan TEXT,
  created_at INTEGER NOT NULL,
  expires_at INTEGER NOT NULL
);
