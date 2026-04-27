# Soal No. 2 — Database Design: Penjelasan & Tradeoffs

## 1. Tipe Data & Charset

### `trucks`

| Kolom | Tipe | Alasan |
|-------|------|--------|
| `truck_id` | `BIGINT UNSIGNED` | Kapasitas hingga ~18 quintillion, cukup untuk puluhan ribu truk. `UNSIGNED` karena ID tidak negatif, menghemat 1 bit. |
| `license_plate_number` | `VARCHAR(20)` | Plat nomor Indonesia seperti "B 1234 ABC" muat dalam 20 karakter dengan toleransi variasi format. `VARCHAR` lebih hemat storage dibanding `CHAR`. |
| `created_at` | `TIMESTAMP` | Standar untuk timestamp creation. Otomatis diisi `CURRENT_TIMESTAMP`. |

### `location_history`

| Kolom | Tipe | Alasan |
|-------|------|--------|
| `id` | `BIGINT UNSIGNED` | Tabel ini akan berisi puluhan juta row; `INT` (max ~2.1 milyar) bisa habis dalam beberapa tahun. `BIGINT` aman untuk jangka panjang. |
| `truck_id` | `BIGINT UNSIGNED` | Konsisten dengan `trucks.truck_id`. |
| `timestamp` | `DATETIME(0)` | Tanpa fractional seconds — tidak diperlukan untuk tracking lokasi kendaraan yang intervalnya dalam hitungan menit. Lebih hemat 1 byte per row dibanding `DATETIME(3)`. |
| `latitude`, `longitude` | `DECIMAL(10,7)` | Presisi 7 desimal ≈ 1.1 cm di equator. Dibanding `FLOAT` (presisi ~1.7 m) atau `DOUBLE` (presisi ~1 nm), `DECIMAL(10,7)` memberikan akurasi yang cukup tanpa overhead kalkulasi floating-point yang tidak presisi. |
| `address` | `VARCHAR(255)` | Cukup untuk alamat singkat hasil reverse-geocoding. |

### Charset & Collation

- **`utf8mb4`** + **`utf8mb4_unicode_ci`**:
  - Mendukung emoji dan semua karakter Unicode (4-byte).
  - `unicode_ci` lebih akurat untuk sorting multi-bahasa dibanding `general_ci`.
  - Cocok untuk sistem logistik yang mungkin menyimpan alamat dalam berbagai bahasa (Indonesia, Inggris, Mandarin, dll).

---

## 2. Strategi Indeks

### `trucks` — UNIQUE INDEX `idx_license_plate`

- **Tipe:** Unique B-tree index pada `license_plate_number`.
- **Kenapa:** Soal menyebutkan query exact-match `WHERE license_plate_number = 'B 1234 ABC'` sangat sering. Unique index memberikan O(log n) lookup langsung ke row yang tepat.
- **UNIQUE constraint** memastikan tidak ada duplikasi plat nomor.

### `location_history` — Composite Index `idx_truck_ts`

- **Kolom:** `(truck_id, timestamp DESC)`.
- **Kenapa:** Query "latest location per truck" butuh akses `WHERE truck_id = ? ORDER BY timestamp DESC LIMIT 1`. Index ini langsung meng-cover query tersebut — MySQL bisa membaca langsung dari index tanpa lookup ke tabel (covering index untuk kolom yang di-SELECT).
- **Loose Index Scan:** Saat melakukan `GROUP BY truck_id` dengan `MAX(timestamp)`, MySQL bisa menggunakan trik "loose index scan" pada index ini untuk menghindari full table scan.

### `location_history` — SPATIAL Index `idx_spatial`

- **Tipe:** Spatial R-tree index pada expression `POINT(longitude, latitude)`.
- **Kenapa:** Query radius membutuhkan pencarian 2D. B-tree tidak efisien untuk query "dalam radius X km dari titik Y" karena harus memeriksa dua dimensi. R-tree adalah struktur data spatial yang mengelompokkan titik-titik berdasarkan kedekatan geografis.
- **Keterbatasan**: Beberapa versi MySQL/MariaDB hanya mendukung spatial index pada kolom bertipe `GEOMETRY`, bukan expression. Untuk kompatibilitas penuh, bisa diganti dengan kolom `coordinates POINT NOT NULL SRID 4326` yang di-populate via trigger.

### Yang Sengaja TIDAK Dibuat: Composite Index `(latitude, longitude)`

- Index B-tree komposit pada dua kolom latitude & longitude tidak efektif untuk query radius. MySQL hanya bisa menggunakan prefix index (latitude saja) untuk bounding-box, lalu scan longitude secara manual. Spatial R-tree jauh lebih unggul untuk ini.

---

## 3. Query Radius: Optimasi & Tradeoffs

### Strategi 3-Layer

Query radius menggunakan pendekatan bertingkat:

1. **Subquery `MAX(timestamp) GROUP BY truck_id`** — Dapatkan hanya lokasi terbaru setiap truk. Tanpa ini, query akan menghitung jarak untuk SEMUA row history (puluhan juta), bukan hanya yang terbaru (puluhan ribu).

2. **Bounding-box pre-filter** — Sebelum menghitung jarak sphere (mahal), filter kasar dengan `WHERE lat BETWEEN ... AND lng BETWEEN ...`. Ini memanfaatkan fakta bahwa 1° latitude ≈ 111 km. Bounding-box sedikit lebih besar dari radius sebenarnya (kotak vs lingkaran), jadi tidak ada false-negative. Hanya row di dalam kotak yang lanjut ke tahap 3.

3. **ST_Distance_Sphere / Haversine** — Hitung jarak akurat hanya untuk row yang lolos bounding-box, lalu `HAVING distance_km <= @radius`.

### Akurasi vs Performa

| Metode | Akurasi | Performa |
|--------|---------|----------|
| Haversine (bumi bulat) | Error ~0.3% (bumi tidak bulat sempurna) | Cepat dengan bounding-box |
| ST_Distance_Sphere (elipsoid) | Akurat, mempertimbangkan WGS84 | Sedikit lebih lambat, tersedia MySQL 5.7+ |
| Equirectangular approximation | Error ~1-5% (semakin besar latitude, semakin besar error) | Paling cepat, tanpa trigonometri |

**Keputusan:** Gunakan ST_Distance_Sphere dengan fallback Haversine. Untuk radius kecil (< 100 km) seperti yang umum di tracking truk, perbedaan akurasi kedua metode negligible (< 0.1%). Yang paling penting adalah bounding-box pre-filter — tanpanya, performa turun drastis.

### Storage vs Query Speed

- **Tabel denormalized `truck_current_location`** (opsional): Jika query radius sangat sering (> 1000 QPS), bisa buat tabel kecil yang selalu menyimpan lokasi terbaru setiap truk (di-update via trigger ON INSERT ke location_history). Ini menghilangkan subquery `GROUP BY` yang mahal. Tradeoff: storage tambahan + kompleksitas trigger.
- **Partitioning by date**: Jika location_history tumbuh ke ratusan juta row, bisa partition by `MONTH(timestamp)` untuk memudahkan archiving data lama.

### Kenapa Tidak Pakai Elasticsearch/Redis

Soal membatasi ke built-in MySQL/MariaDB. Jika boleh external systems, Redis GEO atau Elasticsearch Geo-distance query akan jauh lebih cepat karena mereka punya struktur data spatial yang lebih matang. Tapi dengan strategi indexing di atas, MySQL bisa menangani puluhan juta row dengan response time < 100ms.

---

## 4. "Latest Location Per Truck" — Efisiensi

Problem: location_history berisi semua riwayat, tapi yang dibutuhkan hanya row terbaru per truck.

Solusi dengan composite index `idx_truck_ts (truck_id, timestamp DESC)`:

```sql
SELECT truck_id, MAX(timestamp)
FROM location_history
GROUP BY truck_id
```

MySQL bisa melakukan **loose index scan**: melompat langsung ke timestamp terbaru setiap truck_id tanpa membaca semua row. Ini jauh lebih cepat daripada full table scan + filesort.

Untuk join mendapatkan data lengkap (lat, lng, address), gunakan derived table join:

```sql
SELECT lh.*
FROM location_history lh
INNER JOIN (
    SELECT truck_id, MAX(timestamp) AS max_ts
    FROM location_history
    GROUP BY truck_id
) latest ON lh.truck_id = latest.truck_id AND lh.timestamp = latest.max_ts
```

---

## 5. Tradeoff Tambahan

### VARCHAR vs CHAR untuk license_plate_number

- `VARCHAR(20)`: hemat storage (hanya simpan panjang aktual + 1-2 byte overhead), cocok karena plat nomor bervariasi panjangnya.
- `CHAR(20)`: fixed-length, lebih cepat untuk lookup exact-match karena MySQL tahu persis offset setiap row. Tapi boros storage.
- **Pilihan:** VARCHAR, karena penghematan storage untuk puluhan ribu row lebih signifikan daripada gain performa marginal CHAR.

### InnoDB vs MyISAM

- **InnoDB**: mendukung foreign key, row-level locking, transactions, dan ONLINE DDL. Spatial index support lebih baik di MySQL 5.7+.
- **MyISAM**: dulu lebih cepat untuk spatial queries, tapi sekarang InnoDB sudah menyamai. MyISAM tidak support FK.
- **Pilihan:** InnoDB.

### Auto-increment vs UUID untuk primary key

- **Auto-increment BIGINT**: sequential, insert-friendly (no page splits), compact (8 bytes). Cocok untuk append-only table seperti location_history.
- **UUID**: 16 bytes, random, menyebabkan page splits dan fragmentasi. Tidak direkomendasikan kecuali perlu distributed ID generation.
- **Pilihan:** Auto-increment BIGINT.