SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('superadmin','guru') NOT NULL DEFAULT 'guru',
  aktif         TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kelas (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kelas VARCHAR(50) NOT NULL UNIQUE,
  tingkat   VARCHAR(12) NOT NULL DEFAULT '-',
  aktif     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS siswa (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(20) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  password_kartu VARBINARY(255) NULL,
  nama_siswa     VARCHAR(100) NOT NULL,
  id_kelas       INT UNSIGNED NOT NULL,
  agama          VARCHAR(20) NULL,
  status         ENUM('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  request_reset  TINYINT(1) NOT NULL DEFAULT 0,
  session_id     VARCHAR(64) NULL,
  last_login_at  DATETIME NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_siswa_kelas FOREIGN KEY (id_kelas) REFERENCES kelas(id) ON DELETE CASCADE,
  INDEX idx_siswa_kelas (id_kelas, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS soal (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode_soal        CHAR(6) NOT NULL UNIQUE,
  jenis_ujian      ENUM('Standar','Kompleks') NOT NULL DEFAULT 'Standar',
  mode_soal        VARCHAR(20) NOT NULL DEFAULT 'PDF',
  nama_soal        VARCHAR(255) NOT NULL,
  mapel            VARCHAR(100) NULL,
  agama_target     VARCHAR(20) NULL,
  target_mode      ENUM('kelas','jenjang','siswa') NOT NULL DEFAULT 'kelas',
  jml_pg           INT NOT NULL DEFAULT 0,
  opsi_pg          TINYINT NOT NULL DEFAULT 4,
  jml_uraian       INT NOT NULL DEFAULT 0,
  bobot_pg         DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  bobot_uraian     DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  komposisi        TEXT NULL,
  durasi           INT NOT NULL DEFAULT 60,
  min_pengerjaan   INT NOT NULL DEFAULT 0,
  link_pdf         VARCHAR(255) NULL,
  tampil_nilai     ENUM('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
  kesempatan       ENUM('Sekali Saja','Dapat Diulang') NOT NULL DEFAULT 'Sekali Saja',
  deteksi_tab      TINYINT(1) NOT NULL DEFAULT 1,
  maks_pelanggaran TINYINT NOT NULL DEFAULT 3,
  aksi_pelanggaran ENUM('peringatan','kunci','akhiri') NOT NULL DEFAULT 'peringatan',
  mulai_at         DATETIME NULL,
  selesai_at       DATETIME NULL,
  status           ENUM('Aktif','Draft','Arsip') NOT NULL DEFAULT 'Draft',
  id_guru          INT UNSIGNED NOT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_soal_guru FOREIGN KEY (id_guru) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_soal_guru_status (id_guru, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS soal_kelas (
  id_soal  INT UNSIGNED NOT NULL,
  id_kelas INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_soal, id_kelas),
  CONSTRAINT fk_sk_soal  FOREIGN KEY (id_soal)  REFERENCES soal(id)  ON DELETE CASCADE,
  CONSTRAINT fk_sk_kelas FOREIGN KEY (id_kelas) REFERENCES kelas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS soal_jenjang (
  id_soal INT UNSIGNED NOT NULL,
  tingkat VARCHAR(12) NOT NULL,
  PRIMARY KEY (id_soal, tingkat),
  CONSTRAINT fk_sj_soal FOREIGN KEY (id_soal) REFERENCES soal(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS soal_siswa (
  id_soal  INT UNSIGNED NOT NULL,
  id_siswa INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_soal, id_siswa),
  CONSTRAINT fk_ss_soal  FOREIGN KEY (id_soal)  REFERENCES soal(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ss_siswa FOREIGN KEY (id_siswa) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS butir_soal (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_soal    INT UNSIGNED NOT NULL,
  nomor_soal INT NOT NULL,
  tipe_soal  ENUM('PG','PG_Kompleks','Benar_Salah','Menjodohkan','Isian','Uraian') NOT NULL DEFAULT 'PG',
  pertanyaan MEDIUMTEXT NULL,
  opsi       JSON NULL,
  kunci      JSON NOT NULL,
  rubrik     TEXT NULL,
  pembahasan TEXT NULL,
  skor_benar DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  skor_salah DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  poin_maks  DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  UNIQUE KEY uq_butir (id_soal, nomor_soal),
  CONSTRAINT fk_butir_soal FOREIGN KEY (id_soal) REFERENCES soal(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS riwayat_ujian (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_soal        INT UNSIGNED NOT NULL,
  id_siswa       INT UNSIGNED NOT NULL,
  percobaan_ke   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  waktu_mulai    DATETIME NOT NULL,
  batas_waktu    DATETIME NOT NULL,
  waktu_selesai  DATETIME NULL,
  status         ENUM('Berlangsung','Menunggu Koreksi','Selesai','Dibatalkan') NOT NULL DEFAULT 'Berlangsung',
  status_koreksi ENUM('Belum','Sebagian','Selesai') NOT NULL DEFAULT 'Belum',
  nilai_pg       DECIMAL(5,2) NULL,
  nilai_uraian   DECIMAL(5,2) NULL,
  nilai_akhir    DECIMAL(5,2) NULL,
  pelanggaran    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  terkunci       TINYINT(1) NOT NULL DEFAULT 0,
  dikunci_pada   DATETIME NULL,
  ip             VARCHAR(45) NULL,
  user_agent     VARCHAR(255) NULL,
  UNIQUE KEY uq_percobaan (id_soal, id_siswa, percobaan_ke),
  INDEX idx_riwayat_soal (id_soal, status),
  INDEX idx_riwayat_siswa (id_siswa),
  CONSTRAINT fk_riwayat_soal  FOREIGN KEY (id_soal)  REFERENCES soal(id)  ON DELETE CASCADE,
  CONSTRAINT fk_riwayat_siswa FOREIGN KEY (id_siswa) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jawaban_siswa (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_riwayat INT UNSIGNED NOT NULL,
  nomor_soal INT NOT NULL,
  jawaban    JSON NULL,
  skor       DECIMAL(6,2) NULL,
  benar      TINYINT(1) NULL,
  catatan    VARCHAR(255) NULL,
  waktu_jawab DATETIME NULL,
  UNIQUE KEY uq_jawaban (id_riwayat, nomor_soal),
  CONSTRAINT fk_jawaban_riwayat FOREIGN KEY (id_riwayat)
    REFERENCES riwayat_ujian(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_pelanggaran (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_riwayat INT UNSIGNED NOT NULL,
  jenis      VARCHAR(50) NOT NULL,
  detail     VARCHAR(255) NULL,
  waktu      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_riwayat FOREIGN KEY (id_riwayat)
    REFERENCES riwayat_ujian(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  peran      VARCHAR(20) NULL,
  aksi       VARCHAR(100) NOT NULL,
  entitas    VARCHAR(50) NULL,
  entitas_id INT UNSIGNED NULL,
  detail     JSON NULL,
  ip         VARCHAR(45) NULL,
  waktu      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pengaturan (
  kunci VARCHAR(50) PRIMARY KEY,
  nilai TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS siswa_sesi (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_siswa   INT UNSIGNED NOT NULL,
  sid        CHAR(32) NOT NULL,
  php_sid    VARCHAR(128) NULL,
  browser    VARCHAR(20) NULL,
  user_agent VARCHAR(250) NULL,
  ip         VARCHAR(45) NULL,
  login_at   DATETIME NOT NULL,
  last_seen  DATETIME NOT NULL,
  UNIQUE KEY uq_sesi_sid (sid),
  KEY idx_sesi_siswa (id_siswa, last_seen),
  CONSTRAINT fk_sesi_siswa FOREIGN KEY (id_siswa) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS soal_kolaborator (
  id_soal    INT UNSIGNED NOT NULL,
  id_user    INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_soal, id_user),
  KEY idx_kolab_user (id_user),
  CONSTRAINT fk_kolab_soal FOREIGN KEY (id_soal) REFERENCES soal(id) ON DELETE CASCADE,
  CONSTRAINT fk_kolab_user FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
