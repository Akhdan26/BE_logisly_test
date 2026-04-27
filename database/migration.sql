-- ============================================================
-- Trucks & Location History Migration
-- MySQL 8.0+ / MariaDB
-- ============================================================

-- Master data truk.
-- Sering di-query exact-match by license_plate_number.
CREATE TABLE IF NOT EXISTS `trucks` (
    `truck_id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `license_plate_number`  VARCHAR(20)     NOT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`truck_id`),

    -- Lookup exact-match plat nomor (query paling sering)
    UNIQUE INDEX `idx_license_plate` (`license_plate_number`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Master data truk';


-- Riwayat lokasi truk (append-only, bakal puluhan juta row).
-- Lokasi terbaru per truk = row dengan timestamp paling gede.
CREATE TABLE IF NOT EXISTS `location_history` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `truck_id`      BIGINT UNSIGNED NOT NULL,
    `timestamp`     DATETIME(0)     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `latitude`      DECIMAL(10,7)   NOT NULL COMMENT 'Latitude WGS84, misal -6.2088',
    `longitude`     DECIMAL(10,7)   NOT NULL COMMENT 'Longitude WGS84, misal 106.8456',
    `address`       VARCHAR(255)    DEFAULT NULL,

    -- Generated column buat spatial index.
    -- ST_PointFromText dengan SRID 4326 (WGS84).
    `coordinates`   POINT GENERATED ALWAYS AS (ST_PointFromText(
                        CONCAT('POINT(', `latitude`, ' ', `longitude`, ')'), 4326
                    )) STORED NOT NULL
                    COMMENT 'Koordinat geografis WGS84 (lat, lng)',

    PRIMARY KEY (`id`),

    -- Composite index buat "latest location per truck"
    -- Support loose index scan saat GROUP BY truck_id
    INDEX `idx_truck_ts` (`truck_id`, `timestamp` DESC),

    -- Spatial R-tree index buat query radius
    SPATIAL INDEX `idx_spatial` (`coordinates`),

    CONSTRAINT `fk_location_truck`
        FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`truck_id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Riwayat lokasi truk (append-only)';


-- ============================================================
-- Catatan:
--
-- 1. BIGINT UNSIGNED: kapasitas ~18 quintillion, aman buat puluhan juta row.
--    INT (max ~2.1 milyar) bisa abis dalam beberapa tahun.
--
-- 2. DECIMAL(10,7): presisi ~1.1 cm di equator.
--    Jangan pake FLOAT/DOUBLE — FLOAT presisi cuma ~1.7 meter.
--
-- 3. DATETIME(0): tanpa fractional seconds, hemat 1 byte per row.
--    Tracking lokasi truk nggak butuh presisi milidetik.
--
-- 4. Generated column STORED (bukan VIRTUAL):
--    - STORED: disimpan fisik, bisa di-index spatial. Overhead ~25 byte/row.
--    - VIRTUAL: hemat space tapi nggak bisa spatial index.
--    - Untuk append-only (insert-heavy, jarang update): STORED lebih cocok.
--
-- 5. idx_truck_ts (truck_id, timestamp DESC):
--    Index ini bikin query "latest location" cepet karena MySQL bisa
--    pake loose index scan — langsung lompat ke timestamp terbaru tiap truck.
-- ============================================================
