-- ============================================================
-- Query: Cari truk dalam radius tertentu dari suatu koordinat
--        berdasarkan lokasi terbaru (latest timestamp) tiap truk.
-- MySQL / MariaDB
-- Soal No. 2 — Database Design
-- ============================================================

-- Parameter input (ganti sesuai kebutuhan):
--   @center_lat  : Latitude titik pusat
--   @center_lng  : Longitude titik pusat
--   @radius_km   : Radius pencarian dalam kilometer

SET @center_lat = -6.2088;   -- Contoh: Jakarta
SET @center_lng = 106.8456;
SET @radius_km  = 5.0;       -- 5 km


-- ------------------------------------------------------------
-- Query Utama (MySQL 8.0+ dengan ST_Distance_Sphere)
-- ------------------------------------------------------------
-- Strategi optimasi:
-- 1. Subquery dapatkan latest location per truck (MAX timestamp).
-- 2. Bounding-box pre-filter: eliminasi titik di luar kotak kasar
--    (± ~0.045° per 5 km latitude, ± ~0.045° / cos(lat) longitude)
--    untuk mengurangi jumlah pemanggilan ST_Distance_Sphere.
-- 3. Hitung jarak hanya untuk titik yang lolos bounding-box,
--    lalu filter WHERE distance <= @radius_km.
-- 4. Join ke trucks untuk dapatkan license_plate_number.
--
-- Kompleksitas: subquery GROUP BY truck_id akan terbantu oleh
-- composite index idx_truck_ts (truck_id, timestamp DESC).
-- ------------------------------------------------------------

SELECT
    t.truck_id,
    t.license_plate_number,
    latest.timestamp   AS last_seen_at,
    latest.latitude,
    latest.longitude,
    latest.address,
    ROUND(
        ST_Distance_Sphere(
            POINT(@center_lng, @center_lat),
            POINT(latest.longitude, latest.latitude)
        ) / 1000.0,
        2
    ) AS distance_km
FROM
    trucks t
INNER JOIN (
    -- Dapatkan lokasi terbaru setiap truk
    SELECT
        lh.truck_id,
        lh.timestamp,
        lh.latitude,
        lh.longitude,
        lh.address
    FROM
        location_history lh
    INNER JOIN (
        SELECT
            truck_id,
            MAX(timestamp) AS max_ts
        FROM
            location_history
        GROUP BY
            truck_id
    ) latest_ts ON lh.truck_id = latest_ts.truck_id
               AND lh.timestamp = latest_ts.max_ts
) latest ON t.truck_id = latest.truck_id

-- Bounding-box pre-filter (aproksimasi 1° ≈ 111 km)
-- Rumus:
--   Δlat = @radius_km / 111.0
--   Δlng = @radius_km / (111.0 * COS(RADIANS(@center_lat)))
-- Pre-filter ini drastis mengurangi row yang dihitung ST_Distance_Sphere-nya.
WHERE
    latest.latitude  BETWEEN @center_lat - (@radius_km / 111.0)
                         AND @center_lat + (@radius_km / 111.0)
    AND latest.longitude BETWEEN @center_lng - (@radius_km / (111.0 * COS(RADIANS(@center_lat))))
                             AND @center_lng + (@radius_km / (111.0 * COS(RADIANS(@center_lat))))

-- Hanya ambil yang jarak sphere-nya ≤ radius
HAVING
    distance_km <= @radius_km

ORDER BY
    distance_km ASC;


-- ------------------------------------------------------------
-- Alternatif: Query menggunakan Haversine (MySQL semua versi)
--             Tanpa ST_Distance_Sphere (lebih portabel)
-- ------------------------------------------------------------
-- SELECT
--     t.truck_id,
--     t.license_plate_number,
--     latest.timestamp AS last_seen_at,
--     latest.latitude,
--     latest.longitude,
--     latest.address,
--     ROUND(
--         6371 * 2 * ASIN(SQRT(
--             POWER(SIN(RADIANS((latest.latitude - @center_lat) / 2)), 2) +
--             COS(RADIANS(@center_lat)) * COS(RADIANS(latest.latitude)) *
--             POWER(SIN(RADIANS((latest.longitude - @center_lng) / 2)), 2)
--         )),
--         2
--     ) AS distance_km
-- FROM ...
-- (sisanya sama seperti query utama di atas)


-- ------------------------------------------------------------
-- Catatan Performa:
--
-- 1. Bounding-box pre-filter sangat penting:
--    Tanpa pre-filter, ST_Distance_Sphere dihitung untuk SEMUA row
--    latest location. Dengan puluhan juta row, ini akan lambat.
--    Dengan bounding-box, hanya row dalam kotak kasar yang dihitung
--    jarak sphere-nya (biasanya < 1% total row).
--
-- 2. Composite index idx_truck_ts (truck_id, timestamp DESC):
--    MySQL bisa pakai index ini untuk subquery MAX(timestamp) GROUP BY truck_id
--    (loose index scan), mempercepat dapatkan latest location tiap truk.
--
-- 3. Jika data location_history sangat besar (100M+ row), bisa
--    tambahkan tabel `truck_current_location` yang di-update via
--    trigger setiap insert ke location_history, sehingga query
--    radius langsung ke tabel kecil tanpa subquery GROUP BY.
--
-- 4. ST_Distance_Sphere vs Haversine:
--    - ST_Distance_Sphere: akurat, mempertimbangkan bumi elipsoid,
--      tersedia di MySQL 5.7+.
--    - Haversine: aproksimasi bola, error ~0.3%, portabel ke semua versi.
--    - Untuk radius kecil (< 100 km), perbedaan keduanya negligible.
-- ============================================================