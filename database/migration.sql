-- ============================================================
-- Database Migration: Trucks & Location History
-- MySQL 8.0+ / MariaDB
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

    -- Generated column: POINT dari lat/lng, diperlukan untuk spatial index
    -- MySQL SRID 4326 expects axis order (latitude, longitude), conforming to EPSG:4326.
    -- POINT(lat, lng) — reversed from common GIS (lng, lat) convention.
    `coordinates`   POINT GENERATED ALWAYS AS (ST_PointFromText(
                        CONCAT('POINT(', `latitude`, ' ', `longitude`, ')'), 4326
                    )) STORED NOT NULL
                    COMMENT 'Koordinat geografis WGS84 (lat, lng)',

    PRIMARY KEY (`id`),

    -- Composite index: mempercepat query "latest location per truck"
    -- Query: WHERE truck_id = ? ORDER BY timestamp DESC LIMIT 1
    -- Juga bermanfaat untuk subquery MAX(timestamp) GROUP BY truck_id
    INDEX `idx_truck_ts` (`truck_id`, `timestamp` DESC),

    -- Spatial index: mempercepat bounding-box / radius query dengan ST_Distance_Sphere
    -- R-tree index pada generated column bertipe POINT
    SPATIAL INDEX `idx_spatial` (`coordinates`),

    CONSTRAINT `fk_location_truck`
        FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`truck_id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Riwayat lokasi truk (append-only)';


-- ============================================================
-- Catatan desain:
--
-- 1. Tipe data:
--    - BIGINT UNSIGNED: kapasitas hingga ~18 quintillion, cukup untuk puluhan juta row.
--    - DECIMAL(10,7): presisi ~1.1 cm di equator.
--      Menghindari FLOAT/DOUBLE yang bisa kehilangan presisi saat kalkulasi.
--    - DATETIME(0): tanpa fractional seconds, cukup untuk tracking lokasi.
--    - utf8mb4: mendukung emoji dan karakter internasional.
--
-- 2. Generated column `coordinates` (POINT SRID 4326):
--    - STORED: dihitung saat INSERT/UPDATE, disimpan fisik di disk.
--      Cocok untuk append-only table (insert-heavy, jarang update).
--      Space overhead ~25 bytes per row (POINT internal format).
--    - Jika khawatir storage, bisa pakai VIRTUAL (dihitung on-the-fly),
--      tapi spatial index tetap butuh STORED agar bisa di-index.
--
-- 3. Indeks:
--    - idx_license_plate (UNIQUE): optimal untuk exact-match lookup plat nomor.
--    - idx_truck_ts (truck_id, timestamp DESC): covering index untuk "latest location per truck"
--      dan join ke trucks.
--    - idx_spatial (SPATIAL): R-tree index pada coordinates, mempercepat query
--      ST_Distance_Sphere / MBRContains untuk pencarian radius.
--
-- 4. Tradeoff STORED vs VIRTUAL generated column:
--    - STORED: lebih cepat dibaca (tidak dihitung ulang), bisa di-index (spatial).
--      Overhead write ~25 byte per row.
--    - VIRTUAL: hemat storage (tidak disimpan), tapi tidak bisa spatial index.
--    - Untuk append-only table: STORED lebih baik karena write-once, read-many.
-- ============================================================