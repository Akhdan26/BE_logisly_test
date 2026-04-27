-- ============================================================
-- Cari truk dalam radius tertentu (berdasarkan lokasi terbaru)
-- MySQL / MariaDB
-- ============================================================

-- Ganti parameter ini sesuai kebutuhan
SET @center_lat = -6.2088;   -- (contoh: Jakarta)
SET @center_lng = 106.8456;
SET @radius_km  = 5.0;       -- 5 km


-- ============================================================
-- Query Utama (MySQL 8.0+ / ST_Distance_Sphere)
-- ============================================================
--
-- Flow optimasi:
-- 1. Dapetin lokasi terbaru tiap truk (MAX timestamp).
-- 2. Bounding-box pre-filter: buang titik yang jelas di luar radius
--    (1° ≈ 111 km, adjust buat longitude pake cos(lat)).
-- 3. Hitung jarak akurat cuma buat row yang lolos pre-filter.
-- 4. Join ke trucks buat ambil license_plate_number.
--
-- Subquery GROUP BY truck_id dibantu composite index
-- idx_truck_ts (truck_id, timestamp DESC) dengan loose index scan.
-- ============================================================

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

-- Bounding-box pre-filter (1° lat ≈ 111 km).
-- Delta latitude = radius / 111.0
-- Delta longitude = radius / (111.0 * cos(lat)) — mengecil makin jauh dari equator
-- Pre-filter ini ngurangin drastis row yang perlu dihitung jarak akuratnya.
WHERE
    latest.latitude  BETWEEN @center_lat - (@radius_km / 111.0)
                         AND @center_lat + (@radius_km / 111.0)
    AND latest.longitude BETWEEN @center_lng - (@radius_km / (111.0 * COS(RADIANS(@center_lat))))
                             AND @center_lng + (@radius_km / (111.0 * COS(RADIANS(@center_lat))))

-- Filter final: cuma yang jarak akuratnya ≤ radius
HAVING
    distance_km <= @radius_km

ORDER BY
    distance_km ASC;


-- ============================================================
-- Alternatif: Haversine (MySQL semua versi, tanpa ST_Distance_Sphere)
-- Lebih portabel, error ~0.3% (cukup buat radius < 100 km)
-- ============================================================
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


-- ============================================================
-- Catatan:
--
-- 1. Bounding-box pre-filter itu wajib.
--    Tanpa ini, ST_Distance_Sphere (atau Haversine) dihitung untuk
--    SEMUA row. Dengan puluhan juta row, query bisa makan detik-menit.
--    Pre-filter bikin jumlah row yang dihitung jaraknya cuma < 1% total.
--
-- 2. idx_truck_ts bikin subquery MAX(timestamp) GROUP BY truck_id
--    bisa pake loose index scan (lompat langsung ke timestamp terbaru
--    tiap truck tanpa full scan).
--
-- 3. Kalau location_history udah 100M+ row, bisa consider tabel
--    denormalized `truck_current_location` (update via trigger)
--    biar nggak perlu subquery GROUP BY lagi.
--
-- 4. ST_Distance_Sphere vs Haversine:
--    - ST_Distance_Sphere: elipsoid, lebih akurat (MySQL 5.7+)
--    - Haversine: aproksimasi bola, error ~0.3%, portabel semua versi
--    - Untuk radius kecil (< 100 km), bedanya nggak significant
-- ============================================================
