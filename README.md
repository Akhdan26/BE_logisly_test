# BE Logisly Test

Repository ini berisi jawaban untuk tiga soal Back End Engineer take-home quiz:

| Soal | Topik | Direktori |
|------|-------|-----------|
| 1 | WhatsApp Message Parser | `src/`, `index.php`, `tests/` |
| 2 | Database Design | `database/` |
| 3 | TOP Calculation | *(coming soon)* |

---

## Requirements

| Dependency  | Version |
|-------------|---------|
| PHP         | ≥ 8.1   |
| Composer    | ≥ 2.0   |

> **Dikembangkan & di-test dengan:** PHP 8.2.29, Composer 2.7.7, PHPUnit 11.5.55

## Setup

```bash
git clone git@github.com:Akhdan26/BE_logisly_test.git
cd BE_logisly_test
composer install
```

---

## Soal No. 1 — WhatsApp Message Parser

### Run

```bash
php index.php
```

Output berupa JSON hasil parsing pesan logistik.

### Run Tests

```bash
vendor/bin/phpunit tests/
# atau verbose:
vendor/bin/phpunit tests/ --testdox
```

### Struktur

```
├── composer.json
├── index.php
├── src/
│   ├── MessageParserService.php
│   ├── Parsers/
│   │   ├── DateParser.php
│   │   └── CargoParser.php
│   └── DTO/
│       ├── CargoItemDTO.php
│       └── ParsedMessageDTO.php
└── tests/
    └── MessageParserTest.php
```

---

## Soal No. 2 — Database Design

Lihat direktori `database/`:

| File | Isi |
|------|-----|
| `database/migration.sql` | DDL schema (tables, indexes, constraints) |
| `database/query_nearest_trucks.sql` | Query optimasi cari truk dalam radius X km |
| `database/README.md` | Penjelasan desain: data types, index strategy, tradeoffs |

### Cara menjalankan migration

```bash
mysql -u root -p < database/migration.sql
```

### Cara menjalankan query radius

Buka `database/query_nearest_trucks.sql` di MySQL client (ganti parameter `@center_lat`, `@center_lng`, `@radius_km` sesuai kebutuhan).

---

## Soal No. 3 — TOP Calculation

*(coming soon)*