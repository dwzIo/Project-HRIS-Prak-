CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nip TEXT NOT NULL UNIQUE,
  nama TEXT NOT NULL,
  email TEXT NOT NULL,
  no_hp TEXT, alamat TEXT, kontak_darurat TEXT, foto TEXT,
  password TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'karyawan' CHECK (role IN ('admin','karyawan')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE change_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  field TEXT NOT NULL, nilai_lama TEXT, nilai_baru TEXT NOT NULL, bukti TEXT,
  status TEXT DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Disetujui','Ditolak')),
  catatan TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE letter_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  jenis TEXT NOT NULL, keperluan TEXT NOT NULL,
  status TEXT DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Diproses','Selesai','Ditolak')),
  file TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  jenis TEXT NOT NULL, file TEXT NOT NULL,
  status TEXT DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Terverifikasi','Perlu Diperbarui')),
  uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP
);
