# Soal No. 2 — Database Design

## Skema Tabel

### `trucks`
Nyimpen data master truk. Simpel, cuma 3 kolom:

- `truck_id` — `BIGINT UNSIGNED`, auto-increment. Kenapa BIGINT? Karena anticipate growth. INT mentok di ~2.1 milyar — keliatannya banyak, tapi buat sistem logistik yang bisa punya jutaan truk registered, mending aman dari awal.
- `license_plate_number` — `VARCHAR(20)`. Plat nomor Indonesia "B 1234 ABC" panjangnya cuma 11 karakter, tapi VARCHAR(20) ngasih toleransi buat variasi format. VARCHAR juga lebih hemat storage dibanding CHAR karena cuma nyimpen panjang aktual + 1-2 byte overhead.
- `created_at` — `TIMESTAMP`, auto `CURRENT_TIMESTAMP`.

Index: `UNIQUE idx_license_plate` — karena query exact-match `WHERE license_plate_number = ?` ini yang paling sering dipake, dan plat nomor harus unik.

### `location_history`
Append-only table buat nyimpen riwayat lokasi. Bakal jadi table paling gede (puluhan juta row).

- `id` — `BIGINT UNSIGNED`, auto-increment. Sama alesannya kayak truck_id, cuma disini lebih kritis karena table ini append terus tiap beberapa menit.
- `truck_id` — `BIGINT UNSIGNED`, FK ke trucks.
- `timestamp` — `DATETIME(0)`. Tanpa fractional seconds. Tracking lokasi truk nggak butuh presisi milidetik, dan ini hemat 1 byte per row.
- `latitude`, `longitude` — `DECIMAL(10,7)`. Ini penting: jangan pake FLOAT/DOUBLE. FLOAT presisinya cuma ~1.7 meter — bisa bikin truk "loncat" ke jalan sebelah pas geocoding. DECIMAL(10,7) presisi ~1.1 cm di equator, cukup akurat.
- `address` — `VARCHAR(255)`. Hasil reverse-geocoding, biasanya singkat.
- `coordinates` — Generated column `POINT SRID 4326`. Dihitung otomatis dari lat/lng, dipake buat spatial index.

## Index Strategy

Ada 3 index:

### 1. `UNIQUE idx_license_plate` (`license_plate_number`)
B-tree index biasa. Karena query exact-match sering banget, unique index langsung dapet row dalam O(log n). Plus mencegah duplikasi plat nomor.

### 2. `INDEX idx_truck_ts` (`truck_id`, `timestamp DESC`)
Ini index paling penting buat performa. Composite index ini ngasih dua benefit:
- Query "latest location per truck" (`WHERE truck_id = ? ORDER BY timestamp DESC LIMIT 1`) langsung ke-cover — MySQL bisa baca dari index tanpa lookup ke table.
- Buat `GROUP BY truck_id` dengan `MAX(timestamp)`, MySQL bisa pake "loose index scan" — lompat langsung ke timestamp terbaru tiap truck_id. Tanpa index ini, query bakal full scan puluhan juta row.

### 3. `SPATIAL INDEX idx_spatial` (`coordinates`)
R-tree index buat query radius. B-tree biasa nggak efektif buat query 2D karena harus cek dua dimensi. R-tree nge-group titik berdasarkan kedekatan geografis.

> Technical note: Beberapa versi MySQL/MariaDB cuma support spatial index di kolom `GEOMETRY`, bukan expression. Di migrasi gue pake generated column `coordinates` (STORED) biar kompatibel.

### Yang Sengaja Nggak Dibikin: Composite B-tree `(latitude, longitude)`
Ini common mistake. B-tree komposit di (lat, lng) nggak efektif buat radius query karena MySQL cuma bisa pake prefix index (latitude doang). Longitude tetep di-scan manual. Spatial R-tree jauh lebih superior.

## Query Radius: 3-Layer Optimization

File `query_nearest_trucks.sql` pake 3 layer:

**Layer 1 — Ambil lokasi terbaru doang**
Subquery `MAX(timestamp) GROUP BY truck_id`. Tanpa ini, query bakal hitung jarak untuk SEMUA row history (puluhan juta), bukan cuma yang terbaru (puluhan ribu). Index `idx_truck_ts` bikin subquery ini cepet.

**Layer 2 — Bounding-box pre-filter**
Sebelum hitung jarak akurat yang mahal (trigonometri), filter kasar dulu pake bounding box:
```sql
lat BETWEEN center_lat - delta AND center_lat + delta
AND lng BETWEEN center_lng - delta AND center_lng + delta
```
Ini manfaatin fakta 1° latitude ≈ 111 km. Bikin kotak sedikit lebih gede dari radius asli, jadi nggak ada false-negative (truk di luar kotak pasti di luar radius, truk di dalam kotak belum tentu di dalam radius).

**Layer 3 — Hitung jarak akurat**
Pake `ST_Distance_Sphere()` (MySQL 5.7+) atau Haversine formula. Cuma row yang lolos bounding-box yang dihitung jaraknya. Final filter: `HAVING distance_km <= @radius`.

## Kenapa Nggak Pake Tabel Denormalized `truck_current_location`?

Bisa aja bikin tabel kecil yang selalu nyimpen lokasi terbaru tiap truk (update via trigger ON INSERT). Ini ngilangin subquery GROUP BY yang mahal. Tapi untuk skala puluhan ribu truk, query dengan index udah cukup cepet. Denormalisasi nambah kompleksitas (trigger) dan storage. Belum perlu.

Kalau system udah skala gede (ratusan ribu truk, > 1000 QPS), baru consider denormalisasi atau bahkan Redis GEO / Elasticsearch.

## Charset

`utf8mb4` + `utf8mb4_unicode_ci` — support emoji, semua karakter Unicode, dan sorting multi-bahasa.