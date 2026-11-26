CREATE TABLE users (
  id TEXT PRIMARY KEY,
  nip TEXT NOT NULL UNIQUE,
  nama TEXT NOT NULL,
  email TEXT NOT NULL,
  no_hp TEXT, alamat TEXT, foto TEXT,
  password TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'karyawan' CHECK (role IN ('admin','karyawan')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX idx_users_email ON users (lower(email));
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
CREATE TABLE password_resets (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'Menunggu' CHECK (status IN ('Menunggu','Disetujui','Ditolak','Selesai')),
  catatan TEXT,
  created_at INTEGER NOT NULL,
  expires_at INTEGER NOT NULL
);

CREATE TABLE work_schedules (
  id TEXT PRIMARY KEY,
  nama TEXT NOT NULL,
  tanggal_mulai TEXT NOT NULL,
  tanggal_selesai TEXT NOT NULL,
  jam_masuk_buka TEXT NOT NULL,
  jam_masuk_tepat TEXT NOT NULL,
  jam_pulang_buka TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_work_schedules_rentang ON work_schedules (tanggal_mulai, tanggal_selesai);

CREATE TABLE attendances (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  tanggal TEXT NOT NULL,
  jam_masuk TEXT,
  jam_pulang TEXT,
  status TEXT NOT NULL DEFAULT 'Hadir' CHECK (status IN ('Hadir','Izin','Sakit','Terlambat','Alfa')),
  catatan TEXT,
  bukti TEXT,
  schedule_id TEXT REFERENCES work_schedules(id) ON DELETE SET NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(user_id, tanggal)
);
CREATE INDEX idx_attendances_schedule ON attendances (schedule_id);

CREATE TABLE leave_requests (
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
