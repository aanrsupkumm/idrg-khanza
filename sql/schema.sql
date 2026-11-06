-- Jalankan pada database Khanza (sik). Tabel ber-prefix idrg_ agar tidak bentrok.
CREATE TABLE IF NOT EXISTS idrg_step (
  no_rawat      VARCHAR(17) NOT NULL,
  no_sep        VARCHAR(40) NOT NULL,
  step          VARCHAR(30) NOT NULL,
  status        ENUM('ok','error','skip') NOT NULL,
  message       VARCHAR(500) NOT NULL DEFAULT '',
  response_json MEDIUMTEXT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (no_rawat, step)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS idrg_log (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  method        VARCHAR(60) NOT NULL,
  nomor_sep     VARCHAR(40) NULL,
  http_code     INT NULL,
  code          INT NULL,
  error_no      VARCHAR(10) NULL,
  message       VARCHAR(500) NULL,
  request_json  MEDIUMTEXT NULL,
  response_json MEDIUMTEXT NULL,
  ms            INT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (nomor_sep), KEY (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lampiran berkas digital tambahan (juga dibuat otomatis oleh aplikasi bila belum ada).
CREATE TABLE IF NOT EXISTS idrg_berkas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  no_rawat    VARCHAR(25) NOT NULL,
  judul       VARCHAR(150) NOT NULL,
  nama_asli   VARCHAR(255) NOT NULL,
  nama_simpan VARCHAR(64) NOT NULL,
  mime        VARCHAR(60) NOT NULL,
  ukuran      INT NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (no_rawat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
