-- ============================================================
-- Database Migration: Trucks & Location History
-- MySQL / MariaDB
-- Soal No. 2 — Database Design
-- ============================================================

-- ------------------------------------------------------------
-- 1. Table: trucks
--    Menyimpan data master truk.
--    Sering di-query exact-match by license_plate_number.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trucks` (
    `truck_id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `license_plate_number`  VARCHAR(20)     NOT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`truck_id`),

    -- Karena license_plate_number sering digunakan untuk lookup exact-match
    -- dan nilainya unik per truk, kita pakai UNIQUE index.
    -- VARCHAR(20) cukup untuk plat nomor Indonesia "B 1234 ABC".
    UNIQUE INDEX `idx_license_plate` (`license_plate_number`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Master data truk';


-- ------------------------------------------------------------
-- 2. Table: location_history
--    Menyimpan riwayat lokasi truk (append-only).
--    Akan berisi puluhan juta baris.
--    Current location = row dengan timestamp terbaru per truck.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `location_history` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `truck_id`      BIGINT UNSIGNED NOT NULL,
    `timestamp`     DATETIME(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `latitude`      DECIMAL(10,7)   NOT NULL COMMENT 'Latitude in WGS84, e.g. -6.2088',
    `longitude`     DECIMAL(10,7)   NOT NULL COMMENT 'Longitude in WGS84, e.g. 106.8456',
    `address`       VARCHAR(255)    DEFAULT NULL,

    PRIMARY KEY (`id`),

    -- Composite index: mempercepat query "latest location per truck"
    -- Query: WHERE truck_id = ? ORDER BY timestamp DESC LIMIT 1
    -- Juga bermanfaat untuk subquery MAX(timestamp) GROUP BY truck_id
    INDEX `idx_truck_ts` (`truck_id`, `timestamp` DESC),

    -- Spatial index: mempercepat bounding-box / radius query dengan ST_Distance_Sphere
    -- Titik lokasi disimpan sebagai POINT(lng, lat) untuk kompatibilitas spatial MySQL
    -- (perhatikan: POINT(X, Y) = POINT(longitude, latitude))
    SPATIAL INDEX `idx_spatial` ((ST_PointFromText(CONCAT('POINT(', `longitude`, ' ', `latitude`, ')'), 4326))),

    -- Untuk query bounding-box manual (antara WHERE lat BETWEEN ... AND lng BETWEEN ...)
    -- composite index ini bisa membantu, tapi spatial index biasanya lebih efisien.
    -- INDEX `idx_lat_lng` (`latitude`, `longitude`)

    CONSTRAINT `fk_location_truck`
        FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`truck_id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Riwayat lokasi truk (append-only)';


-- ------------------------------------------------------------
-- Notes:
--
-- 1. Tipe data:
--    - BIGINT UNSIGNED: kapasitas hingga ~18 quintillion, cukup untuk puluhan juta row.
--    - DECIMAL(10,7): presisi ~1.1 cm di equator.
--      Menghindari FLOAT/DOUBLE yang bisa kehilangan presisi saat kalkulasi.
--    - DATETIME(0): tanpa fractional seconds, cukup untuk tracking lokasi.
--    - utf8mb4: mendukung emoji dan karakter internasional.
--
-- 2. Indeks:
--    - idx_license_plate (UNIQUE): optimal untuk exact-match lookup plat nomor.
--    - idx_truck_ts (truck_id, timestamp DESC): covering index untuk "latest location per truck"
--      dan join ke trucks.
--    - SPATIAL index: mempercepat query radius dengan ST_Distance_Sphere atau MBRContains.
--
-- 3. Tradeoff spatial index vs manual bounding-box:
--    - Spatial index bisa langsung digunakan dengan ST_Within / ST_Distance.
--    - Namun jika MySQL versi lama, bisa fallback ke bounding-box manual dengan
--      composite index (latitude, longitude) + kalkulasi Haversine di aplikasi.
--    - Untuk MariaDB, syntax spatial mungkin sedikit berbeda (pakai GEOMETRY type).
--
-- 4. Jika menggunakan MySQL < 5.7 atau MariaDB:
--    - SPATIAL index hanya bisa dibuat pada kolom bertipe GEOMETRY.
--    - Alternatif: tambahkan kolom `coordinates POINT NOT NULL SRID 4326` 
--      yang di-populate via trigger atau aplikasi.
-- ============================================================